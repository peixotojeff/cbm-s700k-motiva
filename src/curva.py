"""Forma da curva de corrente de uma manobra do S700K, a partir de quatro parâmetros.

Inspirada nos gráficos reais de 2017: pico de partida que decai em ~0,15 s para um patamar (~1,3 A)
e, quando há atrito no fim do curso, uma subida nos últimos 30% (caso 4: 1,3 → ~1,65 A).
Usada pelo gerador de casos (área analítica) e pelo serviço do modelo (curva para o gráfico).
"""
import numpy as np

TAU_S = 0.15          # constante de decaimento do pico de partida
INICIO_S = 0.2        # repouso antes da manobra
AMOSTRAGEM_S = 0.05


def area(tempo, pico, patamar, rampa):
    """Integral da corrente durante a manobra (A·s), sem ruído. Aceita arrays."""
    tempo = np.asarray(tempo, dtype=float)
    return patamar * tempo + (pico - patamar) * TAU_S * (1 - np.exp(-tempo / TAU_S)) + rampa * 0.15 * tempo


def amostrar(tempo, pico, patamar, rampa, seed=0, ruido=0.02):
    """Curva amostrada (t, corrente) para exibição. `seed` torna o ruído reprodutível por manobra."""
    rng = np.random.default_rng(seed)
    t = np.arange(0, tempo + INICIO_S + 0.4, AMOSTRAGEM_S)
    i = np.zeros_like(t)
    m = (t >= INICIO_S) & (t <= INICIO_S + tempo)
    tm = t[m] - INICIO_S
    fim = rampa * np.clip((tm - 0.7 * tempo) / (0.3 * tempo), 0, 1)
    i[m] = patamar + (pico - patamar) * np.exp(-tm / TAU_S) + fim + rng.normal(0, ruido, m.sum())
    return t, np.clip(i, 0, None)
