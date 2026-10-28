<?php

namespace App\Services\Cbm;

use App\Enums\Status;
use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Support\Collection;

/**
 * Assertividade medida em campo, com os registros reais (não a base simulada).
 * Acertos: inspeção após alerta com defeito confirmado, ou falha que ocorreu com a máquina já em alerta.
 * Erros: inspeção após alerta sem defeito (alarme falso), ou falha com a máquina verde na leitura anterior (sem aviso).
 * Na "da intervenção", só conta como falso a inspeção sem defeito feita com a máquina em vermelho.
 */
class AssertividadeCampo
{
    public const MINIMO = 20; // abaixo disso uma porcentagem não diz nada

    public function __construct(private Avaliador $avaliador) {}

    public function calcular(): array
    {
        $c = config('cbm.ocorrencias_campo');
        $eventos = array_merge([$c['confirmado'], $c['sem_defeito']], $c['falhas']);
        $tipos = Maquina::pluck('tipo', 'id');
        $placar = ['acertos' => 0, 'sem_aviso' => 0, 'falsos' => 0, 'falsos_vermelho' => 0];
        $lista = Leitura::whereIn('ocorrencia', $eventos)->orderBy('medido_em')->get();

        foreach ($lista as $ev) {
            if ($ev->ocorrencia === $c['confirmado']) {
                $placar['acertos']++;

                continue;
            }
            $antes = $this->statusAntes($ev, $tipos[$ev->maquina_id] ?? 'agulha');
            $emAlerta = in_array($antes, [Status::Vermelho, Status::Amarelo], true);
            if ($ev->ocorrencia === $c['sem_defeito']) {
                $placar['falsos']++;
                $placar['falsos_vermelho'] += $antes === Status::Vermelho ? 1 : 0;
            } else {
                $placar[$emAlerta ? 'acertos' : 'sem_aviso']++;
            }
        }

        $decisoes = $placar['acertos'] + $placar['sem_aviso'] + $placar['falsos'];
        $taxa = fn (int $erros) => $decisoes >= self::MINIMO ? round($placar['acertos'] / max(1, $placar['acertos'] + $erros), 3) : null;

        return $placar + [
            'decisoes' => $decisoes, 'minimo' => self::MINIMO,
            'com_exemplo' => $lista->contains('responsavel', Leitura::MARCA_MOCK),
            'geral' => $taxa($placar['sem_aviso'] + $placar['falsos']),
            'intervencao' => $taxa($placar['sem_aviso'] + $placar['falsos_vermelho']),
        ];
    }

    /** Status da máquina com as leituras anteriores ao evento (o que o sistema mostrava antes dele). */
    private function statusAntes(Leitura $ev, string $tipo): ?Status
    {
        /** @var Collection<int, Leitura> $antes */
        $antes = Leitura::where('maquina_id', $ev->maquina_id)->where('medido_em', '<', $ev->medido_em)
            ->orderBy('medido_em')->orderBy('id')->get();
        if ($antes->isEmpty()) {
            return null;
        }
        $r = $this->avaliador->analisar(collect(['m' => $antes]), config('cbm.horizonte_padrao'), ['m' => $tipo]);

        return $r['situacao'][0]['status'];
    }
}
