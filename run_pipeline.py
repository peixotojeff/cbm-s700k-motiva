"""Executa os estágios 1–3 sobre a base simulada e grava os artefatos usados pelo serviço do modelo (ml/).

  python run_pipeline.py            # gera a base se raw_data/ estiver vazio
  python run_pipeline.py --regerar  # recria a base simulada (3.000 falhas + 1.500 saudáveis)
  python run_pipeline.py --empacotar  # e atualiza a cópia compactada versionada no Git (dados/)
"""
import json
import sys

from src import config as C
from src import db
from src.gerar_casos import gerar
from src.stage1_ingest import carregar_raw
from src.stage2_features import construir_features, features_modelo, rotular
from src.stage3_model import treinar

if __name__ == "__main__":
    if "--regerar" in sys.argv or not (C.RAW / "casos.csv").exists():
        print("Base simulada:", gerar(), "casos")
    tele, manut, casos, rel = carregar_raw()
    print("Estágio 1:", rel)
    feat = rotular(construir_features(tele), manut)
    print("Estágio 2:", len(feat), "leituras com features")
    _, met = treinar(feat, casos)
    chaves = ("taxa_antecipacao", "antecedencia_mediana_h", "taxa_alarme_falso", "trocas_status_saudaveis")
    print("Estágio 3: AUC %.3f, limiar %.3f, acerto do modo %.0f%%" % (met["auc"], met["limiar_vermelho"], 100 * met["acerto_modo_geral"]))
    print("   AVISO (amarelo ou vermelho):   ", {k: met[k] for k in chaves})
    print("   só vermelho (intervir):        ", {k: met["vermelho"][k] for k in chaves})
    print("   leitura isolada (comparação):", {k: met["sem_persistencia"][k] for k in chaves})
    print("   assertividade:                 ", met["assertividade"])
    for modo, m in met["por_modo"].items():
        print(f"   {modo:20s} antecipadas {m['antecipadas']:3d}/{m['casos']:3d} ({m['taxa']:.0%})  antecedência mediana {m['antecedencia_mediana_h']} h  acerto do modo {met['acerto_modo'].get(modo)}")

    # Cenário (HIPÓTESE): o que se ganharia monitorando o tempo de confirmação da correspondência. Não substitui o modelo.
    if not C.MONITORAR_CORRESPONDENCIA:
        _, cen = treinar(feat, casos, features=features_modelo(correspondencia=True), salvar=False)
        resumo = {k: cen[k] for k in ("taxa_antecipacao", "antecedencia_mediana_h", "taxa_alarme_falso", "falhas_antecipadas", "falhas_teste", "assertividade")}
        resumo["por_modo"] = {k: v["taxa"] for k, v in cen["por_modo"].items()}
        caminho = C.MODELS / "metricas.json"
        m = json.loads(caminho.read_text(encoding="utf-8"))
        m["cenario_correspondencia"] = resumo
        caminho.write_text(json.dumps(m, indent=2, ensure_ascii=False), encoding="utf-8")
        print("   cenário + correspondência (hipótese):", {k: resumo[k] for k in ("taxa_antecipacao", "taxa_alarme_falso")},
              "fuso", f"{resumo['por_modo'].get('fuso_retorno', 0):.0%}", "assertividade", resumo["assertividade"])

    db.construir(tele, feat, casos)
    print("SQLite:", db.DB_PATH, f"({db.DB_PATH.stat().st_size / 1e6:.0f} MB)")
    if "--empacotar" in sys.argv:  # atualiza a cópia versionada no Git (dados/)
        from src.pacote import empacotar
        empacotar()
