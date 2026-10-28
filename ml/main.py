"""Serviço do modelo preditivo (FastAPI). Consumido pelo Laravel no Painel de simulação.

Executar na raiz do projeto:  .venv\\Scripts\\uvicorn ml.main:app --port 8001
"""
import sys
import threading
import zlib
from functools import lru_cache, wraps
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

import numpy as np
import pandas as pd
from fastapi import FastAPI, HTTPException, Query

from src import config as C
from src import db
from src.curva import amostrar
from src.stage3_model import carregar_modelo, carregar_modelo_aviso, carregar_modelo_modo, estados, features_de, ids_teste, pontuar

app = FastAPI(title="CBM S700K — serviço do modelo", version="2.0")


@lru_cache(maxsize=1)
def _modelo():
    mdl, met = carregar_modelo()
    return mdl, met, carregar_modelo_modo()


@lru_cache(maxsize=1)
def _intervalo():
    r = db.consultar("SELECT MIN(ciclo) AS mn, MAX(ciclo) AS mx FROM features").iloc[0]
    return int(r["mn"]), int(r["mx"])


@lru_cache(maxsize=1)
def _casos() -> pd.DataFrame:
    return db.consultar("SELECT * FROM casos")


_TRAVA = threading.RLock()


def _uma_vez(f):
    """Serializa o cálculo em cache: quem chega durante o aquecimento espera, em vez de calcular de novo em paralelo."""
    @wraps(f)
    def g(*a):
        with _TRAVA:
            return f(*a)
    g.cache_clear = f.cache_clear
    return g


@_uma_vez
@lru_cache(maxsize=1)
def _tudo() -> pd.DataFrame:
    """Todas as leituras com o risco do modelo e o estado do alarme (persistência + histerese), calculados uma vez."""
    mdl, met, _ = _modelo()
    FEATURES = features_de(mdl)  # noqa: N806
    colunas = ["maquina_id", "ciclo", "maquina", "tipo"] + sorted(set(FEATURES) | {"isolamento_mohm", "ciclos_ate_1mohm"})
    feat = db.consultar(f"SELECT {', '.join(colunas)} FROM features")  # só o necessário: a tabela inteira tem ~60 colunas
    feat = feat.dropna(subset=FEATURES).sort_values(["maquina_id", "ciclo"]).reset_index(drop=True)
    feat["risco"] = mdl.predict_proba(feat[FEATURES])[:, 1]
    aviso = carregar_modelo_aviso()  # amarelo "programar inspeção" (modelo de prazo longo)
    p_aviso = aviso.predict_proba(feat[FEATURES])[:, 1] if aviso is not None else None
    es = estados(feat, feat["risco"].to_numpy(), met["limiar_vermelho"], p_aviso, met.get("limiar_amarelo"))
    return feat.assign(alarme=es["alarme"].to_numpy(), aviso=es["aviso"].to_numpy(), severidade=es["severidade"].to_numpy())


@_uma_vez
@lru_cache(maxsize=32)
def _pontuar(ciclo: int) -> pd.DataFrame:
    mdl, met, modo = _modelo()
    feat = _tudo()
    # o estado do alarme numa leitura só depende das anteriores: o pré-calculado vale para qualquer ciclo
    sc = pontuar(mdl, met["limiar_vermelho"], feat[feat["ciclo"] <= ciclo], modo)
    sc = sc.sort_values(["severidade", "risco"], key=lambda c: c.map(ORDEM) if c.name == "severidade" else -c)
    return sc.merge(_casos()[["caso_id", "desfecho", "modo"]], left_on="maquina_id", right_on="caso_id", how="left")


ORDEM = {"Vermelho": 0, "Amarelo": 1, "Verde": 2}
CODIGO = {"Verde": "G", "Amarelo": "A", "Vermelho": "R"}


def _limpa(v):
    if isinstance(v, (np.floating, float)):
        return None if np.isnan(v) else float(v)
    if isinstance(v, np.integer):
        return int(v)
    return v


def _ciclo(ciclo):
    mn, mx = _intervalo()
    return mx if ciclo is None else min(max(ciclo, mn + 5), mx)


def _item(r: pd.Series) -> dict:
    return {
        "maquina_id": r["maquina_id"], "maquina": r["maquina"], "tipo": r["tipo"], "ciclo": _limpa(r["ciclo"]),
        "severidade": r["severidade"], "risco": _limpa(r["risco"]),
        "modo_provavel": r["modo_provavel"], "modo_rotulo": C.MODOS_FALHA[r["modo_provavel"]],
        "isolamento_mohm": _limpa(r["isolamento_mohm"]), "fatores": r["fatores"], "tendencia": r["tendencia"],
        "ciclos_ate_1mohm": _limpa(r["ciclos_ate_1mohm"]),
        "acao": C.ACOES[r["modo_provavel"]] if r["severidade"] != "Verde" else C.ACOES["nenhuma"],
        # gabarito da simulação (só existe porque a base é simulada)
        "desfecho_real": r["desfecho"], "modo_real": r["modo"], "modo_real_rotulo": C.MODOS_FALHA.get(r["modo"], r["modo"]),
    }


@app.get("/health")
def health():
    try:
        _modelo()
        _intervalo()
    except Exception as e:  # artefatos ausentes
        raise HTTPException(503, f"Modelo/banco indisponível: {e}. Rode `python run_pipeline.py`.")
    return {"status": "ok"}


@app.get("/meta")
def meta():
    health()
    _, met, _ = _modelo()
    mn, mx = _intervalo()
    return {"ciclo_min": mn + 5, "ciclo_max": mx, "metricas": met, "isolamento_min": C.ISOLAMENTO_MIN,
            "horizonte_ciclos": C.HORIZONTE, "intervalo_h": C.INTERVALO_H}


@app.get("/casos")
def casos():
    """Resumo da base simulada e desempenho por modo (conjunto de teste)."""
    health()
    _, met, _ = _modelo()
    cs = _casos()
    falhas = cs[cs["desfecho"] == "falha"]
    por_modo = []
    for modo, g in falhas.groupby("modo"):
        m = met["por_modo"].get(modo, {})
        por_modo.append({
            "modo": modo, "rotulo": C.MODOS_FALHA[modo], "casos": int(len(g)), "participacao": round(len(g) / len(falhas), 3),
            "casos_reais_base": sorted({int(x) for x in g["evento_base"].dropna()}),
            "anomalia_corrente": g["anomalia_corrente"].value_counts().to_dict(),
            "teste_casos": m.get("casos"), "antecipadas": m.get("antecipadas"), "taxa_antecipacao": m.get("taxa"),
            "antecedencia_mediana_h": m.get("antecedencia_mediana_h"), "acerto_modo": met["acerto_modo"].get(modo),
        })
    return {
        "total": int(len(cs)), "falhas": int(len(falhas)), "saudaveis": int((cs["desfecho"] == "saudável").sum()),
        "maquinas_fisicas": int(cs["maquina"].nunique()), "jacare": round(float((cs["tipo"] == "jacare").mean()), 3),
        "com_precursor_fuso": int(falhas.loc[falhas["modo"] == "fuso_retorno", "precursor"].astype(bool).sum()),
        "periodo": [str(falhas["data_falha"].min())[:10], str(falhas["data_falha"].max())[:10]],
        "por_modo": sorted(por_modo, key=lambda m: -m["casos"]),
    }


@_uma_vez
@lru_cache(maxsize=1)
def _animacao() -> dict:
    """Linha do tempo de todos os históricos: um caractere de estado por ciclo.

    G verde · A amarelo · R vermelho · F falhou (a partir do ciclo da falha).
    Uma falha é "antecipada" se houve alarme nos ANTECEDENCIA_MAX ciclos anteriores.
    """
    _, met, _ = _modelo()
    mn, mx = _intervalo()
    ciclos = list(range(mn + 5, mx + 1))
    feat = _tudo()
    cs = _casos().set_index("caso_id")
    falha = db.consultar("SELECT maquina_id, MAX(ciclo) + 1 AS ciclo_falha FROM features GROUP BY maquina_id").set_index("maquina_id")["ciclo_falha"]
    teste = ids_teste()
    historicos = []
    for mid, g in feat.groupby("maquina_id", sort=True):
        sev = dict(zip(g["ciclo"], g["severidade"].map(CODIGO)))
        caso = cs.loc[mid]
        cf = int(falha[mid]) if caso["desfecho"] == "falha" else None
        ultimo, linha = "G", []
        for c in ciclos:
            if cf is not None and c >= cf:
                linha.append("F")
            else:
                ultimo = sev.get(c, ultimo)
                linha.append(ultimo)
        antecipada = vermelho_h = None
        if cf is not None:
            antes = g[(g["ciclo"] >= cf - C.ANTECEDENCIA_MAX) & (g["ciclo"] < cf)]
            horas = lambda col: None if pd.isna(p := antes.loc[antes[col], "ciclo"].min()) else int((cf - p) * C.INTERVALO_H)  # noqa: E731
            antecipada = horas("aviso")     # 1º aviso (amarelo ou vermelho)
            vermelho_h = horas("alarme")    # 1º vermelho
        historicos.append({
            "id": mid, "maquina": caso["maquina"], "desfecho": caso["desfecho"], "modo": caso["modo"],
            "modo_rotulo": C.MODOS_FALHA.get(caso["modo"], caso["modo"]), "ciclo_falha": cf,
            "antecedencia_h": antecipada, "antecedencia_vermelho_h": vermelho_h, "estados": "".join(linha), "teste": mid in teste,
        })
    # ordem embaralhada (fixa): na grade, a posição do ponto não revela se o histórico vai falhar
    historicos.sort(key=lambda h: zlib.crc32(h["id"].encode()))
    return {"ciclos": ciclos, "intervalo_h": C.INTERVALO_H, "antecedencia_max_h": C.ANTECEDENCIA_MAX * C.INTERVALO_H,
            "regra": met["regra_persistencia"], "niveis": met.get("regra_niveis", ""), "historicos": historicos}


@app.on_event("startup")
def _aquecer():
    """Calcula o risco de todas as leituras e a linha do tempo ao subir, em segundo plano: a 1ª abertura já fica rápida."""
    def tarefa():
        try:
            _animacao()
            _pontuar(_intervalo()[1])
        except Exception:  # sem artefatos ainda: o /health explica o que falta
            pass

    threading.Thread(target=tarefa, daemon=True).start()


@app.get("/animacao")
def animacao():
    health()
    return _animacao()


@app.get("/simulacao")
def simulacao(ciclo: int | None = Query(None), limite: int = Query(300, ge=1, le=2000)):
    health()
    ciclo = _ciclo(ciclo)
    sc = _pontuar(ciclo)
    contagem = {s: int((sc["severidade"] == s).sum()) for s in ("Vermelho", "Amarelo", "Verde")}
    return {"ciclo": ciclo, "contagem": contagem, "total": int(len(sc)), "itens": [_item(r) for _, r in sc.head(limite).iterrows()]}


@app.get("/simulacao/{maquina_id}")
def diagnostico(maquina_id: str, ciclo: int | None = Query(None)):
    health()
    ciclo = _ciclo(ciclo)
    sc = _pontuar(ciclo)
    linha = sc[sc["maquina_id"] == maquina_id]
    if linha.empty:
        raise HTTPException(404, f"Histórico {maquina_id} sem pontuação até o ciclo {ciclo}")
    r = linha.iloc[0]
    hist = db.consultar("SELECT ciclo, tempo_manobra_s, isolamento_mohm, corrente_pico, corrente_patamar, corrente_rampa "
                        "FROM features WHERE maquina_id=? AND ciclo<=? ORDER BY ciclo", (maquina_id, ciclo))

    def curva(row):
        t, i = amostrar(row["tempo_manobra_s"], row["corrente_pico"], row["corrente_patamar"], row["corrente_rampa"],
                        seed=zlib.crc32(f"{maquina_id}-{int(row['ciclo'])}".encode()))
        return {"t": np.round(t, 3).tolist(), "a": np.round(i, 3).tolist(), "ciclo": int(row["ciclo"])}

    caso = _casos().set_index("caso_id").loc[maquina_id]
    return {
        **_item(r),
        "curva_base": curva(hist.iloc[min(5, len(hist) - 1)]),
        "curva_atual": curva(hist.iloc[-1]),
        "isolamento": {"ciclo": hist["ciclo"].tolist(), "mohm": hist["isolamento_mohm"].round(3).tolist()},
        "tempo": {"ciclo": hist["ciclo"].tolist(), "s": hist["tempo_manobra_s"].round(3).tolist()},
        "caso": {k: _limpa(v) if not isinstance(v, str) else v for k, v in caso.items()},
    }
