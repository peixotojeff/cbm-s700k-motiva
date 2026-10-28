<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Autenticação da API de integração por token fixo (Authorization: Bearer <CBM_API_TOKEN>). */
class TokenIntegracao
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('cbm.api_token');
        if ($token === '') {
            return response()->json(['erro' => 'API de integração desabilitada: defina CBM_API_TOKEN no .env.'], 503);
        }
        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['erro' => 'Token inválido ou ausente.'], 401);
        }

        return $next($request);
    }
}
