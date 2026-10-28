<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinas', function (Blueprint $table) {
            $table->string('tipo')->default('agulha')->after('codigo');   // agulha | jacare (manual 26330-OM-15, 4.1)
            $table->string('local')->nullable()->after('linha');          // aparelho/região, ex.: X12
            $table->string('grupo_pmp')->nullable()->after('local');      // estrategico | nao_estrategico | patio
        });
        Schema::table('leituras', function (Blueprint $table) {
            $table->string('origem')->default('manual')->after('responsavel'); // manual | api | importacao
        });
    }

    public function down(): void
    {
        Schema::table('leituras', fn (Blueprint $t) => $t->dropColumn('origem'));
        Schema::table('maquinas', fn (Blueprint $t) => $t->dropColumn(['tipo', 'local', 'grupo_pmp']));
    }
};
