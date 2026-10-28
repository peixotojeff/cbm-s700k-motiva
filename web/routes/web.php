<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CasosReaisController;
use App\Http\Controllers\DocumentosController;
use App\Http\Controllers\EstoqueController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PlanoController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SimulacaoController;
use App\Http\Controllers\SituacaoController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Operação
Route::get('/registrar', [RegistroController::class, 'create'])->name('registro.create');
Route::post('/registrar', [RegistroController::class, 'store'])->name('registro.store');
Route::get('/plano', [PlanoController::class, 'index'])->name('plano.index');
Route::get('/plano.csv', [PlanoController::class, 'csv'])->name('plano.csv');
Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
Route::get('/estoque', [EstoqueController::class, 'index'])->name('estoque.index');
Route::get('/situacao', [SituacaoController::class, 'index'])->name('situacao.index');
Route::get('/situacao/{maquina}', [SituacaoController::class, 'show'])->name('situacao.show');
Route::patch('/situacao/{maquina}', [SituacaoController::class, 'update'])->name('situacao.update');
Route::delete('/situacao/{maquina}/leituras/{leitura}', [SituacaoController::class, 'destroyLeitura'])->name('situacao.leitura.destroy');

// Modelo preditivo
Route::get('/simulacao', [SimulacaoController::class, 'index'])->name('simulacao.index');
Route::get('/simulacao/ao-vivo', [SimulacaoController::class, 'animacao'])->name('simulacao.animacao');

// Conhecimento
Route::get('/casos-reais', [CasosReaisController::class, 'index'])->name('casos.index');
Route::get('/documentos', [DocumentosController::class, 'index'])->name('documentos.index');
Route::get('/documentos/{grupo}/{indice}', [DocumentosController::class, 'download'])->whereNumber('indice')->name('documentos.download');

// Administração
Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
Route::post('/admin/importar', [AdminController::class, 'importar'])->name('admin.importar');
Route::post('/admin/mock', [AdminController::class, 'carregarMock'])->name('admin.mock.carregar');
Route::delete('/admin/mock', [AdminController::class, 'removerMock'])->name('admin.mock.remover');
