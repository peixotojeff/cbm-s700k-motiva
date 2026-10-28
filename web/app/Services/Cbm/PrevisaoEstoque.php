<?php

namespace App\Services\Cbm;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Models\Peca;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Cruza o plano de intervenção (demanda por componente e data) com o estoque e o prazo de reposição de cada peça.
 * Vermelho = a peça falta antes de um pedido feito hoje chegar; Amarelo = pedir até uma data ou repor o mínimo.
 */
class PrevisaoEstoque
{
    public function __construct(private Estado $estado) {}

    /** @return Collection<int, array> uma linha por peça, das mais críticas para as mais folgadas */
    public function calcular(int $horizonteDias, ?CarbonImmutable $hoje = null): Collection
    {
        return $this->dePlano($this->estado->atual($horizonteDias)['plano'], $horizonteDias, $hoje);
    }

    /** Mesma previsão a partir de um plano já calculado (evita reavaliar todas as leituras). */
    public function dePlano(Collection $plano, int $horizonteDias, ?CarbonImmutable $hoje = null): Collection
    {
        $hoje ??= CarbonImmutable::today();

        return Peca::orderBy('componente')->orderBy('codigo')->get()
            ->map(fn (Peca $p) => $this->linha($p, $plano->where('chave', $p->componente), $horizonteDias, $hoje))
            ->sortBy(fn ($l) => [$l['status']->ordem(), $l['dias_para_pedir'] ?? INF])->values();
    }

    private function linha(Peca $peca, Collection $itens, int $horizonte, CarbonImmutable $hoje): array
    {
        // Sem prazo estimável (ex.: perda de correspondência) conta como necessidade imediata. Demanda além de
        // horizonte + reposição ainda pode ser pedida depois, então fica fora da conta.
        $janela = $horizonte + $peca->prazo_reposicao_dias;
        $itens = $itens->map(fn ($i) => $i + ['dia' => max(0.0, (float) ($i['prazo_dias'] ?? 0))])
            ->where('dia', '<=', $janela)->sortBy('dia')->values();

        $acumulado = 0;
        $falta = null;
        foreach ($itens as $i) {
            $acumulado += $peca->qtd_por_intervencao;
            if ($falta === null && $acumulado > $peca->estoque) {
                $falta = $i;
            }
        }
        // No horizonte: prazo dentro dele ou item que o plano já mandou programar (ex.: grandeza acima do alerta).
        $noHorizonte = $itens->filter(fn ($i) => $i['dia'] <= $horizonte || ($i['prioridade'] ?? null) === Prioridade::Programar)
            ->count() * $peca->qtd_por_intervencao;
        $saldo = $peca->estoque - $noHorizonte;

        $diasParaPedir = $falta ? floor($falta['dia'] - $peca->prazo_reposicao_dias) : null;
        [$status, $situacao] = match (true) {
            $falta !== null && $diasParaPedir < 0 => [Status::Vermelho, 'Ruptura: falta antes da reposição chegar'],
            $falta !== null => [Status::Amarelo, 'Pedir até '.$hoje->addDays((int) $diasParaPedir)->format('d/m/Y')],
            $saldo < $peca->estoque_minimo => [Status::Amarelo, 'Pedir agora: abaixo do estoque mínimo'],
            default => [Status::Verde, 'Estoque suficiente'],
        };

        return [
            'peca' => $peca, 'status' => $status, 'situacao' => $situacao,
            'demanda_horizonte' => $noHorizonte, 'demanda_total' => $acumulado, 'saldo' => $saldo,
            'falta_em_dias' => $falta ? (int) floor($falta['dia']) : null, 'falta_maquina' => $falta['maquina'] ?? null,
            'dias_para_pedir' => $diasParaPedir,
            'maquinas' => $itens->pluck('maquina')->unique()->values()->all(),
        ];
    }
}
