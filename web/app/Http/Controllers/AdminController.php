<?php

namespace App\Http\Controllers;

use App\Models\Leitura;
use App\Services\Cbm\ImportarLeituras;
use App\Services\Cbm\MockLeituras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'obrigatorias' => ImportarLeituras::OBRIGATORIAS,
            'opcionais' => ImportarLeituras::OPCIONAIS,
            'total' => Leitura::count(),
            'mock' => Leitura::where('responsavel', Leitura::MARCA_MOCK)->count(),
            'apiAtiva' => (string) config('cbm.api_token') !== '',
        ]);
    }

    public function importar(Request $request, ImportarLeituras $importador): RedirectResponse
    {
        $request->validate(['arquivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120']], [], ['arquivo' => 'arquivo']);
        $relatorio = $importador->executar($request->file('arquivo')->getRealPath());

        return redirect()->route('admin.index')->with('importacao', $relatorio);
    }

    public function carregarMock(MockLeituras $mock): RedirectResponse
    {
        return redirect()->route('admin.index')->with('sucesso', $mock->carregar().' leituras de exemplo carregadas.');
    }

    public function removerMock(MockLeituras $mock): RedirectResponse
    {
        return redirect()->route('admin.index')->with('sucesso', $mock->limpar().' leituras de exemplo removidas.');
    }
}
