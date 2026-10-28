<?php

namespace App\Http\Controllers;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Models\Leitura;
use App\Services\Cbm\Estado;
use App\Services\Cbm\PrevisaoEstoque;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, Estado $estado, PrevisaoEstoque $previsao): View
    {
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($request->attributes->get('horizonte'));
        $ativo = $plano->where('prioridade', '!=', Prioridade::Monitorar);
        $limiteDias = config('cbm.dias_sem_leitura');

        $hora = (int) now()->format('H');
        $saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

        $contagem = $situacao->countBy(fn ($s) => $s['status']->value);
        $desatualizadas = $situacao->filter(fn ($s) => $s['ultima_leitura']->diffInDays(now()) >= $limiteDias)->values();
        $estoque = $previsao->dePlano($plano, $request->attributes->get('horizonte'));

        return view('home', [
            'saudacao' => $saudacao,
            'situacao' => $situacao,
            'contagem' => collect(Status::cases())->mapWithKeys(fn ($s) => [$s->value => $contagem[$s->value] ?? 0]),
            'imediatos' => $ativo->where('prioridade', Prioridade::Imediato)->values(),
            'programar' => $ativo->where('prioridade', Prioridade::Programar)->values(),
            'atencao' => $situacao->filter(fn ($s) => in_array($s['status'], [Status::Vermelho, Status::Amarelo], true))->values(),
            'desatualizadas' => $desatualizadas,
            'pedidos' => $estoque->where('status', '!=', Status::Verde)->values(),
            'rupturas' => $estoque->where('status', Status::Vermelho)->values(),
            'limiteDias' => $limiteDias,
            'leiturasSemana' => Leitura::where('medido_em', '>=', now()->subDays(7))->count(),
            'recentes' => Leitura::with('maquina')->orderByDesc('medido_em')->limit(6)->get(),
        ]);
    }
}
