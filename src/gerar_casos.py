"""Base simulada de casos (mockup para a apresentação), calibrada nos 16 casos reais de 2016–2017.

Gera N_FALHAS históricos que terminam numa falha e N_SAUDAVEIS históricos sem falha. Cada histórico tem uma
leitura a cada 6 h com tempo de manobra, força, isolamento, parâmetros da curva de corrente e a ocorrência
de perda de correspondência.

Calibração (web/database/data/eventos_falhas_reais.csv):
- Mistura de modos dos 10 casos reais com causa: barras/folga 4, atrito/travamento/fim de curso 4 (inclui o
  caso 4, sem causa mas com curva típica), retorno do fuso 3. Os modos do desafio que não aparecem nos dados
  reais (motor, embreagem, talonada) entram com 6% cada, com assinatura do manual Siemens (seção 11).
- Retorno do fuso NÃO altera corrente nem tempo (casos 8, 9 e 16); às vezes há perda de correspondência antes.
- Corrente na escala real (patamar ~1,3 A; fim de curso até ~1,65 A no atrito); 13 de 15 máquinas eram jacaré.
- Máquinas físicas se repetem entre casos (nos reais, a 4123 JM falhou 4 vezes).
Saídas em raw_data/: telemetria.csv, manutencao.csv, casos.csv.
"""
import numpy as np
import pandas as pd

from . import config as C
from .curva import area

# modo: (peso, ids dos casos reais que o inspiram, categoria no padrão dos casos reais)
MISTURA = {
    "atrito_travamento": (0.30, [4, 10, 11, 13], "Atrito / travamento no fim de curso"),
    "barras_folga": (0.29, [7, 12, 14, 15], "Barras de detecção / folga mecânica"),
    "fuso_retorno": (0.23, [8, 9, 16], "Retorno do fuso após travamento"),
    "motor_nao_aciona": (0.06, [], "Motor não aciona (manual Siemens 11.2)"),
    "embreagem_patinando": (0.06, [], "Embreagem patinando (manual Siemens 11.4)"),
    "talonada": (0.06, [], "Máquina talonada (manual Siemens 11.5)"),
}
TEXTO_MANUAL = {
    "motor_nao_aciona": ("Motor não inicia a movimentação; corrente de partida em queda", "Verificar seccionadora, cabos, motor e comando do intertravamento"),
    "embreagem_patinando": ("Força de manobra abaixo do especificado; embreagem patinando", "Verificar peças de transmissão e embreagem; medir força (Meks-10)"),
    "talonada": ("Trem forçou a agulha no sentido convergente; força e corrente elevadas", "Inspecionar e revisar a máquina antes de voltar a operar"),
}
REAIS = ["MCH 4082 JM", "MCH 4083 JM", "MCH 4123 JM", "MCH 4124 JM", "MCH 4132 JM", "MCH 4134 JM", "MCH 4132 AG", "MCH 4123 AG"]
LOCAIS = ["X03", "X04", "X12", "X13", "X02", "X05", "X06", "X07", "X08", "X09", "X10", "X11"]


def _frota(rng, n=80):
    """Máquinas físicas: as 8 dos casos reais + outras, 87% jacaré móvel."""
    outras = set()
    while len(outras) < n - len(REAIS):
        sufixo = "JM" if rng.random() < 0.87 else "AG"
        outras.add(f"MCH {rng.integers(4001, 4200)} {sufixo}")
    codigos = REAIS + sorted(outras - set(REAIS))
    return pd.DataFrame({"maquina": codigos, "local": rng.choice(LOCAIS, len(codigos)),
                         "tipo": ["jacare" if c.endswith("JM") else "agulha" for c in codigos]})


def _assinatura(s: dict, modo, G, g, rng):
    """Aplica em `s` (arrays por ciclo) a assinatura de um modo; G = severidade × progresso da degradação."""
    if modo == "atrito_travamento":
        s["tempo"] += 0.8 * G; s["rampa"] += 0.35 * G; s["forca"] += 300 * G; s["pico"] *= 1 + 0.05 * G
    elif modo == "barras_folga":
        s["tempo"] += 0.9 * G; s["patamar"] += 0.05 * G
        s["perda"] |= (rng.random(len(G)) < 0.12 * g).astype(int)  # perdas intermitentes de correspondência
    elif modo == "motor_nao_aciona":
        s["pico"] *= 1 - 0.45 * G; s["patamar"] += 0.05 * G; s["iso"] -= np.cumsum(0.04 * G)
    elif modo == "embreagem_patinando":
        s["forca"] -= 1400 * G; s["tempo"] += 0.4 * G; s["patamar"] -= 0.1 * G
    elif modo == "talonada":
        s["forca"] += 1100 * G; s["pico"] *= 1 + 0.4 * G; s["tempo"] += 0.5 * G


def _historico(rng, tipo, modo, n, amp, onset):
    """Arrays por ciclo (0..n-1). A falha acontece no ciclo n (depois da última leitura)."""
    c = np.arange(n)
    g = np.clip((c - onset) / max(1, n - onset), 0, 1) if modo else np.zeros(n)
    v = C.VERSOES[tipo]
    s = {
        "tempo": v["tempo_nominal"] + rng.normal(0, 0.06) + rng.normal(0, 0.05, n),
        "forca": v["forca_nominal"] + rng.normal(0, 120) + rng.normal(0, 70, n),
        "pico": rng.uniform(3.4, 4.6) * (1 + rng.normal(0, 0.03, n)),
        "patamar": rng.uniform(1.2, 1.4) + rng.normal(0, 0.025, n),
        "rampa": np.abs(rng.normal(0, 0.015, n)),
        "iso": rng.uniform(8, 12) - np.cumsum(rng.uniform(0.004, 0.015, n)) + rng.normal(0, 0.05, n),
        "perda": np.zeros(n, dtype=int),
        # HIPÓTESE a validar com a Motiva: tempo entre o fim do curso e a confirmação da posição pela microswitch
        # (contatos de detecção). Só entra no modelo com MONITORAR_CORRESPONDENCIA = True.
        "corresp": rng.uniform(120, 180) + rng.normal(0, 12, n),
    }
    if modo == "fuso_retorno":
        if rng.random() < 0.6:  # precursor em 60% dos casos; no resto a falha é invisível (casos 8, 9 e 16)
            s["perda"][rng.choice(np.arange(max(0, n - 20), n), rng.integers(1, 3), replace=False)] = 1
        if rng.random() < 0.8:  # retorno do fuso altera a microswitch (casos 8 e 9): a confirmação atrasa
            s["corresp"] += 250 * amp * g
    elif modo == "barras_folga":
        _assinatura(s, modo, amp * g, g, rng)
        s["corresp"] += 80 * amp * g  # folga nas barras também atrasa a detecção
    elif modo:
        _assinatura(s, modo, amp * g, g, rng)
    if modo and rng.random() < 0.3:  # sinais misturados: segunda assinatura mais fraca de outro modo
        outro = rng.choice([m for m in MISTURA if m not in (modo, "fuso_retorno")])
        _assinatura(s, outro, amp * rng.uniform(0.2, 0.5) * g, g, rng)
    if not modo:  # ruído operacional benigno em máquinas saudáveis (calor, ajuste, falha de comunicação)
        if rng.random() < 0.3:
            ini = int(rng.integers(0, n - 10))
            s["tempo"][ini:ini + 10] += rng.uniform(0.1, 0.3)
        if rng.random() < 0.1:
            s["perda"][rng.integers(0, n)] = 1
        if rng.random() < 0.15:  # atraso transitório da confirmação (sujeira, temperatura)
            ini = int(rng.integers(0, n - 8))
            s["corresp"][ini:ini + 8] += rng.uniform(40, 120)

    return dict(tempo_manobra_s=s["tempo"], forca_n=s["forca"], isolamento_mohm=np.clip(s["iso"], 0.2, None),
                corrente_pico=s["pico"], corrente_patamar=s["patamar"], corrente_rampa=s["rampa"],
                corrente_area=area(s["tempo"], s["pico"], s["patamar"], s["rampa"]), perda_correspondencia=s["perda"],
                tempo_correspondencia_ms=s["corresp"])


def gerar(n_falhas=C.N_FALHAS, n_saudaveis=C.N_SAUDAVEIS, n_ciclos=C.N_CICLOS, seed=7):
    rng = np.random.default_rng(seed)
    reais = pd.read_csv(C.EVENTOS_REAIS).set_index("id")
    frota = _frota(rng)
    modos = list(MISTURA)
    pesos = np.array([MISTURA[m][0] for m in modos])
    escolhidos = rng.choice(modos, n_falhas, p=pesos / pesos.sum()).tolist() + [None] * n_saudaveis
    periodo = pd.date_range("2016-01-01", "2017-12-31", freq="D")

    tele, manut, casos = [], [], []
    for k, modo in enumerate(escolhidos):
        cid = f"C{k + 1:04d}"
        maq = frota.iloc[rng.integers(len(frota))]
        n = int(rng.integers(70, n_ciclos)) if modo else n_ciclos
        dur = int(rng.integers(15, 50))                          # ciclos de degradação antes da falha
        amp = float(rng.uniform(0.2, 1.0))                       # severidade (0,2 = sutil)
        h = _historico(rng, maq["tipo"], modo, n, amp, n - dur)
        fim = pd.Timestamp(rng.choice(periodo)) + pd.Timedelta(hours=int(rng.integers(0, 24)))
        ts = fim - pd.to_timedelta((n - np.arange(n)) * C.INTERVALO_H, unit="h")
        tele.append(pd.DataFrame({"maquina_id": cid, "maquina": maq["maquina"], "tipo": maq["tipo"], "ciclo": np.arange(n), "timestamp": ts, **h}))

        base = {"caso_id": cid, "maquina": maq["maquina"], "tipo": maq["tipo"], "local": maq["local"], "linha": "Linha 4 - Amarela"}
        if modo is None:
            casos.append(base | {"desfecho": "saudável", "modo": "nenhuma", "categoria": "Sem falha no período", "data_falha": None,
                                 "anomalia_corrente": "n/a", "severidade": None, "ciclos_degradacao": None, "precursor": None,
                                 "evento_base": None, "sintoma": "", "causa_acao": ""})
            continue
        ids = MISTURA[modo][1]
        ev = int(rng.choice(ids)) if ids else None
        sintoma, causa = (reais.loc[ev, "sintoma"], reais.loc[ev, "causa_acao"]) if ev else TEXTO_MANUAL[modo]
        anomalia = "Não" if modo == "fuso_retorno" else ("Sim" if amp >= 0.6 else "Pequena")
        manut.append((cid, fim, modo, n))
        casos.append(base | {"desfecho": "falha", "modo": modo, "categoria": MISTURA[modo][2], "data_falha": fim,
                             "anomalia_corrente": anomalia, "severidade": round(amp, 2), "ciclos_degradacao": dur,
                             "precursor": bool(h["perda_correspondencia"].any()), "evento_base": ev,
                             "sintoma": sintoma, "causa_acao": causa})

    tdf = pd.concat(tele, ignore_index=True)
    # ruído de coleta: alguns nulos e reenvios duplicados (tratados no Estágio 1)
    tdf.loc[rng.choice(len(tdf), len(tdf) // 500, replace=False), "forca_n"] = np.nan
    tdf = pd.concat([tdf, tdf.sample(50, random_state=1)], ignore_index=True)
    C.RAW.mkdir(exist_ok=True)
    tdf.to_csv(C.RAW / "telemetria.csv", index=False)
    pd.DataFrame(manut, columns=["maquina_id", "timestamp", "modo_falha", "ciclo"]).to_csv(C.RAW / "manutencao.csv", index=False)
    pd.DataFrame(casos).to_csv(C.RAW / "casos.csv", index=False)
    return len(casos)


if __name__ == "__main__":
    print(gerar(), "casos gerados em", C.RAW)
