"""Testes do coletor: .venv\\Scripts\\python -m unittest coletor.test_coletor"""
import json
import tempfile
import threading
import unittest
from http.server import BaseHTTPRequestHandler, HTTPServer
from pathlib import Path

import numpy as np

from coletor import fontes, simulador
from coletor.envio import Cliente
from coletor.extracao import extrair


class TestExtracao(unittest.TestCase):
    def test_curva_trapezio_conhecida(self):
        t = np.arange(0, 8.01, 0.05)
        i = np.where((t >= 1) & (t <= 7), 1.5, 0.0)
        i[(t >= 1) & (t < 1.2)] = 4.0
        m = extrair(t, i)
        self.assertAlmostEqual(m.tempo_manobra_s, 6.0, places=2)
        self.assertEqual(m.corrente_pico, 4.0)
        self.assertGreater(m.corrente_media, 1.5)

    def test_motor_que_nao_aciona(self):
        with self.assertRaisesRegex(ValueError, "não energizou"):
            extrair([0, 1, 2], [0.0, 0.1, 0.0])


class TestSimulador(unittest.TestCase):
    def setUp(self):
        self.rng = np.random.default_rng(1)

    def test_tempo_nominal_por_versao(self):
        for tipo, nominal in simulador.TEMPO_NOMINAL.items():
            m = extrair(*simulador.curva(tipo, "normal", 0, self.rng))
            self.assertAlmostEqual(m.tempo_manobra_s, nominal, delta=0.15)

    def test_atrito_aumenta_tempo_e_motor_reduz_pico(self):
        ini = extrair(*simulador.curva("agulha", "atrito", 0, self.rng))
        fim = extrair(*simulador.curva("agulha", "atrito", 1, self.rng))
        self.assertGreater(fim.tempo_manobra_s - ini.tempo_manobra_s, 0.5)
        p0 = extrair(*simulador.curva("agulha", "motor", 0, self.rng)).corrente_pico
        p1 = extrair(*simulador.curva("agulha", "motor", 1, self.rng)).corrente_pico
        self.assertLess(p1, 0.8 * p0)


class TestFontes(unittest.TestCase):
    def test_csv_agrupa_por_manobra(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d) / "c.csv"
            p.write_text("maquina;medido_em;t_s;corrente_a\nMCH 1;2026-10-02 08:00;0;0\nMCH 1;2026-10-02 08:00;0,5;1,4\n"
                         "MCH 1;2026-10-01 08:00;0;0\n", encoding="utf-8")
            manobras = list(fontes.ler_csv(p))
        self.assertEqual([m[1] for m in manobras], ["2026-10-01 08:00", "2026-10-02 08:00"])
        self.assertEqual(manobras[1][3], [0.0, 1.4])

    def test_log_do_intertravamento_traduz_eventos_e_ignora_os_demais(self):
        with tempfile.TemporaryDirectory() as d:
            p = Path(d) / "log.csv"
            p.write_text("maquina;ocorrido_em;evento;observacoes\n"
                         "MCH 4134 JM;2026-10-28 07:42:10;Perda de correspondência;posição normal\n"
                         "MCH 4134 JM;2026-10-28 07:40:00;Comando de manobra;\n"
                         "MCH 4123 JM;2026-10-28 06:00:00;Maquina travada;\n", encoding="utf-8")
            eventos, ignorados = fontes.ler_eventos(p)
        self.assertEqual(ignorados, 1)
        self.assertEqual([e["evento"] for e in eventos], ["travamento", "perda_correspondencia"])  # em ordem de horário
        self.assertEqual(eventos[1]["observacoes"], "posição normal")


class _Api(BaseHTTPRequestHandler):
    status = 201
    recebidos: list = []

    def do_POST(self):
        corpo = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
        _Api.recebidos.append(corpo)
        self.send_response(_Api.status)
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        itens = next(iter(corpo.values()))  # {"leituras": [...]} ou {"eventos": [...]}
        self.wfile.write(json.dumps({"registradas": len(itens), "duplicadas_ignoradas": 0, "maquinas": []}).encode())

    def log_message(self, *_):
        pass


class TestEnvio(unittest.TestCase):
    def setUp(self):
        self.dir = tempfile.TemporaryDirectory()
        self.fila = Path(self.dir.name) / "fila.jsonl"

    def tearDown(self):
        self.dir.cleanup()

    def test_api_fora_guarda_na_fila_e_reenvia_depois(self):
        fora = Cliente("http://127.0.0.1:9", "t", fila=self.fila, timeout=2)
        r = fora.enviar([{"maquina": "MCH 1"}, {"maquina": "MCH 2"}])
        self.assertEqual(r["na_fila"], 2)
        self.assertEqual(len(fora.pendentes()), 2)

        srv = HTTPServer(("127.0.0.1", 0), _Api)
        threading.Thread(target=srv.serve_forever, daemon=True).start()
        try:
            _Api.recebidos = []
            ok = Cliente(f"http://127.0.0.1:{srv.server_port}", "t", fila=self.fila)
            r = ok.enviar([{"maquina": "MCH 3"}])
        finally:
            srv.shutdown()
            srv.server_close()
        self.assertEqual(r["registradas"], 3)
        self.assertEqual([l["maquina"] for l in _Api.recebidos[0]["leituras"]], ["MCH 1", "MCH 2", "MCH 3"])
        self.assertFalse(self.fila.exists())

    def test_eventos_vao_para_o_recurso_proprio_com_fila_separada(self):
        cli = Cliente("http://127.0.0.1:9", "t", timeout=2, recurso="eventos")
        self.assertTrue(cli.url.endswith("/api/v1/eventos"))
        self.assertEqual(cli.fila.name, "fila_eventos.jsonl")

        srv = HTTPServer(("127.0.0.1", 0), _Api)
        threading.Thread(target=srv.serve_forever, daemon=True).start()
        try:
            _Api.recebidos = []
            ok = Cliente(f"http://127.0.0.1:{srv.server_port}", "t", fila=self.fila, recurso="eventos")
            r = ok.enviar([{"maquina": "MCH 1", "ocorrido_em": "2026-10-28 07:42", "evento": "travamento"}])
        finally:
            srv.shutdown()
            srv.server_close()
        self.assertEqual(r["registradas"], 1)
        self.assertIn("eventos", _Api.recebidos[0])


if __name__ == "__main__":
    unittest.main()
