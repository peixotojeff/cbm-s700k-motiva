<?php

namespace Tests\Feature;

use App\Services\Cbm\AssertividadeCampo;
use App\Services\Cbm\RegistrarLeitura;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssertividadeCampoTest extends TestCase
{
    use RefreshDatabase;

    /** Leituras diárias de uma máquina; a última leva a ocorrência informada. */
    private function maquina(string $codigo, array $leituras, string $ocorrencia): void
    {
        $t0 = CarbonImmutable::parse('2026-10-01 08:00');
        $r = new RegistrarLeitura;
        foreach ([...$leituras, []] as $i => $extra) {
            $r->executar($extra + [
                'maquina' => $codigo, 'medido_em' => $t0->addDays($i), 'tempo_manobra_s' => 6.5, 'forca_n' => 6000, 'isolamento_mohm' => 10,
                'ocorrencia' => $i === count($leituras) ? $ocorrencia : 'Leitura de monitoramento', 'origem' => 'manual',
            ]);
        }
    }

    public function test_placar_de_campo_separa_acertos_falhas_sem_aviso_e_alertas_sem_defeito(): void
    {
        $normal = array_fill(0, 3, []);
        $this->maquina('MCH 1 JM', $normal, 'Perda de correspondência');                                   // verde antes: sem aviso
        $this->maquina('MCH 2 JM', [[], [], ['isolamento_mohm' => 0.8]], 'Inspeção após alerta: sem defeito'); // vermelho antes: falso da intervenção
        $this->maquina('MCH 3 JM', [[], [], ['vibracao_mm_s' => 3.0]], 'Inspeção após alerta: sem defeito');  // amarelo antes: só falso geral
        $this->maquina('MCH 4 JM', [[], [], ['tempo_manobra_s' => 7.2]], 'Inspeção após alerta: defeito confirmado');
        $this->maquina('MCH 5 JM', [[], [], ['isolamento_mohm' => 0.8]], 'Manutenção corretiva');            // falha já em alerta: acerto

        $p = app(AssertividadeCampo::class)->calcular();

        $this->assertSame(['acertos' => 2, 'sem_aviso' => 1, 'falsos' => 2, 'falsos_vermelho' => 1], array_intersect_key($p, array_flip(['acertos', 'sem_aviso', 'falsos', 'falsos_vermelho'])));
        $this->assertSame(5, $p['decisoes']);
        $this->assertNull($p['geral']); // menos de 20 decisões: sem porcentagem
    }

    public function test_porcentagem_aparece_com_decisoes_suficientes(): void
    {
        foreach (range(1, 20) as $n) {
            $this->maquina("MCH $n AG", [[], [], ['isolamento_mohm' => 0.8]], $n <= 18 ? 'Inspeção após alerta: defeito confirmado' : 'Inspeção após alerta: sem defeito');
        }

        $p = app(AssertividadeCampo::class)->calcular();

        $this->assertSame(0.9, $p['geral']);
        $this->assertSame(0.9, $p['intervencao']);
    }

    public function test_painel_mostra_assertividade_em_campo_mesmo_sem_o_servico_do_modelo(): void
    {
        config(['cbm.ml_url' => 'http://127.0.0.1:1']);

        $this->get(route('simulacao.index'))->assertOk()
            ->assertSee('Assertividade em campo')->assertSee('coletando dados: 0 de 20 decisões');
    }
}
