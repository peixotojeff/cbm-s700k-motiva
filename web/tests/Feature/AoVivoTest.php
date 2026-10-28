<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AoVivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_modo_ao_vivo_atualiza_a_pagina_da_maquina(): void
    {
        $this->post(route('admin.mock.carregar'));

        $this->get(route('situacao.show', 'MCH 4123 JM'))->assertOk()
            ->assertSee('Acompanhar ao vivo')->assertDontSee('http-equiv="refresh"', false);
        $this->get(route('situacao.show', ['MCH 4123 JM', 'ao_vivo' => 1]))->assertOk()
            ->assertSee('http-equiv="refresh"', false)->assertSee('Ao vivo');
    }
}
