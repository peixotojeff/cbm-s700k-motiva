<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Grandezas de condição pedidas no kick-off: vibração e temperatura do motor, umidade no gabinete (todas opcionais). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->float('vibracao_mm_s')->nullable()->after('corrente_media');
            $table->float('temperatura_motor_c')->nullable()->after('vibracao_mm_s');
            $table->float('umidade_gabinete_pct')->nullable()->after('temperatura_motor_c');
        });
    }

    public function down(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->dropColumn(['vibracao_mm_s', 'temperatura_motor_c', 'umidade_gabinete_pct']);
        });
    }
};
