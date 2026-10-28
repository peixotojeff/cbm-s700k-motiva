<?php

namespace App\Services\Cbm;

use App\Models\Leitura;
use App\Models\Maquina;
use Carbon\CarbonImmutable;

/**
 * Dados de exemplo (mockup) para demonstração. Todas as linhas levam responsavel = MOCK e podem ser removidas.
 * Simula o monitoramento contínuo (origem API) e as preventivas do PMP registradas em campo (origem manual).
 * Locais e grupos do PMP são fictícios.
 */
class MockLeituras
{
    /**
     * Medições na escala de uma máquina de agulha; para jacaré móvel somam-se +0,5 s e +500 N (manual 26330-OM-15, 4.1).
     * 'visitas' = nível do PMP => índice da leitura em que a preventiva foi feita.
     */
    private function cenarios(): array
    {
        return [
            ['codigo' => 'MCH 4123 JM', 'local' => 'X12', 'n' => 12, 'passo' => 2, 'visitas' => ['N2' => 0, 'N1' => 7],
                'f' => fn ($i) => ['iso' => 7.0 - 0.5 * $i, 'tempo' => 6.15 + 0.03 * $i, 'forca' => 5750, 'pico' => 4.0], 'obs' => 'Isolamento caindo de forma contínua'],
            ['codigo' => 'MCH 4124 JM', 'local' => 'X12', 'n' => 10, 'passo' => 3, 'visitas' => ['N2' => 0, 'N1' => 6],
                'f' => fn ($i) => ['iso' => 9.0 - 0.1 * $i, 'tempo' => 6.2 + 0.04 * $i, 'forca' => 5780 + 15 * $i, 'pico' => 4.0], 'obs' => 'Barras de detecção possivelmente desreguladas'],
            ['codigo' => 'MCH 4134 JM', 'local' => 'X13', 'n' => 9, 'passo' => 3, 'visitas' => ['N2' => 0, 'N1' => 6], 'oc_final' => 'Perda de correspondência',
                'f' => fn ($i) => ['iso' => 8.5 - 0.1 * $i, 'tempo' => 6.25, 'forca' => 5750, 'pico' => 4.0], 'obs' => 'Perda de correspondência sem alteração visível da corrente'],
            ['codigo' => 'MCH 4132 AG', 'local' => 'X13', 'n' => 11, 'passo' => 2, 'visitas' => ['N2' => 0, 'N1' => 5],
                'f' => fn ($i) => ['iso' => 9.5 - 0.05 * $i, 'tempo' => 6.0 + 0.01 * $i, 'forca' => 5800 - 60 * $i, 'pico' => 3.9 - 0.02 * $i], 'obs' => 'Força de acionamento em queda'],
            ['codigo' => 'MCH 4082 JM', 'local' => 'X08', 'n' => 10, 'passo' => 3, 'visitas' => ['N2' => 0, 'N1' => 3],
                'f' => fn ($i) => ['iso' => 9.0 - 0.08 * $i, 'tempo' => 6.2 + 0.02 * $i, 'forca' => 5600 + 30 * $i, 'pico' => 4.0 + 0.08 * $i], 'obs' => 'Folga na agulha; corrente de pico subindo'],
            ['codigo' => 'MCH 4083 JM', 'local' => 'X04', 'n' => 12, 'passo' => 3, 'visitas' => ['N1' => 0],
                'f' => fn ($i) => ['iso' => 10.5 - 0.03 * $i, 'tempo' => 6.0, 'forca' => 5500, 'pico' => 4.0], 'obs' => 'Sem anormalidades'],
            ['codigo' => 'MCH 4081 AG', 'local' => 'X08', 'n' => 8, 'passo' => 4, 'visitas' => ['N1' => 0],
                'f' => fn ($i) => ['iso' => 11.0 - 0.02 * $i, 'tempo' => 6.0, 'forca' => 5520, 'pico' => 4.05], 'obs' => 'Sem anormalidades'],
            ['codigo' => 'MCH 4084 JM', 'local' => 'X08', 'n' => 10, 'passo' => 3, 'visitas' => ['N2' => 0, 'N1' => 3],
                'f' => fn ($i) => ['iso' => 9.0 - 0.1 * $i, 'tempo' => 6.0 + 0.01 * $i, 'forca' => 5500, 'pico' => 4.0 - 0.1 * $i], 'obs' => 'Corrente de pico em queda'],
            ['codigo' => 'MCH 4132 JM', 'local' => null, 'n' => 2, 'passo' => 3, 'visitas' => [],
                'f' => fn ($i) => ['iso' => 10.0, 'tempo' => 6.0, 'forca' => 5500, 'pico' => 4.0], 'obs' => 'Máquina recém-cadastrada'],
            // Grandezas de condição do kick-off (vibração, temperatura, umidade), medidas com instrumento portátil.
            ['codigo' => 'MCH 4085 AG', 'local' => 'X05', 'n' => 10, 'passo' => 3, 'visitas' => ['N1' => 0],
                'f' => fn ($i) => ['iso' => 10.0 - 0.03 * $i, 'tempo' => 6.05 + 0.01 * $i, 'forca' => 5520, 'pico' => 4.0, 'vib' => 1.5 + 0.18 * $i, 'temp' => 45 + 1.5 * $i],
                'obs' => 'Ruído e vibração crescentes no motor (rolamentos)'],
            ['codigo' => 'MCH 4086 JM', 'local' => 'X06', 'n' => 9, 'passo' => 3, 'visitas' => ['N1' => 0], 'oc_final' => 'Infiltração no gabinete',
                'f' => fn ($i) => ['iso' => 9.0 - 0.3 * $i, 'tempo' => 6.0, 'forca' => 5500, 'pico' => 4.0, 'umid' => 60 + 3.5 * $i],
                'obs' => 'Água no gabinete após chuva forte; vedação da tampa ressecada'],
        ];
    }

    /** Normal(0, sigma) por Box-Muller, reprodutível via mt_srand. */
    private function ruido(float $sigma): float
    {
        $u1 = (mt_rand() + 1) / (mt_getrandmax() + 1);
        $u2 = mt_rand() / mt_getrandmax();

        return $sigma * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }

    public function carregar(?CarbonImmutable $agora = null): int
    {
        mt_srand(11);
        $agora ??= CarbonImmutable::now()->startOfHour();
        $this->limpar();
        $registrar = new RegistrarLeitura;
        $total = 0;

        foreach ($this->cenarios() as $c) {
            $tipo = Maquina::inferirTipo($c['codigo']);
            [$dt, $df] = $tipo === 'jacare' ? [0.5, 500] : [0.0, 0];
            $niveis = array_flip($c['visitas']); // índice => nível
            for ($i = 0; $i < $c['n']; $i++) {
                $m = ($c['f'])($i);
                $ultima = $i === $c['n'] - 1;
                $visita = $niveis[$i] ?? null;
                $registrar->executar([
                    'maquina' => $c['codigo'],
                    'medido_em' => $agora->subDays(1 + $c['passo'] * ($c['n'] - 1 - $i))->subHours(mt_rand(0, 3)),
                    'tempo_manobra_s' => round($m['tempo'] + $dt + $this->ruido(0.015), 3),
                    'forca_n' => round($m['forca'] + $df + $this->ruido(25), 1),
                    'isolamento_mohm' => round(max($m['iso'] + $this->ruido(0.05), 0.2), 2),
                    'corrente_pico' => round($m['pico'] + $this->ruido(0.03), 2),
                    'corrente_media' => round(1.8 + $this->ruido(0.02), 2),
                    'vibracao_mm_s' => isset($m['vib']) ? round(max($m['vib'] + $this->ruido(0.05), 0), 2) : null,
                    'temperatura_motor_c' => isset($m['temp']) ? round($m['temp'] + $this->ruido(0.8), 1) : null,
                    'umidade_gabinete_pct' => isset($m['umid']) ? round(min($m['umid'] + $this->ruido(1.0), 100), 1) : null,
                    'ocorrencia' => $visita ? "Inspeção $visita" : ($ultima ? ($c['oc_final'] ?? 'Leitura de monitoramento') : 'Leitura de monitoramento'),
                    'observacoes' => $ultima ? $c['obs'] : '',
                    'responsavel' => Leitura::MARCA_MOCK,
                    'origem' => $visita ? 'manual' : 'api',
                ]);
                $total++;
            }
            Maquina::where('codigo', $c['codigo'])->update([
                'linha' => 'Linha 4 - Amarela', 'local' => $c['local'], 'grupo_pmp' => Maquina::inferirGrupo($c['local']),
            ]);
        }

        return $total;
    }

    /** Remove as leituras de exemplo e as máquinas que ficaram sem nenhuma leitura. */
    public function limpar(): int
    {
        $n = Leitura::where('responsavel', Leitura::MARCA_MOCK)->delete();
        Maquina::doesntHave('leituras')->delete();

        return $n;
    }
}
