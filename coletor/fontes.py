"""Fontes de curvas de corrente.

Hoje: arquivo CSV com uma linha por amostra (maquina, medido_em, t_s, corrente_a); cada par
(maquina, medido_em) é uma manobra. Quando a Motiva enviar o formato do arquivamento do sistema NI
da Linha 4, basta escrever outra função aqui que devolva o mesmo tipo de item.
"""
import csv
from collections import defaultdict
from pathlib import Path
from typing import Iterator

COLUNAS = {"maquina", "medido_em", "t_s", "corrente_a"}


def ler_csv(caminho: Path) -> Iterator[tuple[str, str, list[float], list[float]]]:
    """Devolve (maquina, medido_em, t_s, corrente_a) por manobra, na ordem de medido_em."""
    with open(caminho, newline="", encoding="utf-8-sig") as f:
        amostra = f.read(2048)
        f.seek(0)
        leitor = csv.DictReader(f, delimiter=";" if amostra.count(";") > amostra.count(",") else ",")
        faltam = COLUNAS - {c.strip().lower() for c in (leitor.fieldnames or [])}
        if faltam:
            raise ValueError(f"colunas ausentes em {caminho.name}: {', '.join(sorted(faltam))}")
        manobras = defaultdict(lambda: ([], []))
        for linha in leitor:
            r = {k.strip().lower(): v for k, v in linha.items()}
            t, i = manobras[(r["maquina"].strip(), r["medido_em"].strip())]
            t.append(float(r["t_s"].replace(",", ".")))
            i.append(float(r["corrente_a"].replace(",", ".")))
    for (maquina, medido_em), (t, i) in sorted(manobras.items(), key=lambda kv: kv[0][1]):
        yield maquina, medido_em, t, i


# Log de eventos do intertravamento: uma linha por evento (maquina, ocorrido_em, evento[, observacoes]).
# O nome do evento no log é traduzido para o código da API; outros eventos do log são ignorados.
COLUNAS_EVENTOS = {"maquina", "ocorrido_em", "evento"}
EVENTOS = {
    "perda_correspondencia": "perda_correspondencia", "perda de correspondencia": "perda_correspondencia",
    "perda de correspondência": "perda_correspondencia", "falha de deteccao": "perda_correspondencia",
    "travamento": "travamento", "maquina travada": "travamento",
}


def ler_eventos(caminho: Path) -> tuple[list[dict], int]:
    """Devolve (eventos no formato da API, quantas linhas do log foram ignoradas por não serem de interesse)."""
    with open(caminho, newline="", encoding="utf-8-sig") as f:
        amostra = f.read(2048)
        f.seek(0)
        leitor = csv.DictReader(f, delimiter=";" if amostra.count(";") > amostra.count(",") else ",")
        faltam = COLUNAS_EVENTOS - {c.strip().lower() for c in (leitor.fieldnames or [])}
        if faltam:
            raise ValueError(f"colunas ausentes em {caminho.name}: {', '.join(sorted(faltam))}")
        eventos, ignorados = [], 0
        for linha in leitor:
            r = {k.strip().lower(): (v or "").strip() for k, v in linha.items()}
            codigo = EVENTOS.get(r["evento"].lower())
            if not codigo:
                ignorados += 1
                continue
            e = {"maquina": r["maquina"], "ocorrido_em": r["ocorrido_em"], "evento": codigo}
            if r.get("observacoes"):
                e["observacoes"] = r["observacoes"]
            eventos.append(e)
    return sorted(eventos, key=lambda e: e["ocorrido_em"]), ignorados
