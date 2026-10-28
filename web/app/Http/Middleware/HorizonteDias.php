<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Horizonte de previsão (dias) escolhido pelo usuário: guardado na sessão e compartilhado com as views. */
class HorizonteDias
{
    public function handle(Request $request, Closure $next): Response
    {
        $validos = config('cbm.horizontes');
        $h = (int) $request->query('h', 0);
        if (in_array($h, $validos, true)) {
            $request->session()->put('horizonte', $h);
        }
        $atual = (int) $request->session()->get('horizonte', config('cbm.horizonte_padrao'));
        $request->attributes->set('horizonte', $atual);
        View::share('horizonte', $atual);
        View::share('horizontes', $validos);

        return $next($request);
    }
}
