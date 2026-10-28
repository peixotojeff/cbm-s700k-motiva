"""Grandezas de uma manobra a partir da curva de corrente do motor."""
from dataclasses import dataclass

import numpy as np

LIMIAR_A = 0.25  # corrente acima disso = motor energizado (manobra em curso)


@dataclass
class Manobra:
    tempo_manobra_s: float  # duração com o motor energizado
    corrente_pico: float    # pico de partida
    corrente_media: float   # média durante o curso
    corrente_area: float    # integral da corrente (A·s): esforço total do motor


def extrair(t_s, corrente_a, limiar_a: float = LIMIAR_A) -> Manobra:
    """Extrai tempo de manobra, pico, média e área de uma curva amostrada (t em segundos, corrente em ampères)."""
    t = np.asarray(t_s, dtype=float)
    i = np.asarray(corrente_a, dtype=float)
    if t.size < 3 or t.size != i.size:
        raise ValueError("curva precisa de ao menos 3 amostras com t e corrente do mesmo tamanho")
    ordem = np.argsort(t)
    t, i = t[ordem], i[ordem]
    ativo = np.flatnonzero(i > limiar_a)
    if ativo.size < 2:
        raise ValueError("motor não energizou: corrente nunca passou do limiar (motor não aciona?)")
    ini, fim = ativo[0], ativo[-1]
    tt, ii = t[ini:fim + 1], i[ini:fim + 1]
    return Manobra(
        tempo_manobra_s=round(float(tt[-1] - tt[0]), 3),
        corrente_pico=round(float(ii.max()), 3),
        corrente_media=round(float(ii.mean()), 3),
        corrente_area=round(float(np.trapezoid(ii, tt)), 3),
    )
