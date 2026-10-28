<?php

namespace Tests\Feature;

use App\Models\Leitura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Eventos do log do intertravamento: chegam sozinhos e acionam a regra do fuso. */
class EventoApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cbm.api_token' => self::TOKEN]);
    }

    private function leituras(): void
    {
        foreach (['2026-10-01 08:00', '2026-10-02 08:00', '2026-10-03 08:00'] as $t) {
            $this->withToken(self::TOKEN)->postJson('/api/v1/leituras', ['maquina' => 'MCH 4134 JM', 'medido_em' => $t, 'tempo_manobra_s' => 6.5])->assertCreated();
        }
    }

    public function test_perda_de_correspondencia_do_intertravamento_aciona_o_fuso_na_hora(): void
    {
        $this->leituras();
        $evento = ['maquina' => 'mch 4134 jm', 'ocorrido_em' => '2026-10-03T14:10:00', 'evento' => 'perda_correspondencia'];

        $r = $this->withToken(self::TOKEN)->postJson('/api/v1/eventos', $evento)->assertCreated()
            ->assertJsonPath('registradas', 1)->assertJsonPath('maquinas.0.codigo', 'MCH 4134 JM')
            ->assertJsonPath('maquinas.0.status', 'Vermelho');
        $this->assertContains('Fuso de movimentação, microswitch e roletes', array_column($r->json('maquinas.0.plano'), 'componente'));

        $l = Leitura::latest('medido_em')->first();
        $this->assertSame(['Perda de correspondência', 'intertravamento', 'INTERTRAVAMENTO'], [$l->ocorrencia, $l->origem, $l->responsavel]);
        $this->assertNull($l->tempo_manobra_s);

        // reenvio do mesmo evento (fila do coletor) não duplica
        $this->withToken(self::TOKEN)->postJson('/api/v1/eventos', ['eventos' => [$evento, $evento]])->assertOk()
            ->assertJsonPath('registradas', 0)->assertJsonPath('duplicadas_ignoradas', 2);
        $this->assertSame(4, Leitura::count());
    }

    public function test_lote_invalido_nao_grava_nada_e_exige_token(): void
    {
        $this->postJson('/api/v1/eventos', ['maquina' => 'MCH 1 JM', 'ocorrido_em' => '2026-10-03', 'evento' => 'travamento'])->assertUnauthorized();
        $this->withToken(self::TOKEN)->postJson('/api/v1/eventos', ['eventos' => [
            ['maquina' => 'MCH 1 JM', 'ocorrido_em' => '2026-10-03', 'evento' => 'travamento'],
            ['maquina' => 'MCH 1 JM', 'ocorrido_em' => '2026-10-03', 'evento' => 'explosao'],
        ]])->assertStatus(422)->assertJsonPath('detalhes.1.evento.0', fn ($m) => str_contains($m, 'inválido'));
        $this->assertSame(0, Leitura::count());
    }

    public function test_maquina_com_evento_sem_medicao_abre_nas_telas(): void
    {
        $this->leituras();
        $this->withToken(self::TOKEN)->postJson('/api/v1/eventos', ['maquina' => 'MCH 4134 JM', 'ocorrido_em' => '2026-10-04 09:00', 'evento' => 'travamento'])->assertCreated();

        $this->get(route('home'))->assertOk();
        $this->get(route('situacao.show', 'MCH 4134 JM'))->assertOk()->assertSee('Intertravamento (evento)')->assertSee('Travamento');
    }
}
