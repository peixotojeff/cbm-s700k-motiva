"""Banco SQLite local (data/cbm.db) com a base simulada usada pelo serviço do modelo (ml/). Reconstruído a cada execução do pipeline."""
import sqlite3
from contextlib import closing

import pandas as pd

from . import config as C

DB_PATH = C.DATA / "cbm.db"


def conectar():
    C.DATA.mkdir(exist_ok=True)
    return sqlite3.connect(DB_PATH)


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
    with closing(conectar()) as con:
        return pd.read_sql_query(sql, con, params=params)
