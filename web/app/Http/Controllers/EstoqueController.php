<?php

namespace App\Http\Controllers;

use App\Enums\Status;
use App\Services\Cbm\PrevisaoEstoque;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EstoqueController extends Controller
{
    public function index(Request $request, PrevisaoEstoque $previsao): View
    {
        $linhas = $previsao->calcular($request->attributes->get('horizonte'));

        return view('estoque.index', [
            'linhas' => $linhas,
            'ruptura' => $linhas->where('status', Status::Vermelho)->count(),
            'pedir' => $linhas->where('status', Status::Amarelo)->count(),
            'ficticio' => $linhas->contains(fn ($l) => $l['peca']->ficticio),
        ]);
    }
}
