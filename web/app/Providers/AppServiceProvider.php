<?php

namespace App\Providers;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Services\Cbm\Estado;
use App\Services\Cbm\PrevisaoEstoque;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Contadores de urgência no menu: rota => [quantidade, texto lido pelo leitor de tela].
        View::composer('layouts.app', function ($view) {
            $h = (int) request()->attributes->get('horizonte', config('cbm.horizonte_padrao'));
            ['situacao' => $situacao, 'plano' => $plano] = app(Estado::class)->atual($h);
            $vermelhas = $situacao->where('status', Status::Vermelho)->count();
            $imediatos = $plano->where('prioridade', Prioridade::Imediato)->count();
            $rupturas = app(PrevisaoEstoque::class)->dePlano($plano, $h)->where('status', Status::Vermelho)->count();

            $view->with('avisosMenu', array_filter([
                'situacao.*' => [$vermelhas, $vermelhas === 1 ? '1 máquina em vermelho' : "$vermelhas máquinas em vermelho"],
                'plano.index' => [$imediatos, $imediatos === 1 ? '1 intervenção imediata' : "$imediatos intervenções imediatas"],
                'estoque.index' => [$rupturas, $rupturas === 1 ? '1 peça em ruptura' : "$rupturas peças em ruptura"],
            ], fn ($a) => $a[0] > 0));
        });
    }
}
