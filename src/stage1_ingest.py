"""Estágio 1: ingestão, limpeza e validação de regras nominais (por versão da máquina)."""
import pandas as pd

from . import config as C


def limpar_telemetria(df: pd.DataFrame):
    """Retorna (df limpo com flags de validação, relatório de integridade)."""
    rel = {"linhas_entrada": len(df)}
    df = df.copy()
    df.columns = [c.strip().lower() for c in df.columns]
    df["timestamp"] = pd.to_datetime(df["timestamp"], errors="coerce")
    rel["timestamps_invalidos"] = int(df["timestamp"].isna().sum())
    antes = len(df)
    df = df.drop_duplicates(subset=["maquina_id", "ciclo"])
    rel["duplicatas_removidas"] = antes - len(df)
    cols = ["tempo_manobra_s", "forca_n", "isolamento_mohm"]
    rel["linhas_com_nulos"] = int(df[cols].isna().any(axis=1).sum())
    df = df.dropna(subset=["timestamp"]).sort_values(["maquina_id", "ciclo"])
    df[cols] = df.groupby("maquina_id")[cols].transform(lambda s: s.interpolate(limit_direction="both"))
    df = df.dropna(subset=cols)

    tipo = df["tipo"] if "tipo" in df else pd.Series("agulha", index=df.index)
    t_nom = tipo.map(lambda t: C.VERSOES[t]["tempo_nominal"])
    f_nom = tipo.map(lambda t: C.VERSOES[t]["forca_nominal"])
    df["fora_tempo"] = (df["tempo_manobra_s"] - t_nom).abs() > C.TEMPO_TOL
    df["fora_forca"] = (df["forca_n"] - f_nom).abs() > C.FORCA_TOL
    df["fora_isolamento"] = df["isolamento_mohm"] <= C.ISOLAMENTO_MIN
    rel.update(
        linhas_saida=len(df),
        fora_tempo=int(df["fora_tempo"].sum()),
        fora_forca=int(df["fora_forca"].sum()),
        fora_isolamento=int(df["fora_isolamento"].sum()),
    )
    return df.reset_index(drop=True), rel


def carregar_raw(raw=C.RAW):
    tele, rel = limpar_telemetria(pd.read_csv(raw / "telemetria.csv"))
    manut = pd.read_csv(raw / "manutencao.csv", parse_dates=["timestamp"])
    casos = pd.read_csv(raw / "casos.csv")
    return tele, manut, casos, rel


if __name__ == "__main__":
    print(carregar_raw()[3])
