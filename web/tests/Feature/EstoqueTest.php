<?php

namespace Tests\Feature;

use App\Enums\Status;
use App\Models\Peca;
use App\Services\Cbm\Estado;
use App\Services\Cbm\PrevisaoEstoque;
use Carbon\CarbonImmutable;
use Database\Seeders\PecaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function peca(string $componente, int $estoque, int $minimo, int $prazo): Peca
    {
        return Peca::create(['codigo' => "P-$componente", 'nome' => "Peça $componente", 'componente' => $componente,
            'estoque' => $estoque, 'estoque_minimo' => $minimo, 'prazo_reposicao_dias' => $prazo]);
    }

    /** @param  list<array{0:string,1:string,2:?float}>  $itens  [máquina, chave, prazo] */
    private function prever(array $itens): \Illuminate\Support\Collection
    {
        $plano = collect($itens)->map(fn ($i) => ['maquina' => $i[0], 'chave' => $i[1], 'prazo_dias' => $i[2]]);
        $this->mock(Estado::class)->shouldReceive('atual')->andReturn(['situacao' => collect(), 'plano' => $plano]);

        return app(PrevisaoEstoque::class)->calcular(30, CarbonImmutable::parse('2026-10-27'))->keyBy(fn ($l) => $l['peca']->componente);
    }

    public function test_classifica_ruptura_pedir_ate_minimo_e_ok(): void
    {
        $this->peca('motor', 0, 1, 45);     // precisa em 10 d, chega em 45 d
        $this->peca('embreagem', 1, 0, 5);  // 2ª troca em 20 d, pedir até 20 - 5 = 15 d
        $this->peca('barras', 2, 2, 7);     // não falta, mas fica abaixo do mínimo
        $this->peca('agulha', 4, 1, 3);     // folgado

        $r = $this->prever([
            ['MCH 1', 'motor', 10.0], ['MCH 1', 'embreagem', 2.0], ['MCH 2', 'embreagem', 20.0],
            ['MCH 3', 'barras', null], ['MCH 4', 'agulha', 50.0],
        ]);

        $this->assertSame(Status::Vermelho, $r['motor']['status']);
        $this->assertSame(Status::Amarelo, $r['embreagem']['status']);
        $this->assertSame('Pedir até 11/11/2026', $r['embreagem']['situacao']);
        $this->assertSame('MCH 2', $r['embreagem']['falta_maquina']);
        $this->assertStringStartsWith('Pedir agora', $r['barras']['situacao']);
        $this->assertSame(1, $r['barras']['demanda_horizonte']); // sem prazo conta como imediato
        $this->assertSame(Status::Verde, $r['agulha']['status']);
        $this->assertSame(0, $r['agulha']['demanda_horizonte']); // fora do horizonte não entra no saldo
    }

    public function test_pagina_mostra_aviso_de_dados_ficticios(): void
    {
        $this->seed(PecaSeeder::class);
        $this->post(route('admin.mock.carregar'));

        $this->get(route('estoque.index'))->assertOk()
            ->assertSee('Dados fictícios')->assertSee('Embreagem de transmissão')->assertSee('Ruptura prevista');
    }
}
