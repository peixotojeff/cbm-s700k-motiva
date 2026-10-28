"""Banco do modelo versionado no Git: o data/cbm.db (≈250 MB) não cabe no GitHub, então vai uma cópia enxuta e compactada.

  python -m src.pacote empacotar   # depois de run_pipeline.py: gera dados/cbm.db.xz.001, .002, …
  python -m src.pacote restaurar   # recria data/cbm.db a partir das partes (src/db.py faz isso sozinho se faltar)

A cópia enxuta tem só o que o serviço do modelo (ml/) lê: a tabela casos e, de features, as colunas dos modelos e das
curvas do diagnóstico. A tabela telemetria (dado bruto, usado só para retreinar) fica de fora: run_pipeline.py a recria.
Partes de até 45 MB: abaixo do limite de 100 MB por arquivo do GitHub e do aviso de 50 MB.
"""
import lzma
import sqlite3
import sys
from contextlib import closing
from pathlib import Path

import joblib

from . import config as C

PASTA = C.ROOT / "dados"
PREFIXO = "cbm.db.xz"
PARTE_BYTES = 45 * 1024 * 1024
# colunas lidas por ml/main.py além das features dos modelos (identificação, curva do diagnóstico, isolamento)
COLUNAS_FIXAS = ["maquina_id", "ciclo", "maquina", "tipo", "isolamento_mohm", "ciclos_ate_1mohm",
                 "tempo_manobra_s", "corrente_pico", "corrente_patamar", "corrente_rampa"]


def partes() -> list[Path]:
    return sorted(PASTA.glob(f"{PREFIXO}.[0-9][0-9][0-9]"))


def _colunas_dos_modelos() -> list[str]:
    cols = []
    for nome in ("modelo.joblib", "modelo_aviso.joblib"):
        cols += list(joblib.load(C.MODELS / nome).feature_names_in_)
    modo = C.MODELS / "modelo_modo.joblib"
    if modo.exists():
        cols += list(getattr(joblib.load(modo)["modelo"], "feature_names_in_", []))
    return list(dict.fromkeys(COLUNAS_FIXAS + sorted(set(cols))))


def empacotar(origem: Path | None = None) -> list[Path]:
    from .db import DB_PATH

    origem = origem or DB_PATH
    enxuto = PASTA / "cbm_enxuto.db"
    PASTA.mkdir(exist_ok=True)
    enxuto.unlink(missing_ok=True)
    cols = _colunas_dos_modelos()
    with closing(sqlite3.connect(origem)) as con:
        con.execute(f"ATTACH '{enxuto.as_posix()}' AS o")
        con.execute(f"CREATE TABLE o.features AS SELECT {', '.join(cols)} FROM features")
        con.execute("CREATE TABLE o.casos AS SELECT * FROM casos")
        con.commit()
        con.execute("DETACH o")
    with closing(sqlite3.connect(enxuto)) as con:
        con.execute("CREATE INDEX ix_feat ON features(maquina_id, ciclo)")
        con.execute("CREATE INDEX ix_feat_ciclo ON features(ciclo)")
        con.commit()
        con.execute("VACUUM")
    print(f"cópia enxuta: {enxuto.stat().st_size / 1e6:.0f} MB ({len(cols)} colunas de features + casos); compactando (~2 min)…")
    dados = lzma.compress(enxuto.read_bytes(), preset=9 | lzma.PRESET_EXTREME)
    enxuto.unlink()
    for p in partes():
        p.unlink()
    criadas = []
    for k in range(0, len(dados), PARTE_BYTES):
        p = PASTA / f"{PREFIXO}.{k // PARTE_BYTES + 1:03d}"
        p.write_bytes(dados[k:k + PARTE_BYTES])
        criadas.append(p)
    print(f"{len(dados) / 1e6:.0f} MB em {len(criadas)} partes: " + ", ".join(p.name for p in criadas))
    return criadas


def restaurar(destino: Path | None = None) -> bool:
    """Recria o banco a partir das partes. Devolve False se não houver partes (rode run_pipeline.py)."""
    from .db import DB_PATH

    destino = destino or DB_PATH
    ps = partes()
    if not ps:
        return False
    destino.parent.mkdir(exist_ok=True)
    tmp = destino.with_suffix(".tmp")
    tmp.write_bytes(lzma.decompress(b"".join(p.read_bytes() for p in ps)))
    tmp.replace(destino)  # só aparece completo: um processo interrompido não deixa banco pela metade
    return True


if __name__ == "__main__":
    acao = sys.argv[1] if len(sys.argv) > 1 else ""
    if acao == "empacotar":
        empacotar()
    elif acao == "restaurar":
        print("data/cbm.db restaurado" if restaurar() else f"nenhuma parte em {PASTA}; rode run_pipeline.py")
    else:
        sys.exit(__doc__)
