<?php

namespace App\Services\Cbm;

use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Support\Collection;

/** Carrega as leituras do banco e devolve situação + plano para o horizonte escolhido. */
class Estado
{
    public function __construct(private Avaliador $avaliador) {}

    /** @return Collection<string, Collection<int, Leitura>> código da máquina => leituras ordenadas */
    public function leiturasPorMaquina(): Collection
    {
        $codigos = Maquina::pluck('codigo', 'id');

        return Leitura::orderBy('medido_em')->orderBy('id')->get()
            ->groupBy(fn (Leitura $l) => $codigos[$l->maquina_id]);
    }

    /** @return array{situacao: Collection, plano: Collection} */
    public function atual(int $horizonteDias): array
    {
        return $this->avaliador->analisar($this->leiturasPorMaquina(), $horizonteDias, Maquina::pluck('tipo', 'codigo')->all());
    }
}
