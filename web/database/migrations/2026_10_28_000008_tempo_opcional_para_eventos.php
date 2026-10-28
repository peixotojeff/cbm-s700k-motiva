<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Eventos do intertravamento (perda de correspondência, travamento) não trazem medição: o tempo de manobra fica opcional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->double('tempo_manobra_s')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->double('tempo_manobra_s')->nullable(false)->change();
        });
    }
};
