@extends('layouts.app')
@section('titulo', 'Estoque de peças')
@section('subtitulo', 'Peças que o plano de manutenção vai consumir, comparadas com o estoque e o prazo de reposição: o que pedir e até quando.')

@section('conteudo')
<x-horizonte class="mb-5" />

@if ($ficticio)
    <div class="mb-5 flex gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="note">
        <x-icone nome="atencao" class="h-5 w-5 shrink-0 text-amber-700" />
        <p><strong>Dados fictícios.</strong> Os nomes das peças vêm do manual Siemens 26330-OM-15 e dos casos reais; estoque, mínimo e prazo de reposição são exemplos para a apresentação. Substitua pela lista do almoxarifado da Motiva.</p>
    </div>
@endif

@if ($linhas->isEmpty())
    <div class="card text-sm text-slate-600">Nenhuma peça cadastrada. Rode <code>php artisan db:seed --class=PecaSeeder</code> para carregar a tabela de exemplo.</div>
@else
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3">
        <x-kpi titulo="Ruptura prevista" :valor="$ruptura" :status="\App\Enums\Status::Vermelho" cor="text-red-700" ajuda="A peça acaba antes de um pedido feito hoje chegar." />
        <x-kpi titulo="Pedir" :valor="$pedir" :status="\App\Enums\Status::Amarelo" cor="text-amber-700" ajuda="Há prazo para pedir, ou o saldo fica abaixo do estoque mínimo." />
        <x-kpi titulo="Peças acompanhadas" :valor="$linhas->count()" />
    </div>

    <div class="card overflow-x-auto !p-0">
        <h2 class="px-5 pt-4 font-semibold text-slate-900">Peças por componente</h2>
        <table class="mt-3 min-w-full divide-y divide-slate-200">
            <thead><tr>
                <th class="th">Peça</th><th class="th">Componente</th><th class="th">Situação</th>
                <th class="th text-right">Estoque</th><th class="th text-right">Demanda ≤ {{ $horizonte }} d</th><th class="th text-right">Mínimo</th>
                <th class="th text-right">Reposição</th><th class="th">Máquinas</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($linhas as $l)
                @php($p = $l['peca'])
                <tr>
                    <td class="td"><div class="font-medium text-slate-900">{{ $p->nome }}</div><div class="text-xs text-slate-500">{{ $p->codigo }}@if ($p->fonte) · {{ $p->fonte }}@endif</div></td>
                    <td class="td">{{ $p->rotuloComponente() }}</td>
                    <td class="td">
                        <x-selo :status="$l['status']" />
                        <div class="mt-1 text-sm text-slate-700">{{ $l['situacao'] }}</div>
                        @if ($l['falta_em_dias'] !== null)
                            <div class="text-xs text-slate-500">Falta em {{ $l['falta_em_dias'] }} d ({{ $l['falta_maquina'] }})</div>
                        @endif
                    </td>
                    <td class="td text-right">{{ $p->estoque }}</td>
                    <td class="td text-right">{{ $l['demanda_horizonte'] }}</td>
                    <td class="td text-right">{{ $p->estoque_minimo }}</td>
                    <td class="td text-right whitespace-nowrap">{{ $p->prazo_reposicao_dias }} d</td>
                    <td class="td">
                        @forelse ($l['maquinas'] as $m)
                            <a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $m) }}">{{ $m }}</a>@if (! $loop->last), @endif
                        @empty
                            <span class="text-slate-500">—</span>
                        @endforelse
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <details class="card mt-6">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Como a previsão é calculada</summary>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
            <li><strong>Demanda</strong>: cada item do plano de manutenção consome a quantidade da peça por intervenção, na data prevista. Entram os itens até {{ $horizonte }} dias + o prazo de reposição da peça, inclusive os em monitoramento; o que vem depois ainda pode ser pedido mais tarde. Item sem prazo estimável conta como necessidade imediata.</li>
            <li><strong>Ruptura</strong> (vermelho): o estoque acaba antes de um pedido feito hoje chegar, ou seja, a falta ocorre em menos dias que o prazo de reposição.</li>
            <li><strong>Pedir até</strong> (amarelo): o estoque acaba, mas ainda dá tempo. A data é o dia da falta menos o prazo de reposição.</li>
            <li><strong>Pedir agora</strong> (amarelo): não falta, mas o saldo após a demanda do horizonte fica abaixo do estoque mínimo.</li>
            <li>O <strong>estoque mínimo</strong> cobre as falhas que chegam sem aviso (no Painel de simulação, cerca de 6% das falhas não recebem alerta). Com o histórico real de consumo, ele deve ser recalculado por peça.</li>
        </ul>
    </details>
@endif
@endsection
