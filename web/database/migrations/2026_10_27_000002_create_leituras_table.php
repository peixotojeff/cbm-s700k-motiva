<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leituras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maquina_id')->constrained('maquinas')->cascadeOnDelete();
            $table->dateTime('medido_em');
            $table->double('tempo_manobra_s');
            $table->double('forca_n');
            $table->double('isolamento_mohm');
            $table->double('corrente_pico')->nullable();
            $table->double('corrente_media')->nullable();
            $table->string('ocorrencia')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('responsavel')->nullable(); // "MOCK" marca dados de exemplo
            $table->timestamps();

            $table->index(['maquina_id', 'medido_em']);
            $table->index('responsavel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leituras');
    }
};
