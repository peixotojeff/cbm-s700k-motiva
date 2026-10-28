<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maquina;
use App\Services\Cbm\Estado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MaquinaController extends Controller
{
    public static function horizonte(Request $request): int
    {
        $h = (int) $request->query('h', 0);

        return in_array($h, config('cbm.horizontes'), true) ? $h : config('cbm.horizonte_padrao');
    }

    /** Situação e plano de uma máquina no formato da API. */
    public static function avaliacao(Maquina $m, array $situacao, Collection $plano): array
    {
        return [
            'codigo' => $m->codigo, 'tipo' => $m->tipo, 'linha' => $m->linha, 'local' => $m->local, 'grupo_pmp' => $m->grupo_pmp,
            'status' => $situacao['status']->value,
            'precisa_intervencao' => in_array($situacao['status']->value, ['Vermelho', 'Amarelo'], true),
            'leituras' => $situacao['leituras'],
            'ultima_leitura' => $situacao['ultima_leitura']->toIso8601String(),
            'alertas' => $situacao['alertas'],
            'acao' => $situacao['acao'],
            'plano' => $plano->where('maquina', $m->codigo)->values()->map(fn ($p) => [
                'componente' => $p['componente'], 'prioridade' => $p['prioridade']->value,
                'prazo_dias' => $p['prazo_dias'] === null ? null : round($p['prazo_dias'], 1),
                'motivo' => $p['motivo'], 'acao' => $p['acao'],
            ])->all(),
        ];
    }

    public function index(Request $request, Estado $estado): JsonResponse
    {
        $h = self::horizonte($request);
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($h);
        $maquinas = Maquina::whereIn('codigo', $situacao->pluck('maquina'))->get()->keyBy('codigo');

        return response()->json([
            'horizonte_dias' => $h,
            'maquinas' => $situacao->map(fn ($s) => self::avaliacao($maquinas[$s['maquina']], $s, $plano))->values(),
        ]);
    }

    public function show(Request $request, Estado $estado, Maquina $maquina): JsonResponse
    {
        $h = self::horizonte($request);
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($h);
        $s = $situacao->firstWhere('maquina', $maquina->codigo);
        abort_unless($s, 404, 'Máquina sem leituras.');

        return response()->json(['horizonte_dias' => $h] + self::avaliacao($maquina, $s, $plano));
    }
}
