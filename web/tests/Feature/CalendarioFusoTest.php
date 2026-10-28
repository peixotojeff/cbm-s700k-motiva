<?php

namespace Tests\Feature;

use App\Services\Cbm\Calendario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ponto cego do monitoramento: inspeção dirigida do fuso na N2 dos AMVs estratégicos. */
class CalendarioFusoTest extends TestCase
{
    use RefreshDatabase;

    public function test_so_maquinas_estrategicas_recebem_a_inspecao_do_fuso_na_n2(): void
    {
        $this->post(route('admin.mock.carregar'));

        $linhas = app(Calendario::class)->calcular(15)['linhas']->keyBy(fn ($l) => $l['maquina']->codigo);

        // MCH 4123 JM está em X12 (estratégico) e teve N2 registrada; MCH 4082 JM está em X08 (não estratégico)
        $f = $linhas['MCH 4123 JM']['fuso'];
        $this->assertSame('N2', $f['nivel']);
        $this->assertNotNull($f['proxima']);
        $this->assertNull($linhas['MCH 4082 JM']['fuso']);

        $this->get(route('calendario.index'))->assertOk()
            ->assertSee('Inspeção dirigida do fuso em')->assertSee('ponto cego do monitoramento')
            ->assertSee('microswitch de fim de curso, pino e roletes do fuso');
    }
}
