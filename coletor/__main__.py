"""Linha de comando do coletor.

  python -m coletor simular --maquina "MCH 5001 JM" --cenario atrito            # histórico de 20 dias
  python -m coletor simular --maquina "MCH 5001 JM" --cenario atrito --ao-vivo  # e continua enviando ao vivo
  python -m coletor arquivo curvas.csv                                          # curvas reais (maquina, medido_em, t_s, corrente_a)
  python -m coletor eventos log_intertravamento.csv                             # perdas de correspondência/travamentos
  python -m coletor eventos log_intertravamento.csv --acompanhar                # e continua lendo o log (novas linhas)
  python -m coletor reenviar                                                    # envia o que ficou nas filas

API: --url (ou CBM_API_URL; padrão http://127.0.0.1:8000) e token em CBM_API_TOKEN (ou lido de web/.env).
"""
import argparse
import os
import re
import sys
import time
from datetime import datetime, timedelta
from pathlib import Path

import numpy as np

from . import fontes, simulador
from .envio import Cliente
from .extracao import extrair

RAIZ = Path(__file__).resolve().parent.parent


def _token() -> str:
    if os.environ.get("CBM_API_TOKEN"):
        return os.environ["CBM_API_TOKEN"]
    env = RAIZ / "web" / ".env"
    if env.exists():
        m = re.search(r"^CBM_API_TOKEN=(.+)$", env.read_text(encoding="utf-8"), re.M)
        if m and m.group(1).strip():
            return m.group(1).strip().strip('"')
    sys.exit("Defina CBM_API_TOKEN (ou configure-o em web/.env).")


def _tipo(maquina: str, tipo: str | None) -> str:
    return tipo or ("jacare" if re.search(r"\bJM$", maquina.strip(), re.I) else "agulha")


def _leitura(maquina, tipo, quando: datetime, t, i, responsavel) -> dict:
    m = extrair(t, i)
    return {
        "maquina": maquina, "tipo": tipo, "medido_em": quando.strftime("%Y-%m-%d %H:%M:%S"),
        "tempo_manobra_s": m.tempo_manobra_s, "corrente_pico": m.corrente_pico, "corrente_media": m.corrente_media,
        "ocorrencia": "Leitura de monitoramento", "responsavel": responsavel,
        "observacoes": f"Coletor: área da curva {m.corrente_area} A·s",
    }


def _relatorio(resumo: dict):
    print(f"  registradas={resumo['registradas']} duplicadas_ignoradas={resumo['duplicadas']}"
          f" na_fila={resumo['na_fila']} rejeitadas={resumo['rejeitadas']}")
    if resumo["erro"]:
        print(f"  ! {resumo['erro']}")
    for m in resumo["maquinas"]:
        veredito = "PRECISA DE INTERVENÇÃO" if m["precisa_intervencao"] else "sem intervenção no horizonte"
        print(f"  {m['codigo']} [{m['tipo']}] status={m['status']} → {veredito}")
        for p in m["plano"]:
            if p["prioridade"] != "Monitorar":
                prazo = "agora" if p["prazo_dias"] == 0 else ("prazo n/d" if p["prazo_dias"] is None else f"~{p['prazo_dias']:.0f} d")
                print(f"     - {p['componente']} ({p['prioridade']}, {prazo}): {p['motivo']}")


def cmd_simular(a, cli: Cliente):
    rng = np.random.default_rng(a.seed)
    tipo = _tipo(a.maquina, a.tipo)
    n = a.dias * a.por_dia
    inicio = datetime.now().replace(second=0, microsecond=0) - timedelta(days=a.dias)
    passo = timedelta(hours=24 / a.por_dia)
    print(f"Simulando {n} manobras de {a.maquina} ({tipo}, cenário '{a.cenario}': {simulador.CENARIOS[a.cenario]})")
    historico = []
    for k in range(n):
        t, i = simulador.curva(tipo, a.cenario, a.ate * k / max(1, n - 1), rng)
        historico.append(_leitura(a.maquina, tipo, inicio + k * passo, t, i, a.responsavel))
    _relatorio(cli.enviar(historico))

    if a.ao_vivo:
        print(f"Ao vivo: uma manobra a cada {a.intervalo}s (Ctrl+C para parar)")
        j = 0
        try:
            while not a.manobras or j < a.manobras:
                time.sleep(a.intervalo)
                j += 1
                t, i = simulador.curva(tipo, a.cenario, min(1.0, a.ate + 0.03 * j), rng)
                print(f"[{datetime.now():%H:%M:%S}] manobra {j}")
                _relatorio(cli.enviar([_leitura(a.maquina, tipo, datetime.now(), t, i, a.responsavel)]))
        except KeyboardInterrupt:
            print("encerrado")


def cmd_arquivo(a, cli: Cliente):
    leituras = []
    for maquina, medido_em, t, i in fontes.ler_csv(Path(a.caminho)):
        try:
            leituras.append(_leitura(maquina, _tipo(maquina, a.tipo), datetime.fromisoformat(medido_em), t, i, a.responsavel))
        except ValueError as e:
            print(f"  ! {maquina} {medido_em}: {e}")
    print(f"{len(leituras)} manobras extraídas de {a.caminho}")
    _relatorio(cli.enviar(leituras))


def cmd_eventos(a, cli: Cliente):
    """Envia os eventos do log do intertravamento; com --acompanhar, relê o arquivo e envia só as linhas novas."""
    enviados = 0
    while True:
        eventos, ignorados = fontes.ler_eventos(Path(a.caminho))
        novos = eventos[enviados:]
        if novos or not a.acompanhar:
            print(f"[{datetime.now():%H:%M:%S}] {len(novos)} eventos novos em {a.caminho} ({ignorados} linhas de outros eventos ignoradas)")
            _relatorio(cli.enviar(novos))
        enviados = len(eventos)
        if not a.acompanhar:
            return
        try:
            time.sleep(a.intervalo)
        except KeyboardInterrupt:
            print("encerrado")
            return


def main(argv=None):
    p = argparse.ArgumentParser(prog="python -m coletor", description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--url", default=os.environ.get("CBM_API_URL", "http://127.0.0.1:8000"))
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("simular", help="gera curvas sintéticas com degradação e envia")
    s.add_argument("--maquina", required=True)
    s.add_argument("--tipo", choices=["agulha", "jacare"], help="padrão: pelo sufixo do código (JM = jacaré)")
    s.add_argument("--cenario", choices=list(simulador.CENARIOS), default="normal")
    s.add_argument("--dias", type=int, default=20, help="dias de histórico a gerar")
    s.add_argument("--por-dia", type=int, default=2, help="manobras por dia no histórico")
    s.add_argument("--ate", type=float, default=0.6,
                   help="nível de degradação ao fim do histórico (0 a 1); 0,6 para antes do limite, e o modo ao vivo o cruza")
    s.add_argument("--ao-vivo", action="store_true", help="depois do histórico, continua enviando em tempo real")
    s.add_argument("--intervalo", type=float, default=10, help="segundos entre manobras ao vivo")
    s.add_argument("--manobras", type=int, default=0, help="manobras ao vivo (0 = até Ctrl+C)")
    s.add_argument("--responsavel", default="MOCK", help="MOCK = removível em 'Remover dados de exemplo'")
    s.add_argument("--seed", type=int, default=7)

    f = sub.add_parser("arquivo", help="lê curvas de um CSV (maquina, medido_em, t_s, corrente_a) e envia")
    f.add_argument("caminho")
    f.add_argument("--tipo", choices=["agulha", "jacare"])
    f.add_argument("--responsavel", default="COLETOR")

    e = sub.add_parser("eventos", help="lê o log do intertravamento (maquina, ocorrido_em, evento) e envia os eventos")
    e.add_argument("caminho")
    e.add_argument("--acompanhar", action="store_true", help="continua lendo o arquivo e envia as linhas novas")
    e.add_argument("--intervalo", type=float, default=10, help="segundos entre leituras do log no modo --acompanhar")

    sub.add_parser("reenviar", help="envia as leituras e os eventos que ficaram nas filas locais")

    a = p.parse_args(argv)
    token = _token()
    cli = Cliente(a.url, token)
    if a.cmd == "simular":
        cmd_simular(a, cli)
    elif a.cmd == "arquivo":
        cmd_arquivo(a, cli)
    elif a.cmd == "eventos":
        cmd_eventos(a, Cliente(a.url, token, recurso="eventos"))
    else:
        for c in (cli, Cliente(a.url, token, recurso="eventos")):
            print(f"{len(c.pendentes())} {c.recurso} na fila")
            _relatorio(c.enviar([]))


if __name__ == "__main__":
    main()
