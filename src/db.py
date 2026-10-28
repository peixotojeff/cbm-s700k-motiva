"""Banco SQLite local (data/cbm.db) com a base simulada usada pelo serviço do modelo (ml/). Reconstruído a cada execução do pipeline.
Num clone, vem compactado em dados/ (src/pacote.py) e é restaurado na primeira consulta."""
import sqlite3
from contextlib import closing

import pandas as pd

from . import config as C

DB_PATH = C.DATA / "cbm.db"


def conectar():
    C.DATA.mkdir(exist_ok=True)
    return sqlite3.connect(DB_PATH)


def garantir() -> bool:
    """Depois de um clone só existe a cópia compactada (dados/): restaura data/cbm.db na primeira vez que for preciso."""
    if DB_PATH.exists():
        return True
    from .pacote import restaurar

    return restaurar()


def construir(tele, feat, casos):
    """Recria o banco com os dados processados."""
    DB_PATH.unlink(missing_ok=True)
    with closing(conectar()) as con:
        tele.to_sql("telemetria", con, index=False)
        feat.to_sql("features", con, index=False)
        casos.to_sql("casos", con, index=False)
        con.execute("CREATE INDEX ix_tele ON telemetria(maquina_id, ciclo)")
        con.execute("CREATE INDEX ix_feat ON features(maquina_id, ciclo)")
        con.execute("CREATE INDEX ix_feat_ciclo ON features(ciclo)")
        con.commit()


def consultar(sql: str, params=()) -> pd.DataFrame:
    garantir()  # antes de conectar: o connect criaria um banco vazio no lugar
    with closing(conectar()) as con:
        return pd.read_sql_query(sql, con, params=params)
