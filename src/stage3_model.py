"""Estágio 3: detector de falha (XGBoost), classificador do modo de falha, calibração do limiar e explicabilidade (TreeSHAP).

Dois níveis de aviso, ambos com persistência e histerese (padrão de alarmes industriais):
- VERMELHO "intervir": modelo de curto prazo (falha em até HORIZONTE ciclos ≈ 2,5 dias);
- AMARELO "programar inspeção": modelo de prazo longo (falha em até HORIZONTE_AVISO ≈ 7,5 dias)
  ou perda de correspondência recente (o único precursor do retorno do fuso).
Cada alarme LIGA com 2 das últimas 3 leituras acima do limiar (= a mediana das 3 passa) e DESLIGA só quando
as 3 últimas ficam abaixo de ALARME_SAIDA × limiar: uma oscilação isolada não vira alarme e o status não pisca.
"""
import json

import joblib
import numpy as np
import pandas as pd
import xgboost as xgb
from sklearn.metrics import roc_auc_score
from sklearn.model_selection import GroupShuffleSplit

from . import config as C
from .stage2_features import FEATURES


def features_de(modelo) -> list[str]:
    """Features com que o modelo foi treinado (o cenário com correspondência usa mais colunas)."""
    return list(getattr(modelo, "feature_names_in_", FEATURES))

NOMES = {
    "tempo_rel": "desvio do tempo de manobra", "forca_rel": "desvio da força de acionamento",
    "isolamento_mohm": "resistência de isolamento", "corrente_area": "área da curva de corrente",
    "corrente_pico": "pico de corrente", "pico_rel": "pico de corrente vs. referência",
    "corrente_patamar": "corrente durante o curso", "corrente_rampa": "subida de corrente no fim do curso",
    "tempo_mm": "tendência do tempo de manobra", "tempo_dp": "variabilidade do tempo",
    "forca_mm": "tendência da força", "forca_dp": "variabilidade da força",
    "area_mm": "tendência da área de corrente", "area_dp": "variabilidade da área de corrente",
    "isolamento_taxa": "taxa de queda do isolamento", "ciclos_ate_1mohm": "ciclos estimados até 1 MΩ",
    "perda_corr_janela": "perdas de correspondência recentes", "tipo_jacare": "versão jacaré móvel",
    "tempo_vs_base": "tempo de manobra vs. o normal da máquina", "forca_vs_base": "força vs. o normal da máquina",
    "area_vs_base": "área da corrente vs. o normal da máquina", "patamar_vs_base": "corrente do curso vs. o normal da máquina",
    "rampa_vs_base": "subida de corrente no fim do curso vs. o normal", "tempo_taxa": "tempo de manobra subindo",
    "area_taxa": "área da corrente subindo", "pico_taxa": "pico de corrente mudando", "forca_taxa": "força mudando",
    "tempo_vs_base_mm20": "tempo de manobra acima do normal (20 manobras)",
    "corresp_vs_base": "atraso na confirmação da correspondência", "corresp_mm": "atraso médio na confirmação da correspondência",
    "corresp_taxa": "confirmação da correspondência ficando mais lenta",
}
ANTECEDENCIA_MAX = C.ANTECEDENCIA_MAX
TENDENCIA = 12  # leituras de risco devolvidas por histórico (mini-linha do painel)


def persistencia(df: pd.DataFrame, p) -> pd.DataFrame:
    """Acrescenta por leitura: risco, risco_confirmado (2 de 3 = mediana das 3 últimas) e risco_max3. df ordenado por histórico e ciclo."""
    t = df[["maquina_id", "ciclo"]].assign(risco=np.asarray(p, dtype=float))
    g = t.groupby("maquina_id")["risco"]
    med3 = g.transform(lambda s: s.rolling(3, min_periods=3).median())
    min2 = g.transform(lambda s: s.rolling(2, min_periods=2).min())  # 2ª leitura: as duas precisam passar
    t["risco_confirmado"] = med3.fillna(min2).fillna(0.0)
    t["risco_max3"] = g.transform(lambda s: s.rolling(3, min_periods=1).max())
    return t


def _dias(ciclos: int) -> str:
    return f"{ciclos * C.INTERVALO_H / 24:.1f}".replace(".", ",")


def _histerese(t: pd.DataFrame, limiar: float) -> np.ndarray:
    """Alarme por leitura: liga com risco_confirmado ≥ limiar, desliga com risco_max3 < ALARME_SAIDA × limiar."""
    conf, max3 = t["risco_confirmado"].to_numpy(), t["risco_max3"].to_numpy()
    ids = t["maquina_id"].to_numpy()
    alarme = np.zeros(len(t), dtype=bool)
    ligado, anterior = False, None
    for i in range(len(t)):
        if ids[i] != anterior:  # novo histórico
            ligado, anterior = False, ids[i]
        if not ligado and conf[i] >= limiar:
            ligado = True
        elif ligado and max3[i] < C.ALARME_SAIDA * limiar:
            ligado = False
        alarme[i] = ligado
    return alarme


def estados(df: pd.DataFrame, p, limiar: float, p_aviso=None, limiar_aviso: float | None = None) -> pd.DataFrame:
    """Por leitura: risco, alarme (vermelho), amarelo, aviso (amarelo ou vermelho) e severidade. df ordenado por histórico e ciclo.

    Com p_aviso/limiar_aviso: amarelo = alarme do modelo de prazo longo ou perda de correspondência recente.
    Sem eles (compatibilidade): amarelo quando alguma das 3 últimas leituras passa de LIMIAR_AMARELO × limiar.
    """
    t = persistencia(df, p)
    t["alarme"] = _histerese(t, limiar)
    if p_aviso is not None:
        ta = persistencia(df, p_aviso)
        perda = (df["perda_corr_janela"].to_numpy() > 0) if "perda_corr_janela" in df else np.zeros(len(df), bool)
        t["risco_aviso"] = ta["risco"].to_numpy()
        amarelo = _histerese(ta, limiar_aviso) | perda
    else:
        amarelo = t["risco_max3"].to_numpy() >= C.LIMIAR_AMARELO * limiar
    t["amarelo"] = amarelo & ~t["alarme"].to_numpy()
    t["aviso"] = t["alarme"].to_numpy() | amarelo
    t["severidade"] = np.where(t["alarme"], "Vermelho", np.where(t["amarelo"], "Amarelo", "Verde"))
    return t


def _maximos(df: pd.DataFrame, score: np.ndarray):
    """Score máximo por histórico: nas falhas, só na janela antes da falha; nos saudáveis, em todo o histórico."""
    t = df.assign(s=score)
    resta = t["ciclo_falha"] - t["ciclo"]
    jan = t[(resta >= 1) & (resta <= ANTECEDENCIA_MAX)]
    return jan.groupby("maquina_id")["s"].max().to_numpy(), t[t["ciclo_falha"].isna()].groupby("maquina_id")["s"].max().to_numpy()


def _escolher_limiar(val: pd.DataFrame, score: np.ndarray, teto: float | None = None) -> float:
    """Maior antecipação de falhas com, no máximo, `teto` (padrão ALARME_FALSO_MAX) dos históricos saudáveis alarmando."""
    teto = C.ALARME_FALSO_MAX if teto is None else teto
    falhas, saudaveis = _maximos(val, score)
    candidatos = np.unique(np.concatenate([falhas, saudaveis]))
    falso = (saudaveis[None, :] >= candidatos[:, None]).mean(axis=1)
    antecipa = (falhas[None, :] >= candidatos[:, None]).mean(axis=1)
    ok = falso <= teto
    if not ok.any():
        return float(candidatos.max())
    melhor = antecipa[ok].max()
    return float(candidatos[ok & (antecipa == melhor)].max())


def assertividade(df: pd.DataFrame, es: pd.DataFrame) -> dict:
    """Placar por histórico, como na Simulação ao vivo: acertos = falhas avisadas (amarelo ou vermelho) até ANTECEDENCIA_MAX;
    erros = falhas sem aviso + saudáveis avisados. 'intervencao' conta como falso só o saudável que chegou a vermelho:
    amarelo é "programar inspeção", e a inspeção que não acha defeito é verificação, não intervenção errada."""
    t = df[["maquina_id", "ciclo", "ciclo_falha"]].assign(aviso=es["aviso"].to_numpy(), alarme=es["alarme"].to_numpy())
    resta = t["ciclo_falha"] - t["ciclo"]
    f = t[t["ciclo_falha"].notna()]
    jan = f[(resta.loc[f.index] >= 1) & (resta.loc[f.index] <= ANTECEDENCIA_MAX)]
    avisadas = jan.groupby("maquina_id")["aviso"].any().reindex(f["maquina_id"].unique(), fill_value=False)
    s = t[t["ciclo_falha"].isna()].groupby("maquina_id")
    ac, sem = int(avisadas.sum()), int((~avisadas).sum())
    fa, fv = int(s["aviso"].any().sum()), int(s["alarme"].any().sum())
    return {"acertos": ac, "sem_aviso": sem, "falsos_aviso": fa, "falsos_vermelho": fv,
            "geral": round(ac / max(1, ac + sem + fa), 3), "intervencao": round(ac / max(1, ac + sem + fv), 3)}


def _calibrar_assertividade(dv: pd.DataFrame, p_curto, p_longo, piso_acertos: int) -> tuple[float, float]:
    """Par de limiares (vermelho, amarelo) com maior assertividade na validação, sem avisar menos falhas que `piso_acertos`."""
    melhor, par = -1.0, None
    for lv in np.quantile(p_curto, np.linspace(0.80, 0.999, C.GRADE_CALIBRACAO)):
        for la in np.quantile(p_longo, np.linspace(0.80, 0.999, C.GRADE_CALIBRACAO)):
            a = assertividade(dv, estados(dv, p_curto, lv, p_longo, la))
            if a["acertos"] >= piso_acertos and a["geral"] > melhor:
                melhor, par = a["geral"], (float(lv), float(la))
    return par


def _por_caso(teste: pd.DataFrame, alarme: np.ndarray) -> dict:
    """Desempenho por caso: falhas antecipadas (e com quantas horas) por modo, alarmes falsos e trocas de status em saudáveis."""
    t = teste.assign(alarme=np.asarray(alarme, dtype=bool))
    resta = t["ciclo_falha"] - t["ciclo"]
    modos, horas_todas = {}, []
    falhas = t[t["ciclo_falha"].notna()]
    for modo, g in falhas.groupby("modo_alvo"):
        janela = g[(resta.loc[g.index] >= 1) & (resta.loc[g.index] <= ANTECEDENCIA_MAX)]
        primeiro = janela[janela["alarme"]].groupby("maquina_id")["ciclo"].min()
        n = g["maquina_id"].nunique()
        horas = (g.groupby("maquina_id")["ciclo_falha"].first().loc[primeiro.index] - primeiro) * C.INTERVALO_H
        horas_todas += horas.tolist()
        modos[modo] = {"casos": int(n), "antecipadas": int(len(primeiro)), "taxa": round(len(primeiro) / n, 3),
                       "antecedencia_mediana_h": None if horas.empty else float(horas.median())}
    saudaveis = t[t["ciclo_falha"].isna()]
    alarmados = saudaveis.groupby("maquina_id")["alarme"].any()
    # oscilação: quantas vezes o alarme liga/desliga por histórico saudável
    trocas = saudaveis.groupby("maquina_id")["alarme"].apply(lambda s: int((s.astype(int).diff().abs() == 1).sum()))
    tot = sum(m["casos"] for m in modos.values())
    ant = sum(m["antecipadas"] for m in modos.values())
    return {"por_modo": modos, "falhas_teste": tot, "falhas_antecipadas": ant, "taxa_antecipacao": round(ant / max(1, tot), 3),
            "antecedencia_mediana_h": float(np.median(horas_todas)) if horas_todas else None,
            "saudaveis_teste": int(len(alarmados)), "saudaveis_com_alarme": int(alarmados.sum()),
            "taxa_alarme_falso": round(float(alarmados.mean()), 3) if len(alarmados) else 0.0,
            "trocas_status_saudaveis": int(trocas.sum())}


def _folga(teste: pd.DataFrame, es: pd.DataFrame) -> dict:
    """Folga entre o 1º amarelo e o 1º vermelho (ou a falha, se o vermelho não veio), nas falhas que tiveram aviso."""
    t = teste.assign(aviso=es["aviso"].to_numpy(), alarme=es["alarme"].to_numpy())
    resta = t["ciclo_falha"] - t["ciclo"]
    f = t[t["ciclo_falha"].notna() & (resta >= 1) & (resta <= ANTECEDENCIA_MAX)]
    prim_av = f[f["aviso"]].groupby("maquina_id")["ciclo"].min()
    if prim_av.empty:
        return {"folga_amarelo_vermelho_h": None, "avisos_com_folga_reposicao": 0.0}
    prim_v = f[f["alarme"]].groupby("maquina_id")["ciclo"].min().reindex(prim_av.index)
    cf = f.groupby("maquina_id")["ciclo_falha"].first().loc[prim_av.index]
    folga = (prim_v.fillna(cf) - prim_av) * C.INTERVALO_H
    return {"folga_amarelo_vermelho_h": float(folga.median()),
            "avisos_com_folga_reposicao": round(float((folga >= C.DIAS_REPOSICAO * 24).mean()), 3),
            "dias_reposicao": C.DIAS_REPOSICAO}


def treinar(feat: pd.DataFrame, casos: pd.DataFrame | None = None, features: list[str] | None = None, salvar: bool = True):
    FEATURES = features or globals()["FEATURES"]  # noqa: N806
    d = feat.dropna(subset=FEATURES).sort_values(["maquina_id", "ciclo"]).reset_index(drop=True)
    # treino / validação (calibra o limiar) / teste (só para as métricas), sempre separando históricos inteiros
    resto, te = next(GroupShuffleSplit(test_size=0.3, random_state=0).split(d, groups=d["maquina_id"]))
    r = d.iloc[resto]
    fit, val = next(GroupShuffleSplit(test_size=0.25, random_state=1).split(r, groups=r["maquina_id"]))
    tr = resto[fit]
    Xtr, ytr = d.iloc[tr][FEATURES], d.iloc[tr]["alvo"]
    pos = max(1, int(ytr.sum()))
    modelo = xgb.XGBClassifier(
        n_estimators=300, max_depth=4, learning_rate=0.08, subsample=0.9,
        scale_pos_weight=(len(ytr) - pos) / pos, eval_metric="aucpr", random_state=0,
    ).fit(Xtr, ytr)

    # 2º modelo, de prazo longo: aprende os sinais mais precoces (falha em até HORIZONTE_AVISO ciclos) → aviso amarelo
    resta = d["ciclo_falha"] - d["ciclo"]
    alvo_aviso = ((resta >= 1) & (resta <= C.HORIZONTE_AVISO)).astype(int)
    ya = alvo_aviso.iloc[tr]
    pos_a = max(1, int(ya.sum()))
    modelo_aviso = xgb.XGBClassifier(
        n_estimators=300, max_depth=4, learning_rate=0.08, subsample=0.9,
        scale_pos_weight=(len(ya) - pos_a) / pos_a, eval_metric="aucpr", random_state=0,
    ).fit(Xtr, ya)

    dv, dt = r.iloc[val].sort_values(["maquina_id", "ciclo"]), d.iloc[np.sort(te)]
    pv, pt = persistencia(dv, modelo.predict_proba(dv[FEATURES])[:, 1]), persistencia(dt, modelo.predict_proba(dt[FEATURES])[:, 1])
    limiar = _escolher_limiar(dv, pv["risco_confirmado"].to_numpy())     # com persistência (padrão)
    limiar_simples = _escolher_limiar(dv, pv["risco"].to_numpy())        # leitura isolada (comparação)
    pv_aviso = modelo_aviso.predict_proba(dv[FEATURES])[:, 1]
    pav = persistencia(dv, pv_aviso)
    limiar_aviso = _escolher_limiar(dv, pav["risco_confirmado"].to_numpy(), teto=C.ALARME_FALSO_AVISO)
    pt_aviso = modelo_aviso.predict_proba(dt[FEATURES])[:, 1]
    # Calibração final: maior assertividade na validação, sem avisar menos falhas que a calibração por teto de falso.
    pv_risco = pv["risco"].to_numpy()
    teto_val = assertividade(dv, estados(dv, pv_risco, limiar, pv_aviso, limiar_aviso))
    par = _calibrar_assertividade(dv, pv_risco, pv_aviso, int(np.floor(teto_val["acertos"] * (1 - C.TOLERANCIA_ACERTOS))))
    if par:
        limiar, limiar_aviso = par

    # modo de falha: aprendido nas leituras que antecedem falhas (rótulos da base simulada)
    classes = sorted(d.loc[d["alvo"] == 1, "modo_alvo"].dropna().unique())
    pos_tr = d.iloc[tr][d.iloc[tr]["alvo"] == 1]
    pos_te = dt[dt["alvo"] == 1]
    modelo_modo = xgb.XGBClassifier(n_estimators=200, max_depth=4, learning_rate=0.1, random_state=0, eval_metric="mlogloss")
    modelo_modo.fit(pos_tr[FEATURES], pos_tr["modo_alvo"].map(classes.index))
    acerto = (np.array(classes)[modelo_modo.predict(pos_te[FEATURES])] == pos_te["modo_alvo"].to_numpy())
    acerto_modo = pd.Series(acerto, index=pos_te["modo_alvo"]).groupby(level=0).mean().round(3).to_dict()

    es = estados(dt, pt["risco"].to_numpy(), limiar, pt_aviso, limiar_aviso)
    aviso = _por_caso(dt, es["aviso"].to_numpy())                  # amarelo ou vermelho: a equipe já foi avisada
    vermelho = _por_caso(dt, es["alarme"].to_numpy())              # só o vermelho ("intervir")
    sem = _por_caso(dt, pt["risco"].to_numpy() >= limiar_simples)  # vermelho por leitura isolada, sem histerese
    chaves = ("taxa_antecipacao", "falhas_antecipadas", "antecedencia_mediana_h", "taxa_alarme_falso", "saudaveis_com_alarme", "trocas_status_saudaveis")
    metricas = {
        "auc": float(roc_auc_score(dt["alvo"], pt["risco"])), "limiar_vermelho": limiar,
        "limiar_amarelo": limiar_aviso, "horizonte_aviso_ciclos": C.HORIZONTE_AVISO, "alarme_falso_aviso_max": C.ALARME_FALSO_AVISO,
        "regra_persistencia": f"Liga com 2 das últimas 3 leituras acima do limiar; desliga quando as 3 ficam abaixo de {C.ALARME_SAIDA:.0%} dele",
        "regra_niveis": (f"Vermelho (intervir): risco de falha em até {_dias(C.HORIZONTE)} dias. "
                         f"Amarelo (programar inspeção): risco em até {_dias(C.HORIZONTE_AVISO)} dias ou perda de correspondência recente."),
        "alarme_falso_max": C.ALARME_FALSO_MAX, "antecedencia_max_h": ANTECEDENCIA_MAX * C.INTERVALO_H,
        "horizonte_ciclos": C.HORIZONTE, "intervalo_h": C.INTERVALO_H,
        "historicos_treino": int(d.iloc[tr]["maquina_id"].nunique()), "historicos_teste": int(dt["maquina_id"].nunique()),
        "acerto_modo": acerto_modo, "acerto_modo_geral": round(float(acerto.mean()), 3),
        **aviso,  # métricas principais: falha com aviso (amarelo ou vermelho)
        **_folga(dt, es),
        "assertividade": assertividade(dt, es),
        "calibracao": ("Limiares escolhidos na validação para a maior assertividade, avisando ao menos "
                       f"{1 - C.TOLERANCIA_ACERTOS:.0%} das falhas que a calibração por teto de alarme falso avisa "
                       f"({C.ALARME_FALSO_MAX:.0%} vermelho, {C.ALARME_FALSO_AVISO:.0%} amarelo)"),
        "tolerancia_acertos": C.TOLERANCIA_ACERTOS,
        "teto_aviso_falso": {"vermelho": C.ALARME_FALSO_MAX, "amarelo": C.ALARME_FALSO_AVISO},
        "saida_histerese": C.ALARME_SAIDA, "limiar_amarelo_valor": limiar_aviso,
        "vermelho": {**{k: vermelho[k] for k in chaves}, "por_modo": {k: v["taxa"] for k, v in vermelho["por_modo"].items()}},
        "sem_persistencia": {"limiar_vermelho": limiar_simples, **{k: sem[k] for k in chaves}},
    }
    metricas["features"] = FEATURES
    if not salvar:
        return modelo, metricas
    C.MODELS.mkdir(exist_ok=True)
    joblib.dump(modelo, C.MODELS / "modelo.joblib")
    joblib.dump(modelo_aviso, C.MODELS / "modelo_aviso.joblib")
    joblib.dump({"modelo": modelo_modo, "classes": classes}, C.MODELS / "modelo_modo.joblib")
    (C.MODELS / "metricas.json").write_text(json.dumps(metricas, indent=2, ensure_ascii=False), encoding="utf-8")
    # históricos do conjunto de teste (nunca vistos no treino), para a simulação ao vivo mostrar só eles
    (C.MODELS / "teste_ids.json").write_text(json.dumps(sorted(dt["maquina_id"].unique().tolist())), encoding="utf-8")
    return modelo, metricas


def ids_teste() -> set[str]:
    p = C.MODELS / "teste_ids.json"
    return set(json.loads(p.read_text(encoding="utf-8"))) if p.exists() else set()


def carregar_modelo():
    return joblib.load(C.MODELS / "modelo.joblib"), json.loads((C.MODELS / "metricas.json").read_text(encoding="utf-8"))


def carregar_modelo_modo():
    p = C.MODELS / "modelo_modo.joblib"
    return joblib.load(p) if p.exists() else None


def carregar_modelo_aviso():
    p = C.MODELS / "modelo_aviso.joblib"
    return joblib.load(p) if p.exists() else None


def pontuar(modelo, limiar: float, feat: pd.DataFrame, modo=None, aviso=None) -> pd.DataFrame:
    """Última leitura de cada histórico: risco, severidade (com persistência), tendência, fatores (SHAP) e modo provável.

    aviso = (modelo_aviso, limiar_amarelo) ativa o amarelo do modelo de prazo longo.
    """
    FEATURES = features_de(modelo)  # noqa: N806
    base = feat.dropna(subset=FEATURES).sort_values(["maquina_id", "ciclo"]).reset_index(drop=True)
    if base.empty:  # menos leituras que a janela mínima
        return base.assign(risco=[], severidade=[], fatores=[], modo_provavel=[], tendencia=[])
    if "risco" not in base:  # o serviço pode trazer o risco pré-calculado
        base = base.assign(risco=modelo.predict_proba(base[FEATURES])[:, 1])
    if "severidade" not in base:  # o estado só depende das leituras anteriores: pode vir pré-calculado para todas
        p_aviso = aviso[0].predict_proba(base[FEATURES])[:, 1] if aviso else None
        es = estados(base, base["risco"].to_numpy(), limiar, p_aviso, aviso[1] if aviso else None)
        base = base.assign(alarme=es["alarme"].to_numpy(), aviso=es["aviso"].to_numpy(), severidade=es["severidade"].to_numpy())
    cauda = base.groupby("maquina_id").tail(TENDENCIA)
    tendencia = pd.Series({k: np.round(v, 3).tolist() for k, v in cauda.groupby("maquina_id")["risco"]})

    ult = base.groupby("maquina_id").tail(1).reset_index(drop=True)
    ult["tendencia"] = ult["maquina_id"].map(tendencia)
    X = ult[FEATURES]
    contrib = modelo.get_booster().predict(xgb.DMatrix(X), pred_contribs=True)[:, :-1]
    fatores = []
    for i in range(len(ult)):
        top = np.argsort(-contrib[i])[:3]
        fatores.append("; ".join(f"{NOMES[FEATURES[j]]} (+{contrib[i][j]:.2f})" for j in top if contrib[i][j] > 0))
    ult["fatores"] = fatores
    if modo:
        ult["modo_provavel"] = np.array(modo["classes"])[modo["modelo"].predict(X)]
        ult.loc[ult["severidade"] == "Verde", "modo_provavel"] = "nenhuma"
    else:
        ult["modo_provavel"] = "nenhuma"
    return ult
