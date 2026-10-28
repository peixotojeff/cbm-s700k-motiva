<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Peças de reposição por componente do plano de manutenção (estoque e prazo de reposição). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pecas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nome');
            $table->string('componente');                       // chave de config('cbm.componentes'): isolamento, barras, ...
            $table->unsignedInteger('qtd_por_intervencao')->default(1);
            $table->unsignedInteger('estoque')->default(0);
            $table->unsignedInteger('estoque_minimo')->default(0);
            $table->unsignedInteger('prazo_reposicao_dias');
            $table->string('fonte')->nullable();                // de onde veio a peça (manual, catálogo)
            $table->boolean('ficticio')->default(true);         // quantidades e prazos de exemplo
            $table->timestamps();
            $table->index('componente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pecas');
    }
};
