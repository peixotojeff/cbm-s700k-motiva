<?php

namespace App\Http\Controllers;

use App\Models\EventoFalha;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CasosReaisController extends Controller
{
    public function index(Request $request): View
    {
        $todos = EventoFalha::orderBy('data')->get();
        $maq = array_filter((array) $request->query('maquina'));
        $cat = array_filter((array) $request->query('categoria'));
        $eventos = $todos
            ->when($maq, fn ($c) => $c->whereIn('maquina', $maq))
            ->when($cat, fn ($c) => $c->whereIn('categoria', $cat))
            ->values();
        $porCategoria = $todos->countBy('categoria')->sortDesc();

        return view('casos.index', [
            'eventos' => $eventos,
            'total' => $todos->count(),
            'maquinasDistintas' => $todos->where('maquina', '!=', 'n/d')->pluck('maquina')->unique()->count(),
            'semAnomalia' => $todos->where('anomalia_corrente', 'Não')->count(),
            'evitadas' => $todos->filter(fn ($e) => str_contains($e->categoria, 'evitada'))->count(),
            'opcoesMaquina' => $todos->pluck('maquina')->unique()->sort()->values(),
            'opcoesCategoria' => $todos->pluck('categoria')->unique()->sort()->values(),
            'maq' => $maq, 'cat' => $cat,
            'grafCategorias' => ['rotulos' => $porCategoria->keys()->all(), 'valores' => $porCategoria->values()->all()],
            'grafLinha' => $todos->map(fn ($e) => ['x' => $e->data->format('Y-m-d'), 'y' => $e->maquina, 'cat' => $e->categoria])->all(),
        ]);
    }
}
