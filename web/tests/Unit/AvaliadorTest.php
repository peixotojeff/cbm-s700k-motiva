<?php

namespace Tests\Unit;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Models\Leitura;
use App\Services\Cbm\Avaliador;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AvaliadorTest extends TestCase
{
    private function config(): array
    {
        return [
            'versoes' => [
                'agulha' => ['tempo_nominal' => 6.0, 'forca_nominal' => 5500.0, 'forca_tol' => 500.0],
                'jacare' => ['tempo_nominal' => 6.5, 'forca_nominal' => 6000.0, 'forca_tol' => 500.0],
            ],
            'tempo_tolerancia_s' => 0.5, 'isolamento_min' => 1.0,
            'min_leituras' => 3,
            'ocorrencias_fuso' => ['Perda de correspondência', 'Travamento'],
            'ocorrencias_gabinete' => ['Infiltração no gabinete'],
            'condicao' => [
                'vibracao_mm_s' => ['rotulo' => 'Vibração do motor', 'unidade' => 'mm/s', 'alerta' => 2.8, 'critico' => 4.5, 'componente' => 'transmissao'],
                'umidade_gabinete_pct' => ['rotulo' => 'Umidade no gabinete', 'unidade' => '%', 'alerta' => 80.0, 'critico' => 95.0, 'componente' => 'gabinete'],
            ],
            'acoes' => ['isolamento' => 'A-iso', 'tempo' => 'A-tempo', 'embreagem_patinando' => 'A-emb', 'talonada' => 'A-tal', 'nenhuma' => 'A-nenhuma'],
            'componentes' => [
                'isolamento' => ['Cabos', 'acao-iso'], 'barras' => ['Barras', 'acao-barras'], 'embreagem' => ['Embreagem', 'acao-emb'],
                'agulha' => ['Agulha', 'acao-ag'], 'motor' => ['Motor', 'acao-motor'], 'fuso' => ['Fuso', 'acao-fuso'],
                'transmissao' => ['Transmissão', 'acao-tra'], 'gabinete' => ['Gabinete', 'acao-gab'],
            ],
        ];
    }

    /** @param list<array<string,mixed>> $linhas */
    private function leituras(array $linhas, int $passoDias = 2): Collection
    {
        $t0 = CarbonImmutable::parse('2026-10-01 08:00');

        return collect($linhas)->map(function ($l, $i) use ($t0, $passoDias) {
            return new Leitura($l + [
                'medido_em' => $t0->addDays($i * $passoDias), 'tempo_manobra_s' => 6.25, 'forca_n' => 5750.0,
                'isolamento_mohm' => 10.0, 'corrente_pico' => 4.0, 'ocorrencia' => 'Inspeção N1',
            ]);
        });
    }

    private function analisar(Collection $ls, int $horizonte = 15): array
    {
        return (new Avaliador($this->config()))->analisar(collect(['MCH 1' => $ls]), $horizonte);
    }

    public function test_maquina_saudavel_fica_verde_e_sem_plano_ativo(): void
    {
        $r = $this->analisar($this->leituras(array_fill(0, 5, [])));

        $this->assertSame(Status::Verde, $r['situacao'][0]['status']);
        $this->assertSame([], $r['situacao'][0]['alertas']);
        $this->assertCount(0, $r['plano']->where('prioridade', '!=', Prioridade::Monitorar));
    }

    public function test_poucas_leituras_sem_alerta_ficam_aguardando(): void
    {
        $r = $this->analisar($this->leituras(array_fill(0, 2, [])));

        $this->assertSame(Status::Aguardando, $r['situacao'][0]['status']);
        $this->assertCount(0, $r['plano']);
    }

    public function test_isolamento_no_limite_e_vermelho_e_imediato(): void
    {
        $r = $this->analisar($this->leituras([[], [], ['isolamento_mohm' => 0.9]]));

        $this->assertSame(Status::Vermelho, $r['situacao'][0]['status']);
        $item = $r['plano']->firstWhere('componente', 'Cabos');
        $this->assertSame(Prioridade::Imediato, $item['prioridade']);
        $this->assertSame(0.0, $item['prazo_dias']);
    }

    public function test_isolamento_em_queda_estima_prazo_dentro_do_horizonte(): void
    {
        // 6,0 → 2,0 MΩ em 8 dias (−1 MΩ a cada 2 dias): cruza 1 MΩ em ~2 dias.
        $ls = $this->leituras(array_map(fn ($i) => ['isolamento_mohm' => 6.0 - $i], range(0, 4)));
        $r = $this->analisar($ls);

        $item = $r['plano']->firstWhere('componente', 'Cabos');
        $this->assertSame(Prioridade::Programar, $item['prioridade']);
        $this->assertEqualsWithDelta(2.0, $item['prazo_dias'], 0.01);
        $this->assertSame(Status::Amarelo, $r['situacao'][0]['status']);
    }

    public function test_prazo_fora_do_horizonte_vira_monitorar(): void
    {
        $ls = $this->leituras(array_map(fn ($i) => ['isolamento_mohm' => 6.0 - $i], range(0, 4)));

        $this->assertSame(Prioridade::Monitorar, $this->analisar($ls, 1)['plano']->firstWhere('componente', 'Cabos')['prioridade']);
    }

    public function test_vibracao_subindo_aponta_rolamentos_com_prazo(): void
    {
        // 2,0 → 3,2 mm/s (+0,3 a cada 2 dias): passou do alerta 2,8 e chega a 4,5 em ~8,7 dias.
        $ls = $this->leituras(array_map(fn ($i) => ['vibracao_mm_s' => 2.0 + 0.3 * $i], range(0, 4)));
        $r = $this->analisar($ls);

        $item = $r['plano']->firstWhere('componente', 'Transmissão');
        $this->assertSame(Prioridade::Programar, $item['prioridade']);
        $this->assertEqualsWithDelta(1.3 / 0.15, $item['prazo_dias'], 0.01);
        $this->assertSame(Status::Amarelo, $r['situacao'][0]['status']);
        $this->assertStringContainsString('Vibração do motor 3,2 mm/s em alerta', $r['situacao'][0]['alertas'][0]);
        $this->assertSame('acao-tra', $r['situacao'][0]['acao']);
        // já acima do alerta: entra no plano mesmo com o crítico fora do horizonte
        $this->assertSame(Prioridade::Programar, $this->analisar($ls, 7)['plano']->firstWhere('componente', 'Transmissão')['prioridade']);
    }

    public function test_vibracao_critica_e_imediata(): void
    {
        $r = $this->analisar($this->leituras([[], [], ['vibracao_mm_s' => 5.0]]));

        $this->assertSame(Prioridade::Imediato, $r['plano']->firstWhere('componente', 'Transmissão')['prioridade']);
        $this->assertSame(Status::Vermelho, $r['situacao'][0]['status']);
    }

    public function test_umidade_alta_sem_tendencia_fica_programar(): void
    {
        // uma só medição acima do alerta: sem tendência, mas exige programar inspeção
        $r = $this->analisar($this->leituras([[], [], ['umidade_gabinete_pct' => 85.0]]));

        $item = $r['plano']->firstWhere('componente', 'Gabinete');
        $this->assertSame(Prioridade::Programar, $item['prioridade']);
        $this->assertNull($item['prazo_dias']);
    }

    public function test_infiltracao_registrada_aponta_o_gabinete(): void
    {
        $r = $this->analisar($this->leituras([[], [], ['ocorrencia' => 'Infiltração no gabinete']]));

        $this->assertSame(Prioridade::Imediato, $r['plano']->firstWhere('componente', 'Gabinete')['prioridade']);
    }

    public function test_corrente_normal_com_perda_de_correspondencia_aponta_o_fuso(): void
    {
        $ls = $this->leituras([[], [], ['ocorrencia' => 'Perda de correspondência']]);
        $r = $this->analisar($ls);

        $this->assertSame(Prioridade::Imediato, $r['plano']->firstWhere('componente', 'Fuso')['prioridade']);
        $this->assertSame(Status::Vermelho, $r['situacao'][0]['status']);
    }

    public function test_forca_caindo_aponta_embreagem(): void
    {
        $ls = $this->leituras(array_map(fn ($i) => ['forca_n' => 5800 - 100 * $i], range(0, 5)));
        $item = $this->analisar($ls)['plano']->firstWhere('componente', 'Embreagem');

        $this->assertNotNull($item);
        $this->assertSame(Prioridade::Programar, $item['prioridade']);
        $this->assertGreaterThan(0, $item['prazo_dias']);
    }

    public function test_sem_tendencia_com_datas_iguais_nao_extrapola(): void
    {
        $ls = $this->leituras(array_map(fn ($i) => ['isolamento_mohm' => 6.0 - $i], range(0, 4)), passoDias: 0);

        $this->assertNull($this->analisar($ls)['plano']->firstWhere('componente', 'Cabos'));
    }

    public function test_leitura_so_de_corrente_nao_apaga_o_isolamento_critico_medido_em_campo(): void
    {
        // Inspeção mediu 0,8 MΩ; depois chegam duas leituras de sensor sem isolamento nem força.
        $ls = $this->leituras([[], ['isolamento_mohm' => 0.8], ['isolamento_mohm' => null, 'forca_n' => null], ['isolamento_mohm' => null, 'forca_n' => null]]);
        $r = $this->analisar($ls);

        $this->assertSame(Status::Vermelho, $r['situacao'][0]['status']);
        $this->assertStringContainsString('0.80 MΩ', implode(' ', $r['situacao'][0]['alertas']));
        $this->assertSame(Prioridade::Imediato, $r['plano']->firstWhere('componente', 'Cabos')['prioridade']);
    }

    public function test_tendencia_usa_so_as_leituras_que_mediram_a_grandeza(): void
    {
        // Isolamento medido em 3 inspeções (6 → 4 MΩ em 8 dias), intercaladas com leituras de sensor sem isolamento.
        $ls = $this->leituras([
            ['isolamento_mohm' => 6.0], ['isolamento_mohm' => null], ['isolamento_mohm' => 5.0],
            ['isolamento_mohm' => null], ['isolamento_mohm' => 4.0],
        ]);
        $item = $this->analisar($ls, 30)['plano']->firstWhere('componente', 'Cabos');

        // −1 MΩ a cada 4 dias: de 4 MΩ até 1 MΩ faltam 12 dias.
        $this->assertEqualsWithDelta(12.0, $item['prazo_dias'], 0.01);
    }

    public function test_limites_dependem_da_versao_da_maquina(): void
    {
        // 6,8 s e 6.300 N: fora da faixa da agulha (5,5–6,5 s; 5.000–6.000 N), dentro da do jacaré (6,0–7,0 s; 5.500–6.500 N).
        $ls = $this->leituras(array_fill(0, 3, ['tempo_manobra_s' => 6.8, 'forca_n' => 6300.0]));
        $a = new Avaliador($this->config());

        $agulha = $a->analisar(collect(['MCH 1 AG' => $ls]), 15, ['MCH 1 AG' => 'agulha'])['situacao'][0];
        $jacare = $a->analisar(collect(['MCH 1 JM' => $ls]), 15, ['MCH 1 JM' => 'jacare'])['situacao'][0];

        $this->assertCount(2, $agulha['alertas']);
        $this->assertNotSame(Status::Verde, $agulha['status']);
        $this->assertSame([], $jacare['alertas']);
        $this->assertSame(Status::Verde, $jacare['status']);
    }

    public function test_situacoes_ordenadas_por_severidade(): void
    {
        $a = new Avaliador($this->config());
        $r = $a->analisar(collect([
            'MCH A' => $this->leituras(array_fill(0, 5, [])),
            'MCH B' => $this->leituras([[], [], ['isolamento_mohm' => 0.5]]),
        ]), 15);

        $this->assertSame(['MCH B', 'MCH A'], $r['situacao']->pluck('maquina')->all());
    }
}
