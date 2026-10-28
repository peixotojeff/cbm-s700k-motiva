"""Parâmetros nominais do Siemens S700K e constantes do pipeline do modelo."""
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
RAW = ROOT / "raw_data"
DATA = ROOT / "data"
MODELS = ROOT / "models"
EVENTOS_REAIS = ROOT / "web" / "database" / "data" / "eventos_falhas_reais.csv"  # 16 casos reais (2016–2017)

# Versões do S700K (manual Siemens 26330-OM-15, seção 4.1). Tolerância de tempo é premissa do projeto.
VERSOES = {
    "agulha": {"tempo_nominal": 6.0, "forca_nominal": 5500.0},
    "jacare": {"tempo_nominal": 6.5, "forca_nominal": 6000.0},
}
FORCA_TOL = 500.0                         # N
TEMPO_TOL = 0.5                           # s
ISOLAMENTO_MIN = 1.0                      # MΩ

# Base simulada (calibrada nos 16 casos reais de 2016–2017)
N_FALHAS = 3000
N_SAUDAVEIS = 1500
N_CICLOS = 120                            # leituras por histórico (uma a cada 6 h ≈ 30 dias)
INTERVALO_H = 6

# Cenário: monitorar o tempo de confirmação da correspondência (microswitch). HIPÓTESE — ainda não medido na Linha 4.
MONITORAR_CORRESPONDENCIA = False

# Engenharia de features (Estágio 2)
JANELA = 10                               # últimas N manobras

# Modelagem (Estágio 3)
HORIZONTE = 10                            # falha nos próximos N ciclos (≈ 2,5 dias)
ALARME_FALSO_MAX = 0.10                   # limiar: máx. de históricos saudáveis com algum alarme (validação)
ANTECEDENCIA_MAX = 30                     # ciclos antes da falha em que um alarme conta como antecipação (≈ 7,5 dias)
LIMIAR_AMARELO = 0.25                     # fração do limiar vermelho
ALARME_SAIDA = 0.60                       # histerese: o vermelho só desliga com as 3 últimas leituras abaixo de 60% do limiar
# Aviso AMARELO ("programar inspeção"): vem de um 2º modelo, treinado para falha em até HORIZONTE_AVISO ciclos,
# ou de perda de correspondência recente. Inspeção programada custa menos que intervir: teto maior de aviso falso.
HORIZONTE_AVISO = 30                      # ≈ 7,5 dias
ALARME_FALSO_AVISO = 0.20
# Calibração final dos dois limiares: grade (n × n quantis) que maximiza a assertividade na validação.
GRADE_CALIBRACAO = 18
# Trava de segurança: o par escolhido não pode avisar menos que (1 − tolerância) das falhas que a regra por teto avisa
# na validação. Sem tolerância, o ruído de ~300 falhas de validação força limiares baixos e infla o aviso falso.
TOLERANCIA_ACERTOS = 0.01
# Prazo de reposição de peças: mede quantos avisos amarelos dão essa folga até o vermelho (planejamento de estoque).
# Na base simulada a degradação começa ~8 dias antes da falha; forçar o amarelo para 5 dias antes do vermelho só
# aumentaria os alarmes falsos (experimento de 2026-10-27: folga máx. ~54 h com 31% de aviso falso).
DIAS_REPOSICAO = 5

MODOS_FALHA = {
    "atrito_travamento": "Atrito / travamento no fim de curso",
    "barras_folga": "Barras de detecção / folga mecânica",
    "fuso_retorno": "Retorno do fuso após travamento",
    "motor_nao_aciona": "Motor não aciona",
    "embreagem_patinando": "Embreagem patinando",
    "talonada": "Máquina talonada",
    "nenhuma": "Sem anomalia dominante",
}

ACOES = {
    "atrito_travamento": "Lubrificar placas roletadas e barras; verificar obstáculo entre lâmina e trilho e o fim de curso (casos 4, 10, 11 e 13).",
    "barras_folga": "Regular as barras de detecção; verificar pino e bucha excêntrica (casos 7, 12, 14 e 15).",
    "fuso_retorno": "Verificar retorno do fuso, status da microswitch e desgaste de pino/roletes (casos 8, 9 e 16).",
    "motor_nao_aciona": "Inspecionar seccionadora, cabos, motor e comando do intertravamento (manual Siemens, caso 11.2).",
    "embreagem_patinando": "Inspecionar peças de transmissão e a embreagem; medir força de manobra (manual Siemens, caso 11.4).",
    "talonada": "Inspecionar e revisar a máquina antes de voltar à operação (manual Siemens, caso 11.5).",
    "nenhuma": "Manter plano de manutenção preventiva padrão.",
}
