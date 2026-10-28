<?php

namespace App\Http\Controllers;

use App\Enums\Prioridade;
use App\Enums\Status;
use App\Models\Leitura;
use App\Models\Maquina;
use App\Services\Cbm\Estado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SituacaoController extends Controller
{
    public function index(Request $request, Estado $estado): View
    {
        $situacao = $estado->atual($request->attributes->get('horizonte'))['situacao'];
        $status = Status::tryFrom((string) $request->query('status'));
        $busca = trim((string) $request->query('q'));
        $filtradas = $situacao
            ->when($status, fn ($c) => $c->where('status', $status))
            ->when($busca !== '', fn ($c) => $c->filter(fn ($s) => str_contains(mb_strtoupper($s['maquina']), mb_strtoupper($busca))))
            ->values();

        return view('situacao.index', [
            'situacao' => $situacao,
            'filtradas' => $filtradas,
            'status' => $status,
            'busca' => $busca,
            'contagem' => $situacao->countBy(fn ($s) => $s['status']->value),
        ]);
    }

    public function show(Request $request, Estado $estado, Maquina $maquina): View
    {
        $leituras = $maquina->leituras()->get();
        $analise = $estado->atual($request->attributes->get('horizonte'));
        $serie = fn (string $campo) => $leituras->pluck($campo)->all();
        $lim = Maquina::limites($maquina->tipo);

        return view('situacao.show', [
            'maquina' => $maquina,
            'situacao' => $analise['situacao']->firstWhere('maquina', $maquina->codigo),
            'plano' => $doPlano = $analise['plano']->where('maquina', $maquina->codigo)->values(),
            'ativos' => $doPlano->where('prioridade', '!=', Prioridade::Monitorar)->values(),
            'monitorar' => $doPlano->where('prioridade', Prioridade::Monitorar)->values(),
            'leituras' => $leituras->reverse()->values(),
            'graficos' => [
                'rotulos' => $leituras->map(fn ($l) => $l->medido_em->format('d/m H:i'))->all(),
                'tempo' => $serie('tempo_manobra_s'), 'forca' => $serie('forca_n'),
                'isolamento' => $serie('isolamento_mohm'), 'pico' => $serie('corrente_pico'),
                'limites' => [
                    'tempo' => $lim['tempo'], 'tempo_nominal' => $lim['tempo_nominal'],
                    'forca' => $lim['forca'], 'forca_nominal' => $lim['forca_nominal'],
                    'isolamento' => config('cbm.isolamento_min'),
                ],
                // só as grandezas de condição que esta máquina já mediu
                'condicao' => collect(config('cbm.condicao'))
                    ->filter(fn ($g, $campo) => $leituras->whereNotNull($campo)->isNotEmpty())
                    ->map(fn ($g, $campo) => [
                        'titulo' => "{$g['rotulo']} ({$g['unidade']})", 'valores' => $serie($campo), 'limites' => [$g['alerta'], $g['critico']],
                    ])->all(),
            ],
            'versoes' => config('cbm.versoes'),
            'linhas' => config('cbm.linhas'),
            'grupos' => config('cbm.pmp'),
            'origens' => config('cbm.origens'),
        ]);
    }

    public function update(Request $request, Maquina $maquina): RedirectResponse
    {
        $dados = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(config('cbm.versoes')))],
            'linha' => ['nullable', Rule::in(config('cbm.linhas'))],
            'local' => ['nullable', 'string', 'max:10'],
            'grupo_pmp' => ['nullable', Rule::in(array_keys(config('cbm.pmp')))],
        ], [], ['tipo' => 'versão', 'grupo_pmp' => 'grupo do PMP']);
        $dados['local'] = isset($dados['local']) ? strtoupper(trim($dados['local'])) : null;
        $dados['grupo_pmp'] = ($dados['grupo_pmp'] ?? null) ?: Maquina::inferirGrupo($dados['local']);
        $maquina->update($dados);

        return redirect()->route('situacao.show', $maquina->codigo)->with('sucesso', 'Cadastro de '.$maquina->codigo.' atualizado.');
    }

    public function destroyLeitura(Maquina $maquina, Leitura $leitura): RedirectResponse
    {
        abort_unless($leitura->maquina_id === $maquina->id, 404);
        $leitura->delete();

        return redirect()->route('situacao.show', $maquina->codigo)->with('sucesso', 'Lançamento excluído.');
    }
}
