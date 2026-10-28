"""Estágio 2: indicadores de degradação temporal."""
import numpy as np
import pandas as pd

from . import config as C

FEATURES_BASE = [
    "tempo_rel", "forca_rel", "isolamento_mohm", "corrente_area", "corrente_pico", "pico_rel", "corrente_patamar", "corrente_rampa",
    "tempo_mm", "tempo_dp", "forca_mm", "forca_dp", "area_mm", "area_dp",
    "isolamento_taxa", "ciclos_ate_1mohm", "perda_corr_janela", "tipo_jacare",
]
# Base própria de cada máquina (cada S700K tem seu "normal") e tendência explícita nas últimas manobras.
FEATURES_MAQUINA = [
    "tempo_vs_base", "forca_vs_base", "area_vs_base", "patamar_vs_base", "rampa_vs_base",
    "tempo_taxa", "area_taxa", "pico_taxa", "forca_taxa", "tempo_vs_base_mm20",
]
# HIPÓTESE: tempo de confirmação da correspondência (microswitch), ainda não medido na Linha 4.
FEATURES_CORRESPONDENCIA = ["corresp_vs_base", "corresp_mm", "corresp_taxa"]


def features_modelo(correspondencia: bool | None = None) -> list[str]:
    usar = C.MONITORAR_CORRESPONDENCIA if correspondencia is None else correspondencia
    return FEATURES_BASE + FEATURES_MAQUINA + (FEATURES_CORRESPONDENCIA if usar else [])


FEATURES = features_modelo()


def _slope(y):
    return np.polyfit(np.arange(len(y)), y, 1)[0]


def _inclinacao(s: pd.Series, w: int = C.JANELA) -> pd.Series:
    """Inclinação da reta (por ciclo) nas últimas w leituras, por mínimos quadrados, vetorizada."""
    t = pd.Series(np.arange(len(s), dtype=float), index=s.index)
    soma_y = s.rolling(w, min_periods=w).sum()
    soma_ty = (t * s).rolling(w, min_periods=w).sum()
    t0 = t - (w - 1)
    sxy = soma_ty - t0 * soma_y - (w - 1) / 2 * soma_y      # Σ (x − x̄) y, com x = 0..w−1
    sxx = w * (w ** 2 - 1) / 12
    return (sxy / sxx).fillna(0.0)


def construir_features(tele: pd.DataFrame) -> pd.DataFrame:
    """Janelas móveis e tendências por histórico. Tempo e força entram como desvio do nominal da versão."""
    df = tele.copy()
    tipo = df["tipo"] if "tipo" in df else pd.Series("agulha", index=df.index)
    df["tipo_jacare"] = (tipo == "jacare").astype(int)
    df["tempo_rel"] = df["tempo_manobra_s"] - tipo.map(lambda t: C.VERSOES[t]["tempo_nominal"])
    df["forca_rel"] = df["forca_n"] - tipo.map(lambda t: C.VERSOES[t]["forca_nominal"])
    if "perda_correspondencia" not in df:
        df["perda_correspondencia"] = 0
    if "tempo_correspondencia_ms" not in df:
        df["tempo_correspondencia_ms"] = np.nan

    out = []
    for _, g in df.sort_values(["maquina_id", "ciclo"]).groupby("maquina_id"):
        g = g.copy()
        for col, nome in [("tempo_rel", "tempo"), ("forca_rel", "forca"), ("corrente_area", "area")]:
            r = g[col].rolling(C.JANELA, min_periods=3)
            g[f"{nome}_mm"] = r.mean()
            g[f"{nome}_dp"] = r.std().fillna(0)
        base = g.iloc[:C.JANELA]  # primeiras leituras do histórico = "normal" daquela máquina
        g["pico_rel"] = g["corrente_pico"] / base["corrente_pico"].median()
        g["tempo_vs_base"] = g["tempo_rel"] - base["tempo_rel"].median()
        g["forca_vs_base"] = g["forca_rel"] - base["forca_rel"].median()
        g["area_vs_base"] = g["corrente_area"] / base["corrente_area"].median() - 1
        g["patamar_vs_base"] = g["corrente_patamar"] - base["corrente_patamar"].median()
        g["rampa_vs_base"] = g["corrente_rampa"] - base["corrente_rampa"].median()
        g["tempo_vs_base_mm20"] = g["tempo_vs_base"].rolling(2 * C.JANELA, min_periods=3).mean()
        g["tempo_taxa"] = _inclinacao(g["tempo_rel"])
        g["area_taxa"] = _inclinacao(g["corrente_area"])
        g["pico_taxa"] = _inclinacao(g["pico_rel"])
        g["forca_taxa"] = _inclinacao(g["forca_rel"])
        corr = g["tempo_correspondencia_ms"]
        g["corresp_vs_base"] = corr - base["tempo_correspondencia_ms"].median()
        g["corresp_mm"] = g["corresp_vs_base"].rolling(C.JANELA, min_periods=3).mean()
        g["corresp_taxa"] = _inclinacao(corr.fillna(0))
        g["perda_corr_janela"] = g["perda_correspondencia"].rolling(C.JANELA, min_periods=1).sum()
        g["isolamento_taxa"] = g["isolamento_mohm"].rolling(C.JANELA, min_periods=3).apply(_slope, raw=True).fillna(0)
        queda = (-g["isolamento_taxa"]).clip(lower=1e-6)
        g["ciclos_ate_1mohm"] = ((g["isolamento_mohm"] - C.ISOLAMENTO_MIN) / queda).clip(0, 1000)
        out.append(g)
    return pd.concat(out, ignore_index=True)


def rotular(feat: pd.DataFrame, manut: pd.DataFrame) -> pd.DataFrame:
    """alvo = a falha acontece nos próximos HORIZONTE ciclos (a última leitura é a anterior à falha)."""
    m = manut.set_index("maquina_id")
    feat = feat.copy()
    feat["ciclo_falha"] = feat["maquina_id"].map(m["ciclo"])
    resta = feat["ciclo_falha"] - feat["ciclo"]
    feat["alvo"] = ((resta >= 1) & (resta <= C.HORIZONTE)).astype(int)
    feat["modo_alvo"] = feat["maquina_id"].map(m["modo_falha"])
    return feat
