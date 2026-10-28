<?php

namespace App\Http\Controllers;

use App\Enums\Prioridade;
use App\Services\Cbm\Estado;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanoController extends Controller
{
    public function index(Request $request, Estado $estado): View
    {
        ['plano' => $plano] = $estado->atual($request->attributes->get('horizonte'));

        return view('plano.index', [
            'ativo' => $plano->where('prioridade', '!=', Prioridade::Monitorar)->values(),
            'monitorar' => $plano->where('prioridade', Prioridade::Monitorar)->values(),
            'vazio' => $plano->isEmpty(),
        ]);
    }

    public function csv(Request $request, Estado $estado): StreamedResponse
    {
        $plano = $estado->atual($request->attributes->get('horizonte'))['plano']
            ->where('prioridade', '!=', Prioridade::Monitorar);

        return response()->streamDownload(function () use ($plano) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // BOM para o Excel
            fputcsv($f, ['Máquina', 'Componente', 'Prioridade', 'Prazo (dias)', 'Motivo', 'Ação'], ';', '"', '');
            foreach ($plano as $p) {
                fputcsv($f, [$p['maquina'], $p['componente'], $p['prioridade']->value, $p['prazo_dias'] === null ? '' : round($p['prazo_dias']), $p['motivo'], $p['acao']], ';', '"', '');
            }
            fclose($f);
        }, 'plano_manutencao.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
