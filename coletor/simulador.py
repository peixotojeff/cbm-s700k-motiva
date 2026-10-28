"""Gera curvas de corrente sintéticas de uma máquina S700K com degradação progressiva.

Forma inspirada nos gráficos reais de 2017 (corrente de partida, patamar ~1,3 A durante o curso).
Os números são ilustrativos: substituir por curvas reais quando a Motiva disponibilizar.
"""
import numpy as np

AMOSTRAGEM_S = 0.05
TEMPO_NOMINAL = {"agulha": 6.0, "jacare": 6.5}  # manual Siemens 26330-OM-15, seção 4.1

CENARIOS = {
    "normal": "Máquina saudável: tempo e corrente estáveis.",
    "atrito": "Atrito/lubrificação nas barras: tempo de manobra e corrente no fim do curso sobem (como o caso da MCH 4123 JM em 2017).",
    "motor": "Degradação do motor/alimentação: corrente de pico de partida cai.",
}


def curva(tipo: str, cenario: str, progresso: float, rng: np.random.Generator):
    """Curva de uma manobra. `progresso` vai de 0 (início) a 1 (degradação máxima)."""
    if cenario not in CENARIOS:
        raise ValueError(f"cenário desconhecido: {cenario} (use {', '.join(CENARIOS)})")
    p = float(np.clip(progresso, 0, 1))
    dur = TEMPO_NOMINAL[tipo] + rng.normal(0, 0.03)
    pico, patamar, rampa_fim = 4.0, 1.3, 0.0
    if cenario == "atrito":
        dur += 0.7 * p          # cruza a tolerância de +0,5 s perto do fim
        rampa_fim = 0.35 * p    # corrente sobe no fim do curso (1,3 → ~1,65 A)
    elif cenario == "motor":
        pico *= 1 - 0.3 * p     # cai >20% da referência perto do fim

    t = np.arange(0, dur + 0.6, AMOSTRAGEM_S)
    i = np.zeros_like(t)
    m = (t >= 0.2) & (t <= 0.2 + dur)
    tm = t[m] - 0.2
    partida = (pico - patamar) * np.exp(-tm / 0.15)               # pico de partida decaindo
    fim = rampa_fim * np.clip((tm - 0.7 * dur) / (0.3 * dur), 0, 1)  # subida nos 30% finais
    i[m] = patamar + partida + fim + rng.normal(0, 0.02, m.sum())
    return t, np.clip(i, 0, None)
