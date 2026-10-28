<?php

namespace Tests\Feature;

use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cbm.api_token' => self::TOKEN]);
    }

    private function leitura(array $extra = []): array
    {
        return $extra + ['maquina' => 'MCH 9001 JM', 'medido_em' => '2026-10-01 08:00', 'tempo_manobra_s' => 6.5, 'forca_n' => 6000, 'isolamento_mohm' => 9.5];
    }

    private function api(): self
    {
        return $this->withToken(self::TOKEN);
    }

    public function test_api_exige_token(): void
    {
        $this->postJson('/api/v1/leituras', $this->leitura())->assertUnauthorized();
        $this->withToken('errado')->postJson('/api/v1/leituras', $this->leitura())->assertUnauthorized();

        config(['cbm.api_token' => null]);
        $this->api()->postJson('/api/v1/leituras', $this->leitura())->assertStatus(503);
        $this->assertSame(0, Leitura::count());
    }

    public function test_api_registra_leitura_e_devolve_avaliacao(): void
    {
        $this->api()->postJson('/api/v1/leituras', $this->leitura())
            ->assertCreated()
            ->assertJsonPath('registradas', 1)
            ->assertJsonPath('maquinas.0.codigo', 'MCH 9001 JM')
            ->assertJsonPath('maquinas.0.tipo', 'jacare')
            ->assertJsonPath('maquinas.0.status', 'Aguardando');

        $l = Leitura::first();
        $this->assertSame('api', $l->origem);
        $this->assertSame('Leitura de monitoramento', $l->ocorrencia);
        $this->assertSame('API', $l->responsavel);
    }

    public function test_api_aceita_leitura_de_sensor_so_com_tempo_e_corrente(): void
    {
        $sensor = ['maquina' => 'MCH 9001 JM', 'medido_em' => '2026-10-02 08:00', 'tempo_manobra_s' => 6.5, 'corrente_pico' => 4.1, 'corrente_media' => 1.8];

        $this->api()->postJson('/api/v1/leituras', $sensor)->assertCreated()->assertJsonPath('registradas', 1);

        $l = Leitura::first();
        $this->assertNull($l->forca_n);
        $this->assertNull($l->isolamento_mohm);
        $this->get(route('situacao.show', 'MCH 9001 JM'))->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_api_reenvio_da_mesma_leitura_e_ignorado(): void
    {
        $this->api()->postJson('/api/v1/leituras', $this->leitura())->assertCreated();

        // Reenvio (ex.: falha de rede) com data em outro formato, e duplicata dentro do mesmo lote.
        $lote = ['leituras' => [
            $this->leitura(['medido_em' => '2026-10-01T08:00:00', 'maquina' => 'mch 9001 jm']),
            $this->leitura(['medido_em' => '2026-10-03 08:00']),
            $this->leitura(['medido_em' => '2026-10-03 08:00']),
        ]];
        $this->api()->postJson('/api/v1/leituras', $lote)
            ->assertCreated()
            ->assertJsonPath('registradas', 1)
            ->assertJsonPath('duplicadas_ignoradas', 2);

        $this->api()->postJson('/api/v1/leituras', $this->leitura())
            ->assertOk()
            ->assertJsonPath('registradas', 0)
            ->assertJsonPath('maquinas.0.codigo', 'MCH 9001 JM');
        $this->assertSame(2, Leitura::count());
    }

    public function test_api_lote_e_tudo_ou_nada(): void
    {
        $lote = ['leituras' => [$this->leitura(), $this->leitura(['forca_n' => 'x'])]];

        $this->api()->postJson('/api/v1/leituras', $lote)
            ->assertUnprocessable()
            ->assertJsonStructure(['erro', 'detalhes' => ['1' => ['forca_n']]]);
        $this->assertSame(0, Leitura::count());
    }

    public function test_api_lote_com_isolamento_caindo_indica_intervencao(): void
    {
        $lote = ['leituras' => array_map(fn ($i) => $this->leitura([
            'medido_em' => sprintf('2026-10-%02d 08:00', 1 + 2 * $i), 'isolamento_mohm' => 6 - $i,
        ]), range(0, 4))];

        $this->api()->postJson('/api/v1/leituras?h=15', $lote)
            ->assertCreated()
            ->assertJsonPath('registradas', 5)
            ->assertJsonPath('maquinas.0.precisa_intervencao', true)
            ->assertJsonPath('maquinas.0.plano.0.componente', 'Cabos e enrolamentos do motor');

        $this->api()->getJson('/api/v1/maquinas/MCH 9001 JM')->assertOk()->assertJsonPath('status', 'Amarelo');
        $this->api()->getJson('/api/v1/maquinas')->assertOk()->assertJsonCount(1, 'maquinas');
        $this->api()->get('/api/v1/maquinas/INEXISTENTE')->assertNotFound()->assertHeader('content-type', 'application/json');
    }

    public function test_versao_inferida_pelo_sufixo_e_editavel_no_cadastro(): void
    {
        $this->api()->postJson('/api/v1/leituras', $this->leitura(['maquina' => 'MCH 1 AG']));
        $this->assertSame('agulha', Maquina::first()->tipo);

        $this->patch(route('situacao.update', 'MCH 1 AG'), ['tipo' => 'jacare', 'linha' => 'Linha 4 - Amarela', 'local' => 'x12'])
            ->assertRedirect(route('situacao.show', 'MCH 1 AG'));

        $m = Maquina::first();
        $this->assertSame(['jacare', 'X12', 'estrategico'], [$m->tipo, $m->local, $m->grupo_pmp]);
    }

    public function test_calendario_recomenda_antecipar_quando_condicao_vence_antes_da_preventiva(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get(route('calendario.index'))->assertOk()
            ->assertSee('Calendário × condição')->assertSee('Antecipar')->assertSee('dias de antecedência')
            ->assertSee('Preventiva atrasada')
            ->assertSee('Horizonte de previsão')->assertSee('nunca sugere adiar', false);
    }
}
