<?php

namespace Tests\Feature;

use App\Models\Leitura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Garantias de usabilidade e acessibilidade (revisão com a UI/UX Pro Max). */
class UxTest extends TestCase
{
    use RefreshDatabase;

    private function leitura(array $extra = []): array
    {
        return $extra + [
            'maquina' => 'MCH 4123 JM', 'data' => '2026-10-01', 'hora' => '08:00',
            'tempo_manobra_s' => 6.5, 'forca_n' => 6000, 'isolamento_mohm' => 9, 'ocorrencia' => 'Inspeção N1',
        ];
    }

    public function test_erro_aparece_junto_ao_campo_e_no_resumo_com_link(): void
    {
        $this->followingRedirects()->from(route('registro.create'))
            ->post(route('registro.store'), $this->leitura(['maquina' => '', 'tempo_manobra_s' => 99]))
            ->assertOk()
            ->assertSee('id="resumo-erros"', false)->assertSee('Corrija 2 campos para continuar:')
            ->assertSee('href="#maquina"', false)->assertSee('href="#tempo_manobra_s"', false)
            ->assertSee('id="erro-maquina"', false)->assertSee('id="erro-tempo_manobra_s"', false)
            ->assertSee('aria-invalid="true" aria-describedby="ajuda-maquina erro-maquina"', false)
            ->assertSee('O campo tempo de manobra deve estar entre 0 e 60.');
    }

    public function test_envio_repetido_nao_grava_a_mesma_leitura_duas_vezes(): void
    {
        $this->post(route('registro.store'), $this->leitura())->assertRedirect();
        $this->post(route('registro.store'), $this->leitura())->assertRedirect()->assertSessionHas('sucesso', fn ($m) => str_contains($m, 'nada foi duplicado'));

        $this->assertSame(1, Leitura::count());
        $this->post(route('registro.store'), $this->leitura(['hora' => '09:00']));
        $this->assertSame(2, Leitura::count());
    }

    public function test_botao_de_envio_tem_estado_de_carregamento(): void
    {
        $this->get(route('registro.create'))->assertSee('data-enviando-texto="Registrando…"', false);
    }

    public function test_telas_sem_emoji_como_icone_e_com_atalho_para_o_conteudo(): void
    {
        $this->post(route('admin.mock.carregar'));

        foreach (['home', 'registro.create', 'plano.index', 'calendario.index', 'estoque.index', 'situacao.index', 'casos.index', 'documentos.index', 'admin.index'] as $rota) {
            $html = $this->get(route($rota))->assertOk()->assertSee('Pular para o conteúdo')->assertSee('id="conteudo"', false)->getContent();
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{25B6}]/u', $html, "emoji em $rota");
        }
        $this->get(route('situacao.show', 'MCH 4123 JM'))->assertOk()->assertSee('aria-label="Excluir a leitura de', false);
    }

    public function test_menu_agrupa_por_tarefa_e_avisa_urgencias_com_texto(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get(route('home'))->assertOk()
            ->assertSeeInOrder(['Campo', 'Registrar leitura', 'Situação das máquinas', 'Planejamento', 'Plano de manutenção'])
            ->assertSee('máquinas em vermelho)')->assertSee('intervenções imediatas)')
            ->assertDontSee('onchange="location', false); // menu do celular não navega ao rolar as opções
    }

    public function test_situacao_filtra_por_status_e_busca_e_orienta_quando_vazio(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get(route('situacao.index', ['status' => 'Vermelho']))->assertOk()
            ->assertSee('MCH 4134 JM')->assertDontSee('>MCH 4083 JM<', false)->assertSee('em vermelho.');
        $this->get(route('situacao.index', ['q' => '4083']))->assertSee('1 de 11 máquinas')->assertSee('>MCH 4083 JM<', false);
        $this->get(route('situacao.index', ['q' => 'XYZ']))->assertSee('Nenhuma máquina encontrada')->assertSee('Registre a primeira leitura');
    }

    public function test_pagina_da_maquina_leva_ao_registro_ja_preenchido(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get(route('situacao.show', 'MCH 4083 JM'))->assertSee(route('registro.create', ['maquina' => 'MCH 4083 JM']), false);
        $this->get(route('registro.create', ['maquina' => 'MCH 4083 JM']))->assertSee('value="MCH 4083 JM"', false);
    }

    public function test_status_tem_icone_alem_da_cor(): void
    {
        $this->post(route('admin.mock.carregar'));

        // selo do status: ícone SVG + texto (não só cor)
        $this->get(route('situacao.index'))->assertSee('<svg', false)->assertSee('Vermelho')->assertSee('Amarelo');
    }
}
