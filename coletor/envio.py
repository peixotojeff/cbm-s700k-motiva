"""Envio para a API do CBM com fila local: se a API estiver fora, as leituras ficam em disco e são reenviadas depois.

O reenvio é seguro porque a API ignora leitura repetida (mesma máquina, mesmo medido_em).
"""
import json
import urllib.error
import urllib.request
from pathlib import Path

PASTA = Path(__file__).resolve().parent
FILA = PASTA / "fila.jsonl"
REJEITADAS = PASTA / "rejeitadas.jsonl"
LOTE = 200


class Cliente:
    def __init__(self, url: str, token: str, fila: Path | None = None, timeout: float = 15, recurso: str = "leituras"):
        """recurso = "leituras" (medições) ou "eventos" (log do intertravamento); cada um tem a sua fila local."""
        self.url = url.rstrip("/") + f"/api/v1/{recurso}"
        self.recurso = recurso
        self.token = token
        self.fila = fila or (FILA if recurso == "leituras" else PASTA / f"fila_{recurso}.jsonl")
        self.timeout = timeout

    def _post(self, leituras: list[dict]) -> dict:
        req = urllib.request.Request(
            self.url, data=json.dumps({self.recurso: leituras}).encode(), method="POST",
            headers={"Authorization": f"Bearer {self.token}", "Content-Type": "application/json", "Accept": "application/json"},
        )
        with urllib.request.urlopen(req, timeout=self.timeout) as r:
            return json.loads(r.read())

    def _enfileirar(self, leituras: list[dict]):
        with self.fila.open("a", encoding="utf-8") as f:
            for l in leituras:
                f.write(json.dumps(l, ensure_ascii=False) + "\n")

    def pendentes(self) -> list[dict]:
        if not self.fila.exists():
            return []
        return [json.loads(x) for x in self.fila.read_text(encoding="utf-8").splitlines() if x.strip()]

    def enviar(self, leituras: list[dict]) -> dict:
        """Envia a fila pendente + as novas leituras em lotes. Devolve um resumo e a última avaliação recebida."""
        todas = self.pendentes() + leituras
        self.fila.unlink(missing_ok=True)
        resumo = {"registradas": 0, "duplicadas": 0, "na_fila": 0, "rejeitadas": 0, "maquinas": [], "erro": None}
        for k in range(0, len(todas), LOTE):
            lote = todas[k:k + LOTE]
            try:
                r = self._post(lote)
                resumo["registradas"] += r["registradas"]
                resumo["duplicadas"] += r.get("duplicadas_ignoradas", 0)
                resumo["maquinas"] = r["maquinas"]
            except urllib.error.HTTPError as e:
                corpo = e.read().decode(errors="replace")
                if e.code == 422:  # dado inválido: não adianta reenviar
                    with REJEITADAS.open("a", encoding="utf-8") as f:
                        f.write(json.dumps({"erro": corpo, "leituras": lote}, ensure_ascii=False) + "\n")
                    resumo["rejeitadas"] += len(lote)
                    resumo["erro"] = f"HTTP 422: {corpo[:300]}"
                else:  # 401/429/5xx: guarda para tentar de novo
                    self._enfileirar(todas[k:])
                    resumo["na_fila"] += len(todas) - k
                    resumo["erro"] = f"HTTP {e.code}: {corpo[:300]}"
                    break
            except (urllib.error.URLError, TimeoutError, ConnectionError) as e:
                self._enfileirar(todas[k:])
                resumo["na_fila"] += len(todas) - k
                resumo["erro"] = f"API inacessível ({e}); leituras guardadas em {self.fila.name}"
                break
        return resumo
