<?php

namespace App\Services\Cbm;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Models\Leitura;
use Illuminate\Support\Collection;

/**
 * Avaliação preventiva das leituras: faixas nominais da versão da máquina (agulha × jacaré) + tendência linear por data.
 */
class Avaliador
{
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? config('cbm');
    }

    /** Faixas de tempo e força da versão (manual Siemens 26330-OM-15, seção 4.1). */
    public function limites(string $tipo): array
    {
        $v = $this->cfg['versoes'][$tipo] ?? $this->cfg['versoes']['agulha'];
        $tol = $this->cfg['tempo_tolerancia_s'];

        return [
            'tempo' => [$v['tempo_nominal'] - $tol, $v['tempo_nominal'] + $tol],
            'forca' => [$v['forca_nominal'] - $v['forca_tol'], $v['forca_nominal'] + $v['forca_tol']],
        ];
    }

    private const CAMPOS = [
        'tempo_manobra_s', 'forca_n', 'isolamento_mohm', 'corrente_pico', 'corrente_media',
        'vibracao_mm_s', 'temperatura_motor_c', 'umidade_gabinete_pct', 'ocorrencia',
    ];

    /** Grandezas de condição (vibração, temperatura, umidade) com limites de alerta e crítico. */
    private function condicao(): array
    {
        return $this->cfg['condicao'] ?? [];
    }

    /**
     * Retrato atual da máquina: o último valor conhecido de cada grandeza. Um sensor que só mede corrente e tempo
     * não apaga o isolamento ou a força medidos na última inspeção.
     *
     * @param  Collection<int, Leitura>  $leituras
     */
    public function ultimoConhecido(Collection $leituras): Leitura
    {
        $ult = $leituras->last();
        $atual = new Leitura(['medido_em' => $ult->medido_em]);
        foreach (self::CAMPOS as $campo) {
            $atual->{$campo} = $campo === 'ocorrencia' ? $ult->ocorrencia : $leituras->whereNotNull($campo)->last()?->{$campo};
        }

        return $atual;
    }

    /** @return list<string> */
    public function alertas(Leitura $l, string $tipo = 'agulha'): array
    {
        $lim = $this->limites($tipo);
        [$tMin, $tMax] = $lim['tempo'];
        [$fMin, $fMax] = $lim['forca'];
        $a = [];
        if ($l->tempo_manobra_s !== null && ($l->tempo_manobra_s < $tMin || $l->tempo_manobra_s > $tMax)) {
            $a[] = sprintf('Tempo de manobra %.2f s fora da faixa (%s–%s s)', $l->tempo_manobra_s, $this->num($tMin, 1), $this->num($tMax, 1));
        }
        if ($l->forca_n !== null && ($l->forca_n < $fMin || $l->forca_n > $fMax)) {
            $a[] = sprintf('Força %.0f N fora da faixa (%s–%s N)', $l->forca_n, $this->num($fMin), $this->num($fMax));
        }
        if ($this->isolamentoCritico($l)) {
            $a[] = sprintf('Isolamento %.2f MΩ no limite crítico (≤ %s MΩ)', $l->isolamento_mohm, $this->cfg['isolamento_min']);
        }
        foreach ($this->condicao() as $campo => $g) {
            if ($l->{$campo} !== null && $l->{$campo} >= $g['alerta']) {
                $nivel = $l->{$campo} >= $g['critico'] ? 'crítico' : 'alerta';
                $a[] = sprintf('%s %s %s em %s (alerta ≥ %s, crítico ≥ %s)', $g['rotulo'], $this->num($l->{$campo}, 1), $g['unidade'], $nivel,
                    $this->num($g['alerta'], 1), $this->num($g['critico'], 1));
            }
        }

        return $a;
    }

    private function num(float $v, int $casas = 0): string
    {
        return number_format($v, $casas, ',', '.');
    }

    private function isolamentoCritico(Leitura $l): bool
    {
        return $l->isolamento_mohm !== null && $l->isolamento_mohm <= $this->cfg['isolamento_min'];
    }

    private function acaoPorRegra(Leitura $l, string $tipo): string
    {
        $c = $this->cfg;
        [$fMin, $fMax] = $this->limites($tipo)['forca'];

        $acima = collect($this->condicao())->filter(fn ($g, $campo) => $l->{$campo} !== null && $l->{$campo} >= $g['alerta']);

        return match (true) {
            $this->isolamentoCritico($l) => $c['acoes']['isolamento'],
            $acima->isNotEmpty() => $c['componentes'][$acima->first()['componente']][1],
            $l->forca_n !== null && $l->forca_n < $fMin => $c['acoes']['embreagem_patinando'],
            $l->forca_n !== null && $l->forca_n > $fMax => $c['acoes']['talonada'],
            default => $c['acoes']['tempo'],
        };
    }

    /**
     * Status de uma máquina a partir das suas leituras (ordenadas por medido_em).
     *
     * @param  Collection<int, Leitura>  $leituras
     */
    public function situacao(string $codigo, Collection $leituras, string $tipo = 'agulha'): array
    {
        $ult = $this->ultimoConhecido($leituras);
        $alertas = $this->alertas($ult, $tipo);
        $status = ($leituras->count() < $this->cfg['min_leituras'] && ! $alertas) ? Status::Aguardando : Status::Verde;

        if ($this->isolamentoCritico($ult)) {
            $status = Status::Vermelho;
        } elseif ($alertas && in_array($status, [Status::Verde, Status::Aguardando], true)) {
            $status = Status::Amarelo;
        }

        return [
            'maquina' => $codigo,
            'tipo' => $tipo,
            'status' => $status,
            'leituras' => $leituras->count(),
            'ultima_leitura' => $ult->medido_em,
            'alertas' => $alertas,
            'acao' => $alertas ? $this->acaoPorRegra($ult, $tipo) : $this->cfg['acoes']['nenhuma'],
        ];
    }

    /**
     * Dias até a tendência linear de $y cruzar $limite. 0 se já cruzou; null se não há tendência de aproximação.
     *
     * @param  list<float>  $x  dias desde a primeira leitura
     * @param  list<float>  $y
     */
    private function diasAte(array $x, array $y, float $limite, bool $sobe): ?float
    {
        $atual = end($y);
        if (($sobe && $atual >= $limite) || (! $sobe && $atual <= $limite)) {
            return 0.0;
        }
        if (count($y) < $this->cfg['min_leituras'] || end($x) - $x[0] < 0.01) {
            return null;
        }
        $a = $this->inclinacao($x, $y);
        if (($sobe && $a <= 0) || (! $sobe && $a >= 0)) {
            return null;
        }

        return ($limite - $atual) / $a;
    }

    /** Coeficiente angular da regressão linear por mínimos quadrados. */
    private function inclinacao(array $x, array $y): float
    {
        $n = count($x);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $num = $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($x[$i] - $mx) * ($y[$i] - $my);
            $den += ($x[$i] - $mx) ** 2;
        }

        return $den == 0.0 ? 0.0 : $num / $den;
    }

    private function mediana(array $v): float
    {
        sort($v);
        $n = count($v);

        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /**
     * Componentes sob risco de uma máquina, com prazo (dias) até o limite.
     *
     * @param  Collection<int, Leitura>  $leituras
     * @return list<array{chave:string,componente:string,acao:string,dias:?float,motivo:string}>
     */
    public function prognostico(Collection $leituras, string $tipo = 'agulha'): array
    {
        $c = $this->cfg;
        $lim = $this->limites($tipo);
        $t0 = $leituras->first()->medido_em;
        // Série de uma grandeza: [dias desde a 1ª leitura, valores], só com as leituras que a mediram.
        $serie = function (string $campo) use ($leituras, $t0) {
            $ls = $leituras->whereNotNull($campo)->values();

            return [
                $ls->map(fn (Leitura $l) => $t0->diffInSeconds($l->medido_em, false) / 86400)->all(),
                $ls->map(fn (Leitura $l) => (float) $l->{$campo})->all(),
            ];
        };
        $cand = [];
        $add = function (string $chave, ?float $dias, string $motivo) use (&$cand) {
            if (! array_key_exists($chave, $cand) || ($dias !== null && ($cand[$chave][0] === null || $dias < $cand[$chave][0]))) {
                $cand[$chave] = [$dias, $motivo];
            }
        };

        [$x, $y] = $serie('isolamento_mohm');
        $d = $y ? $this->diasAte($x, $y, $c['isolamento_min'], false) : null;
        if ($d !== null) {
            $add('isolamento', $d, $d > 0 ? 'Isolamento em queda até o limite de 1 MΩ' : 'Isolamento no limite crítico');
        }
        $tMax = $lim['tempo'][1];
        [$x, $y] = $serie('tempo_manobra_s');
        $d = $y ? $this->diasAte($x, $y, $tMax, true) : null;
        if ($d !== null) {
            $add('barras', $d, $d > 0 ? "Tempo de manobra subindo até {$this->num($tMax, 1)} s" : "Tempo de manobra acima de {$this->num($tMax, 1)} s");
        }
        [$fMin, $fMax] = $lim['forca'];
        [$x, $y] = $serie('forca_n');
        foreach ([[false, $fMin, 'embreagem', "Força caindo abaixo de {$this->num($fMin)} N"], [true, $fMax, 'agulha', "Força subindo acima de {$this->num($fMax)} N"]] as [$sobe, $limite, $chave, $txt]) {
            $d = $y ? $this->diasAte($x, $y, $limite, $sobe) : null;
            if ($d !== null) {
                $add($chave, $d, $txt);
            }
        }
        [$x, $pico] = $serie('corrente_pico');
        if ($pico) {
            $ref = $this->mediana(array_slice($pico, 0, $c['min_leituras']));
            $d = $this->diasAte($x, $pico, 0.8 * $ref, false);
            if ($d !== null && $ref > 0) {
                $add('motor', $d, 'Corrente de pico caindo (>20% abaixo da referência)');
            }
        }
        $jaEmAlerta = [];
        foreach ($this->condicao() as $campo => $g) {
            [$x, $y] = $serie($campo);
            if (! $y) {
                continue;
            }
            $d = $this->diasAte($x, $y, $g['critico'], true);
            if (end($y) >= $g['alerta']) {
                $jaEmAlerta[$g['componente']] = true; // nunca fica só em monitoramento
            }
            if ($d === null && end($y) >= $g['alerta']) {
                $add($g['componente'], null, "{$g['rotulo']} acima do alerta ({$this->num($g['alerta'], 1)} {$g['unidade']})");
            } elseif ($d !== null) {
                $add($g['componente'], $d, $d > 0 ? "{$g['rotulo']} subindo até {$this->num($g['critico'], 1)} {$g['unidade']}" : "{$g['rotulo']} no nível crítico");
            }
        }
        $recentes = $leituras->slice(-$c['min_leituras'])->pluck('ocorrencia');
        if ($recentes->intersect($c['ocorrencias_fuso'])->isNotEmpty()) {
            $add('fuso', 0.0, 'Ocorrência recente de perda de correspondência/travamento (pode não alterar a corrente)');
        }
        if ($recentes->intersect($c['ocorrencias_gabinete'] ?? [])->isNotEmpty()) {
            $add('gabinete', 0.0, 'Infiltração de água no gabinete registrada (risco ao isolamento)');
        }

        $out = [];
        foreach ($cand as $chave => [$dias, $motivo]) {
            $out[] = [
                'chave' => $chave, 'componente' => $c['componentes'][$chave][0], 'acao' => $c['componentes'][$chave][1],
                'dias' => $dias, 'motivo' => $motivo, 'em_alerta' => $jaEmAlerta[$chave] ?? false,
            ];
        }

        return $out;
    }

    /**
     * Situação por máquina e plano de intervenção dentro do horizonte.
     *
     * @param  Collection<string, Collection<int, Leitura>>  $porMaquina  código => leituras ordenadas
     * @param  array<string, string>  $tipos  código => agulha|jacare (padrão: agulha)
     * @return array{situacao: Collection, plano: Collection}
     */
    public function analisar(Collection $porMaquina, int $horizonteDias, array $tipos = []): array
    {
        $situacao = $porMaquina->sortKeys()
            ->map(fn (Collection $ls, string $cod) => $this->situacao($cod, $ls, $tipos[$cod] ?? 'agulha'))
            ->sortBy(fn ($s) => $s['status']->ordem())->values();

        $plano = collect();
        foreach ($situacao as $s) {
            foreach ($this->prognostico($porMaquina[$s['maquina']], $s['tipo']) as $p) {
                $dias = $p['dias'];
                $prioridade = match (true) {
                    $dias === null => Prioridade::Programar, // sem prazo estimável
                    $dias <= 0 => Prioridade::Imediato,
                    $dias <= $horizonteDias, $p['em_alerta'] => Prioridade::Programar,
                    default => Prioridade::Monitorar,
                };
                $plano->push([
                    'maquina' => $s['maquina'], 'chave' => $p['chave'], 'componente' => $p['componente'], 'prioridade' => $prioridade,
                    'prazo_dias' => $dias, 'motivo' => $p['motivo'], 'acao' => $p['acao'],
                ]);
            }
        }
        $plano = $plano->sort(fn ($a, $b) => [$a['prioridade']->ordem(), $a['prazo_dias'] ?? INF] <=> [$b['prioridade']->ordem(), $b['prazo_dias'] ?? INF])->values();

        return ['situacao' => $this->aplicarPlano($situacao, $plano), 'plano' => $plano];
    }

    /** Imediato -> Vermelho; Programar -> Amarelo (nunca rebaixa). */
    private function aplicarPlano(Collection $situacao, Collection $plano): Collection
    {
        return $situacao->map(function (array $s) use ($plano) {
            $itens = $plano->where('maquina', $s['maquina'])->where('prioridade', '!=', Prioridade::Monitorar)->values();
            if ($itens->isEmpty()) {
                return $s;
            }
            if ($s['status'] !== Status::Vermelho) {
                $s['status'] = $itens->contains(fn ($i) => $i['prioridade'] === Prioridade::Imediato) ? Status::Vermelho : Status::Amarelo;
            }
            $s['acao'] = $itens->first()['acao'];

            return $s;
        })->sortBy(fn ($s) => $s['status']->ordem())->values();
    }
}
