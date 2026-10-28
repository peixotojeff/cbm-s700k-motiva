<?php

namespace Tests\Feature;

use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FluxoTest extends TestCase
{
    use RefreshDatabase;

    private function registrar(array $extra = [])
    {
        return $this->post(route('registro.store'), $extra + [
            'maquina' => 'mch  4123 jm', 'responsavel' => 'Ana', 'data' => '2026-10-01', 'hora' => '08:00',
            'tempo_manobra_s' => 6.25, 'forca_n' => 5750, 'isolamento_mohm' => 10, 'corrente_pico' => 4, 'corrente_media' => 1.8,
            'ocorrencia' => 'Inspeção N1',
        ]);
    }

    public function test_home_vazia_orienta_o_primeiro_registro(): void
    {
        $this->get('/')->assertOk()->assertSee('Bem-vindo ao CBM S700K')->assertSee('Nenhuma leitura registrada ainda');
        $this->get('/registrar')->assertOk()->assertSee('Registrar leitura');
    }

    public function test_home_resume_kpis_e_alertas(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get('/')->assertOk()
            ->assertSee('intervenções imediatas')->assertSee('Peças a pedir')
            ->assertSee('Alertas')->assertSee('MCH 4124 JM')->assertSee('agir agora')
            ->assertSee('Últimas leituras');
    }

    public function test_home_alerta_maquina_sem_leitura_ha_muito_tempo(): void
    {
        $this->registrar(['data' => now()->subDays(40)->format('Y-m-d')]);

        $this->get('/')->assertOk()->assertSee('sem leitura há 40 dias');
    }

    public function test_registro_normaliza_codigo_e_mostra_veredito(): void
    {
        $this->registrar()->assertRedirect(route('registro.create', ['maquina' => 'MCH 4123 JM']));

        $this->assertSame('MCH 4123 JM', Maquina::first()->codigo);
        $this->get(route('registro.create', ['maquina' => 'MCH 4123 JM']))
            ->assertOk()->assertSee('Resultado da análise')->assertSee('1 de 3 leituras');
    }

    public function test_registro_aceita_condicao_e_aponta_o_componente(): void
    {
        $this->registrar(['vibracao_mm_s' => 5.2, 'umidade_gabinete_pct' => 50]);

        $l = Leitura::first();
        $this->assertSame(5.2, $l->vibracao_mm_s);
        $this->assertNull($l->temperatura_motor_c);
        $this->get(route('registro.create', ['maquina' => 'MCH 4123 JM']))
            ->assertOk()->assertSee('Rolamentos e engrenagens da transmissão')->assertSee('Vibração do motor (mm/s)');
        $this->get(route('situacao.show', 'MCH 4123 JM'))->assertOk()->assertSee('g-vibracao_mm_s')->assertDontSee('g-temperatura_motor_c');
        $this->from('/registrar')->registrar(['umidade_gabinete_pct' => 120])->assertSessionHasErrors('umidade_gabinete_pct');
    }

    public function test_registro_valida_campos_obrigatorios(): void
    {
        $this->from('/registrar')->post(route('registro.store'), ['maquina' => ''])
            ->assertSessionHasErrors(['maquina', 'tempo_manobra_s', 'forca_n', 'isolamento_mohm']);
        $this->assertSame(0, Leitura::count());
    }

    public function test_cinco_leituras_com_isolamento_caindo_geram_plano_e_veredito(): void
    {
        foreach (range(0, 4) as $i) {
            $this->registrar(['data' => '2026-10-'.sprintf('%02d', 1 + 2 * $i), 'isolamento_mohm' => 6 - $i, 'tempo_manobra_s' => 6.1 + 0.07 * $i,
                'ocorrencia' => $i === 4 ? 'Perda de correspondência' : 'Inspeção N1']);
        }

        $this->get(route('registro.create', ['maquina' => 'MCH 4123 JM', 'h' => 15]))
            ->assertOk()->assertSee('PRECISA DE INTERVENÇÃO')->assertSee('Cabos e enrolamentos do motor');
        $this->get(route('plano.index'))->assertOk()->assertSee('Fuso de movimentação');
        $this->get(route('plano.csv'))->assertOk()->assertDownload('plano_manutencao.csv');
        $this->get(route('situacao.index'))->assertOk()->assertSee('MCH 4123 JM');
        $this->get(route('situacao.show', 'MCH 4123 JM'))->assertOk()->assertSee('Histórico de leituras');
    }

    public function test_horizonte_e_guardado_na_sessao_e_ignora_valores_invalidos(): void
    {
        $this->get('/plano?h=30')->assertSee('próximos 30 dias');
        $this->get('/plano')->assertSee('próximos 30 dias');
        $this->get('/plano?h=999')->assertSee('próximos 30 dias');
    }

    public function test_seletor_de_horizonte_so_nas_paginas_de_situacao_e_plano(): void
    {
        $this->registrar();

        $this->get(route('situacao.index'))->assertSee('Horizonte de previsão');
        $this->get(route('situacao.show', 'MCH 4123 JM'))->assertSee('Horizonte de previsão');
        $this->get(route('plano.index'))->assertSee('Horizonte de previsão');
        foreach (['home', 'registro.create', 'casos.index', 'documentos.index', 'admin.index'] as $rota) {
            $this->get(route($rota))->assertOk()->assertDontSee('Horizonte de previsão');
        }
    }

    public function test_excluir_leitura(): void
    {
        $this->registrar();
        $l = Leitura::first();

        $this->delete(route('situacao.leitura.destroy', [$l->maquina->codigo, $l->id]))->assertRedirect();
        $this->assertSame(0, Leitura::count());
    }

    public function test_importacao_csv_aceita_ponto_e_virgula_e_relata_erros(): void
    {
        $csv = "maquina;medido_em;tempo_manobra_s;forca_n;isolamento_mohm\n"
            ."MCH 1;2026-10-01 08:00;6,25;5750;9,5\n"
            ."MCH 1;2026-10-03 08:00;99;5750;9,5\n";
        $arquivo = UploadedFile::fake()->createWithContent('leituras.csv', $csv);

        $this->post(route('admin.importar'), ['arquivo' => $arquivo])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('importacao', fn ($r) => $r['importadas'] === 1 && count($r['erros']) === 1 && $r['erros'][0]['linha'] === 3);
        $this->assertSame(6.25, Leitura::first()->tempo_manobra_s);
    }

    public function test_importacao_com_colunas_ausentes(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('x.csv', "maquina,medido_em\nMCH 1,2026-10-01\n");

        $this->post(route('admin.importar'), ['arquivo' => $arquivo])
            ->assertSessionHas('importacao', fn ($r) => in_array('tempo_manobra_s', $r['colunas_ausentes'], true) && $r['importadas'] === 0);
    }

    public function test_mock_carrega_e_remove_sem_tocar_nos_dados_reais(): void
    {
        $this->registrar(['responsavel' => 'Ana']);
        $this->post(route('admin.mock.carregar'))->assertRedirect();
        $this->assertGreaterThan(50, Leitura::where('responsavel', 'MOCK')->count());

        $this->delete(route('admin.mock.remover'))->assertRedirect();
        $this->assertSame(1, Leitura::count());
    }

    public function test_casos_reais_e_documentos_renderizam(): void
    {
        $this->seed(\Database\Seeders\EventoFalhaSeeder::class);

        $this->get(route('casos.index'))->assertOk()->assertSee('Casos reais de falha')->assertSee('16');
        $this->get(route('casos.index', ['maquina' => ['MCH 4124 JM']]))->assertOk();
        $this->get(route('documentos.index'))->assertOk()->assertSee('Base documental');
        $this->get(route('documentos.download', ['fornecedor', 9999]))->assertNotFound();
        $this->get(route('documentos.download', ['inexistente', 0]))->assertNotFound();
    }

    public function test_simulacao_consome_servico_de_ml(): void
    {
        $item = [
            'maquina_id' => 'C0001', 'maquina' => 'MCH 4123 JM', 'tipo' => 'jacare', 'ciclo' => 119, 'severidade' => 'Vermelho', 'risco' => 0.97,
            'modo_provavel' => 'atrito_travamento', 'modo_rotulo' => 'Atrito / travamento no fim de curso', 'isolamento_mohm' => 5.0,
            'fatores' => 'tendência do tempo de manobra (+2.7)', 'ciclos_ate_1mohm' => 70.0, 'acao' => 'Lubrificar placas roletadas e barras.',
            'desfecho_real' => 'falha', 'modo_real' => 'atrito_travamento', 'modo_real_rotulo' => 'Atrito / travamento no fim de curso',
            'tendencia' => [0.1, 0.4, 0.9, 0.97],
        ];
        Http::fake([
            '*/meta' => Http::response(['ciclo_min' => 5, 'ciclo_max' => 119, 'isolamento_min' => 1.0, 'horizonte_ciclos' => 10, 'intervalo_h' => 6, 'metricas' => [
                'auc' => 0.9, 'limiar_vermelho' => 0.9, 'taxa_antecipacao' => 0.683, 'falhas_antecipadas' => 205, 'falhas_teste' => 300,
                'antecedencia_max_h' => 180, 'taxa_alarme_falso' => 0.083, 'saudaveis_com_alarme' => 12, 'saudaveis_teste' => 145,
                'alarme_falso_max' => 0.1, 'acerto_modo_geral' => 0.908, 'historicos_teste' => 450,
                'regra_persistencia' => 'Liga com 2 das últimas 3 leituras acima do limiar', 'antecedencia_mediana_h' => 72, 'trocas_status_saudaveis' => 24,
                'regra_niveis' => 'Vermelho (intervir): risco em até 2,5 dias. Amarelo (programar inspeção): risco em até 7,5 dias.',
                'limiar_amarelo' => 0.87, 'horizonte_aviso_ciclos' => 30, 'horizonte_ciclos' => 10, 'intervalo_h' => 6, 'saida_histerese' => 0.6,
                'teto_aviso_falso' => ['vermelho' => 0.1, 'amarelo' => 0.2],
                'folga_amarelo_vermelho_h' => 30, 'avisos_com_folga_reposicao' => 0.004, 'dias_reposicao' => 5,
                'vermelho' => ['taxa_antecipacao' => 0.65, 'antecedencia_mediana_h' => 66, 'taxa_alarme_falso' => 0.05, 'saudaveis_com_alarme' => 8, 'trocas_status_saudaveis' => 16],
                'sem_persistencia' => ['taxa_antecipacao' => 0.6, 'antecedencia_mediana_h' => 66, 'taxa_alarme_falso' => 0.08, 'saudaveis_com_alarme' => 10, 'trocas_status_saudaveis' => 32],
            ]]),
            '*/casos' => Http::response([
                'total' => 1500, 'falhas' => 1000, 'saudaveis' => 500, 'maquinas_fisicas' => 76, 'jacare' => 0.87, 'com_precursor_fuso' => 140,
                'periodo' => ['2016-01-01', '2017-12-31'],
                'por_modo' => [['modo' => 'fuso_retorno', 'rotulo' => 'Retorno do fuso após travamento', 'casos' => 238, 'participacao' => 0.24,
                    'casos_reais_base' => [8, 9, 16], 'teste_casos' => 84, 'antecipadas' => 33, 'taxa_antecipacao' => 0.39,
                    'antecedencia_mediana_h' => 54, 'acerto_modo' => 0.88]],
            ]),
            '*/simulacao/*' => Http::response($item + [
                'curva_base' => ['t' => [0, 1], 'a' => [1, 2], 'ciclo' => 5], 'curva_atual' => ['t' => [0, 1], 'a' => [1, 3], 'ciclo' => 119],
                'isolamento' => ['ciclo' => [1, 2], 'mohm' => [9, 8]], 'tempo' => ['ciclo' => [1, 2], 's' => [6.5, 6.9]],
                'caso' => ['desfecho' => 'falha', 'data_falha' => '2017-08-17 10:00:00', 'severidade' => 0.8, 'ciclos_degradacao' => 30,
                    'anomalia_corrente' => 'Sim', 'sintoma' => 'Travamento com elevação da corrente', 'causa_acao' => 'Máquina substituída', 'evento_base' => 10],
            ]),
            '*/simulacao*' => Http::response(['ciclo' => 119, 'total' => 1500, 'contagem' => ['Vermelho' => 1, 'Amarelo' => 0, 'Verde' => 0], 'itens' => [$item]]),
        ]);

        $this->get(route('simulacao.index'))->assertOk()
            ->assertSee('C0001')->assertSee('97%')->assertSee('Lubrificar placas roletadas e barras.')
            ->assertSee('68%')->assertSee('Base de casos simulados')->assertSee('nº 8, 9, 16')
            ->assertSee('falhou: Atrito / travamento no fim de curso')->assertSee('Inspirado no caso real nº 10')
            ->assertSee('Regras dos alertas')->assertSee('Programar inspeção')->assertSee('Intervir')
            ->assertSee('O que dispara')->assertSee('Quando desliga')->assertSee('2 das últimas 3 leituras')
            ->assertSee('7,5 dias')->assertSee('2,5 dias')->assertSee('0,87')->assertSee('Perda de correspondência')
            ->assertSee('Reposição de peças em 5 dias')->assertSee('0,4%')
            ->assertSee('Amarelo ou vermelho')->assertSee('Só vermelho')->assertSee('3,2')->assertSee('Falhas com aviso')->assertSee('<polyline', false)
            ->assertSee(route('simulacao.animacao'));
    }

    public function test_simulacao_ao_vivo_recebe_a_linha_do_tempo(): void
    {
        Http::fake([
            '*/animacao' => Http::response(['ciclos' => [5, 6, 7], 'intervalo_h' => 6, 'antecedencia_max_h' => 180, 'regra' => 'Liga com 2 das últimas 3',
                'historicos' => [['id' => 'C0001', 'maquina' => 'MCH 4123 JM', 'desfecho' => 'falha', 'modo' => 'atrito_travamento',
                    'modo_rotulo' => 'Atrito', 'ciclo_falha' => 7, 'antecedencia_h' => 12, 'estados' => 'ARF']]]),
            '*/meta' => Http::response(['metricas' => ['taxa_antecipacao' => 0.74, 'taxa_alarme_falso' => 0.11]]),
        ]);

        $this->get(route('simulacao.animacao'))->assertOk()
            ->assertSee('Simulação ao vivo')->assertSee('"estados":"ARF"', false)->assertSee('74% das falhas antecipadas')
            ->assertSee('Assertividade do modelo')->assertSee('k-assert', false)
            ->assertSee('Como ler este gráfico')->assertSee('total acumulado')->assertSee('situação no ciclo')
            ->assertSee('No amarelo, é uma inspeção programada sem necessidade');
    }

    public function test_simulacao_ao_vivo_sem_servico_de_ml(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('recusada'));

        $this->get(route('simulacao.animacao'))->assertOk()->assertSee('indisponível');
    }

    public function test_simulacao_degrada_com_aviso_quando_ml_esta_fora(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('recusada'));

        $this->get(route('simulacao.index'))->assertOk()->assertSee('indisponível')->assertSee('uvicorn ml.main:app');
    }
}
