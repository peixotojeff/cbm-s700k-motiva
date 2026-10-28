<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sensores de corrente não medem força nem isolamento: esses campos passam a ser opcionais por leitura. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->double('forca_n')->nullable()->change();
            $table->double('isolamento_mohm')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('leituras', function (Blueprint $table) {
            $table->double('forca_n')->nullable(false)->change();
            $table->double('isolamento_mohm')->nullable(false)->change();
        });
    }
};
