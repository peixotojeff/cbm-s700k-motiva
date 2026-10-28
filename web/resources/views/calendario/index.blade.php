@extends('layouts.app')
@section('titulo', 'Calendário × condição')
@section('subtitulo', 'Compara a próxima preventiva do plano da Motiva (PMP-3AMV-011-10) com o prazo que a condição da máquina indica.')

@php
    $selos = [
        'antecipar' => ['Antecipar', 'bg-red-100 text-red-800 ring-red-600/20'],
        'incluir' => ['Incluir na preventiva', 'bg-amber-100 text-amber-800 ring-amber-600/20'],
        'manter' => ['Manter calendário', 'bg-emerald-100 text-emerald-800 ring-emerald-600/20'],
        'atrasada' => ['Preventiva atrasada', 'bg-amber-100 text-amber-800 ring-amber-600/20'],
        'sem_preventiva' => ['Intervir pela condição', 'bg-red-100 text-red-800 ring-red-600/20'],
        'sem_cadastro' => ['Completar cadastro', 'bg-slate-100 text-slate-700 ring-slate-500/20'],
    ];
@endphp

@section('conteudo')
<x-horizonte class="mb-5" />

@if ($linhas->isEmpty())
    <div class="card text-sm text-slate-600">Nenhuma leitura registrada ainda.</div>
@else
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi titulo="Intervenções a antecipar" :valor="$resumo['antecipar']" cor="text-red-700" ajuda="A condição pede intervenção antes da próxima preventiva do calendário" />
        <x-kpi titulo="Antecedência média ganha" :valor="$resumo['antecedencia_media'] !== null ? $resumo['antecedencia_media'].' dias' : '—'" cor="text-brand-600" ajuda="Quantos dias antes do calendário o monitoramento aponta a intervenção" />
        <x-kpi titulo="Preventivas atrasadas" :valor="$resumo['atrasadas']" cor="text-amber-700" />
        <x-kpi titulo="Sem grupo do PMP" :valor="$resumo['sem_cadastro']" ajuda="Cadastre o local (X) da máquina para comparar com o calendário" />
    </div>

    @if ($resumo['fuso'])
        @php($rf = config('cbm.inspecao_fuso'))
        <div class="mb-5 flex gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 text-sm text-slate-800" role="note">
            <x-icone nome="alvo" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
            <p><strong>Inspeção dirigida do fuso em {{ $resumo['fuso'] }} {{ $resumo['fuso'] === 1 ? 'máquina estratégica' : 'máquinas estratégicas' }}.</strong>
                O retorno do fuso sem perda de correspondência antes não altera corrente, tempo nem força: o monitoramento não o enxerga
                (casos reais 8, 9 e 16). Por isso, na {{ $rf['nivel'] }} dos AMVs estratégicos, inspecionar {{ $rf['itens'] }}.
                As perdas de correspondência do intertravamento já chegam sozinhas pela API e disparam o alerta na hora.</p>
        </div>
    @endif

    <div class="card overflow-x-auto !p-0">
        <table class="min-w-full divide-y divide-slate-200">
            <thead><tr><th class="th">Máquina</th><th class="th">Grupo / local</th><th class="th">Próxima preventiva</th><th class="th">Condição</th><th class="th">Recomendação</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($linhas as $l)
                @php($m = $l['maquina'])
                <tr>
                    <td class="td font-medium"><a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $m->codigo) }}">{{ $m->codigo }}</a>
                        <div class="text-xs font-normal text-slate-500">{{ $m->rotuloTipo() }}</div></td>
                    <td class="td">{{ $m->rotuloGrupo() ?? '—' }}<div class="text-sm text-slate-600">{{ $m->local ?? 'local não informado' }}</div></td>
                    <td class="td whitespace-nowrap">
                        @if ($p = $l['proxima'])
                            <span class="font-medium">{{ $p['nivel'] }}</span> · {{ $p['proxima']->format('d/m/Y') }}
                            <div class="text-xs {{ $p['dias'] < 0 ? 'font-semibold text-red-700' : 'text-slate-500' }}">
                                {{ $p['dias'] < 0 ? 'atrasada há '.abs($p['dias']).' dias' : 'em '.$p['dias'].' dias' }}</div>
                        @else
                            <span class="text-slate-500">sem preventiva registrada</span>
                        @endif
                    </td>
                    <td class="td">
                        @if ($c = $l['critico'])
                            {{ $c['componente'] }}
                            <div class="text-sm text-slate-600"><x-prazo :dias="$c['prazo_dias']" />@if ($l['itens'] > 1) · +{{ $l['itens'] - 1 }} item(ns)@endif</div>
                        @else
                            <span class="text-slate-500">sem sinal de degradação</span>
                        @endif
                    </td>
                    <td class="td">
                        <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $selos[$l['recomendacao']][1] }}">{{ $selos[$l['recomendacao']][0] }}</span>
                        <div class="mt-1 max-w-xs text-xs text-slate-600">
                            @switch($l['recomendacao'])
                                @case('antecipar') A condição pede intervenção em ~{{ $l['prazo_condicao'] }} d; pelo calendário seria só na {{ $l['proxima']['nivel'] }} em {{ max(0, $l['proxima']['dias']) }} d — <strong>{{ $l['antecedencia'] }} dias de antecedência</strong>. @break
                                @case('incluir') Incluir {{ mb_strtolower($l['critico']['componente']) }} no escopo da próxima {{ $l['proxima']['nivel'] }}. @break
                                @case('manter') Seguir o PMP; nenhum componente com prazo no horizonte. @break
                                @case('atrasada') Sem sinal de degradação, mas a {{ $l['proxima']['nivel'] }} venceu; executar — a condição não dispensa o calendário. @break
                                @case('sem_preventiva') Não há preventiva registrada para comparar; programar pela condição. @break
                                @case('sem_cadastro') Informe o local (X) na página da máquina para comparar com o PMP. @break
                            @endswitch
                        </div>
                        @if ($f = $l['fuso'])
                            <div class="mt-2 flex max-w-xs items-start gap-1.5 rounded-md bg-brand-50 px-2 py-1.5 text-xs text-brand-900 ring-1 ring-brand-100">
                                <x-icone nome="alvo" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600" />
                                <span>Na {{ $f['nivel'] }}{{ $f['proxima'] ? ' de '.$f['proxima']->format('d/m/Y') : '' }}: inspecionar {{ $f['itens'] }}
                                    — <strong>ponto cego do monitoramento</strong>.</span>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <details class="card mt-6">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Intervalos do PMP e como a comparação é feita</summary>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead><tr><th class="th">Grupo</th><th class="th">Locais</th><th class="th">N1</th><th class="th">N2</th><th class="th">NL</th><th class="th">N3</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($pmp as $g)
                    <tr><td class="td">{{ $g['rotulo'] }}</td><td class="td">{{ implode(', ', $g['locais']) }}</td>
                        @foreach (['N1', 'N2', 'NL', 'N3'] as $n)<td class="td">{{ isset($g['intervalos'][$n]) ? $g['intervalos'][$n].' sem' : '—' }}</td>@endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <ul class="mt-4 list-disc space-y-1 pl-5 text-sm text-slate-700">
            <li>A última execução de cada nível vem das leituras registradas com a ocorrência “Inspeção N1/N2/N3” ou “Manutenção NL”.</li>
            <li>A próxima preventiva é a mais próxima entre os níveis (última execução + intervalo do grupo).</li>
            <li>Se o prazo previsto pela condição vence antes dela, a recomendação é <strong>antecipar</strong>; a diferença é a antecedência que o monitoramento dá sobre o calendário.</li>
            <li>O sistema <strong>nunca sugere adiar</strong> uma preventiva: o fabricante condiciona a garantia aos intervalos (manual Siemens 26330-OM-15, seção 8.2).</li>
        </ul>
    </details>
@endif
@endsection
