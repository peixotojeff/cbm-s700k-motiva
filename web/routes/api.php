<?php

use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\LeituraController;
use App\Http\Controllers\Api\MaquinaController;
use App\Http\Middleware\TokenIntegracao;
use Illuminate\Support\Facades\Route;

// API de integração (sensores, sistema de monitoramento remoto). Autenticação: Authorization: Bearer <CBM_API_TOKEN>.
Route::prefix('v1')->middleware([TokenIntegracao::class, 'throttle:120,1'])->group(function () {
    Route::post('/leituras', [LeituraController::class, 'store'])->name('api.leituras.store');
    Route::post('/eventos', [EventoController::class, 'store'])->name('api.eventos.store');
    Route::get('/maquinas', [MaquinaController::class, 'index'])->name('api.maquinas.index');
    Route::get('/maquinas/{maquina}', [MaquinaController::class, 'show'])->name('api.maquinas.show');
});
