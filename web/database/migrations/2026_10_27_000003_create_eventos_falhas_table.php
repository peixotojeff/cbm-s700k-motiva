<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_falhas', function (Blueprint $table) {
            $table->id();
            $table->string('maquina');
            $table->date('data');
            $table->string('fonte');
            $table->string('slide');
            $table->string('categoria');
            $table->string('anomalia_corrente'); // Sim | Não | n/d
            $table->text('sintoma');
            $table->text('causa_acao');
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_falhas');
    }
};
