<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maquinas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // ex.: "MCH 4123 JM"
            $table->string('linha')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquinas');
    }
};
