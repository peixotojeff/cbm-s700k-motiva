<?php

namespace App\Services\Cbm;

use App\Enums\Prioridade;
use App\Models\Leitura;
use App\Models\Maquina;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calendário × condição: compara a próxima preventiva pelo PMP-3AMV-011-10 com o prazo previsto pela condição.
 * Nunca sugere adiar uma preventiva: o fabricante condiciona a garantia aos intervalos (manual 26330-OM-15, 8.2).
 */
class Calendario
{
    public function __construct(private Estado $estado) {}

    /** @return array{linhas: Collection, resumo: array} */
    public function calcular(int $horizonteDias, ?CarbonImmutable $hoje = null): array
    {
        $hoje ??= CarbonImmutable::now();
        $plano = $this->estado->atual($horizonteDias)['plano']->where('prioridade', '!=', Prioridade::Monitorar);
        $mapa = config('cbm.ocorrencia_nivel');

        $linhas = Maquina::with('leituras')->orderBy('codigo')->get()
            ->filter(fn (Maquina $m) => $m->leituras->isNotEmpty())
            ->map(function (Maquina $m) use ($hoje, $plano, $mapa) {
                $intervalos = config("cbm.pmp.{$m->grupo_pmp}.intervalos");
                $niveis = [];
                foreach ($intervalos ?? [] as $nivel => $semanas) {
                    $ultima = $m->leituras->filter(fn (Leitura $l) => ($mapa[$l->ocorrencia] ?? null) === $nivel)->last()?->medido_em;
                    $proxima = $ultima ? CarbonImmutable::parse($ultima)->addWeeks($semanas) : null;
                    $niveis[$nivel] = [
                        'semanas' => $semanas, 'ultima' => $ultima, 'proxima' => $proxima,
                        'dias' => $proxima ? (int) floor($hoje->diffInDays($proxima, false)) : null,
                    ];
                }
                $proximaVisita = collect($niveis)->filter(fn ($n) => $n['proxima'])->sortBy('dias')->map(fn ($n, $k) => $n + ['nivel' => $k])->first();

                $itens = $plano->where('maquina', $m->codigo);
                $critico = $itens->sortBy(fn ($i) => $i['prazo_dias'] ?? INF)->first();
                $prazo = $critico ? (int) floor($critico['prazo_dias'] ?? 0) : null;

                [$rec, $antecedencia] = $this->recomendacao($m, $critico, $prazo, $proximaVisita);

                return [
                    'maquina' => $m, 'niveis' => $niveis, 'proxima' => $proximaVisita, 'fuso' => $this->inspecaoFuso($m, $niveis),
                    'critico' => $critico, 'prazo_condicao' => $prazo, 'itens' => $itens->count(),
                    'recomendacao' => $rec, 'antecedencia' => $antecedencia,
                ];
            })->values();

        $antecipar = $linhas->where('recomendacao', 'antecipar');

        return ['linhas' => $linhas, 'resumo' => [
            'antecipar' => $antecipar->count(),
            'antecedencia_media' => $antecipar->isNotEmpty() ? (int) round($antecipar->avg('antecedencia')) : null,
            'atrasadas' => $linhas->filter(fn ($l) => ($l['proxima']['dias'] ?? 0) < 0)->count(),
            'sem_cadastro' => $linhas->filter(fn ($l) => ! $l['maquina']->grupo_pmp)->count(),
            'manter' => $linhas->where('recomendacao', 'manter')->count(),
            'fuso' => $linhas->filter(fn ($l) => $l['fuso'])->count(),
        ]];
    }

    /**
     * Inspeção dirigida do fuso na preventiva das máquinas críticas: é a única defesa contra o modo de falha que o
     * monitoramento não enxerga. null quando o grupo do PMP da máquina não entra na regra.
     *
     * @return array{nivel: string, itens: string, proxima: ?CarbonImmutable, dias: ?int}|null
     */
    private function inspecaoFuso(Maquina $m, array $niveis): ?array
    {
        $regra = config('cbm.inspecao_fuso');
        if (! in_array($m->grupo_pmp, $regra['grupos'], true)) {
            return null;
        }
        $n = $niveis[$regra['nivel']] ?? null;

        return ['nivel' => $regra['nivel'], 'itens' => $regra['itens'], 'proxima' => $n['proxima'] ?? null, 'dias' => $n['dias'] ?? null];
    }

    /** @return array{0: string, 1: ?int} chave da recomendação e dias de antecedência ganhos */
    private function recomendacao(Maquina $m, ?array $critico, ?int $prazo, ?array $proxima): array
    {
        if (! $critico) {
            return [($proxima['dias'] ?? 0) < 0 ? 'atrasada' : 'manter', null];
        }
        if (! $m->grupo_pmp) {
            return ['sem_cadastro', null];
        }
        if (! $proxima) {
            return ['sem_preventiva', null];
        }
        $diasVisita = max(0, $proxima['dias']);
        if ($prazo < $diasVisita) {
            return ['antecipar', $diasVisita - $prazo];
        }

        return ['incluir', null];
    }
}
