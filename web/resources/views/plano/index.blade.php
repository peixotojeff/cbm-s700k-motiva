@extends('layouts.app')
@section('titulo', 'Plano de manutenção')
@section('subtitulo', "Componentes que a equipe deve inspecionar/trocar nos próximos {$horizonte} dias, conforme a tendência das leituras registradas.")

@section('conteudo')
<x-horizonte class="mb-5" />
@if ($vazio)
    <div class="card text-sm text-slate-600">Sem leituras suficientes para gerar o plano. Registre medições em <a class="text-brand-600 underline" href="{{ route('registro.create') }}">Registrar leitura</a>.</div>
@else
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3">
        <x-kpi titulo="Imediato" :valor="$ativo->where('prioridade', \App\Enums\Prioridade::Imediato)->count()" cor="text-red-700" />
        <x-kpi :titulo="'Programar (≤ '.$horizonte.' dias)'" :valor="$ativo->where('prioridade', \App\Enums\Prioridade::Programar)->count()" cor="text-amber-700" />
        <x-kpi titulo="Máquinas envolvidas" :valor="$ativo->pluck('maquina')->unique()->count()" />
    </div>

    @if ($ativo->isEmpty())
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-5 text-sm text-emerald-900">Nenhum componente exige intervenção nos próximos {{ $horizonte }} dias.</div>
    @else
        <div class="card overflow-x-auto !p-0">
            <div class="flex items-center justify-between px-5 pt-4">
                <h2 class="font-semibold text-slate-900">Intervenções</h2>
                <a class="btn-ghost" href="{{ route('plano.csv', ['h' => $horizonte]) }}">Baixar CSV</a>
            </div>
            <table class="mt-3 min-w-full divide-y divide-slate-200">
                <thead><tr><th class="th">Máquina</th><th class="th">Componente</th><th class="th">Prioridade</th><th class="th">Prazo</th><th class="th">Motivo</th><th class="th">Ação</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($ativo as $p)
                    <tr>
                        <td class="td font-medium"><a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $p['maquina']) }}">{{ $p['maquina'] }}</a></td>
                        <td class="td">{{ $p['componente'] }}</td>
                        <td class="td"><x-selo :status="$p['prioridade']" /></td>
                        <td class="td whitespace-nowrap"><x-prazo :dias="$p['prazo_dias']" /></td>
                        <td class="td">{{ $p['motivo'] }}</td>
                        <td class="td max-w-sm">{{ $p['acao'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <details class="card mt-6">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Em monitoramento (fora do horizonte) — {{ $monitorar->count() }}</summary>
        @if ($monitorar->isEmpty())
            <p class="mt-3 text-sm text-slate-500">Nada em monitoramento.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="th">Máquina</th><th class="th">Componente</th><th class="th">Prazo</th><th class="th">Motivo</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($monitorar as $p)
                        <tr><td class="td">{{ $p['maquina'] }}</td><td class="td">{{ $p['componente'] }}</td><td class="td whitespace-nowrap"><x-prazo :dias="$p['prazo_dias']" /></td><td class="td">{{ $p['motivo'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </details>

    <details class="card mt-4">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">Como o prazo é calculado</summary>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-700">
            <li><strong>Tendência linear</strong> das leituras (mín. {{ config('cbm.min_leituras') }}, em datas diferentes): estima em quantos dias tempo de manobra, força, isolamento e corrente cruzam os limites nominais.</li>
            <li><strong>Condição do equipamento</strong> (quando medida): vibração do motor (ISO 10816-1, alerta 2,8 / crítico 4,5 mm/s), temperatura do motor e umidade do gabinete (premissas: 70/85 °C e 80/95%). Acima do alerta o componente entra no plano; no crítico, a intervenção é imediata. Infiltração registrada aponta a vedação do gabinete.</li>
            <li><strong>Reaperto por condição</strong>: em vez de reapertar a cada 3–6 semanas sem indicação, o reaperto das fixações entra na ação das barras quando o tempo de manobra ou a força saem da tendência.</li>
            <li><strong>Ocorrências</strong> de perda de correspondência/travamento sinalizam fuso/microswitch, falha que pode não alterar a corrente (casos reais de 2017).</li>
            <li>O <strong>modelo preditivo</strong> (XGBoost) está no Painel de simulação; só passa a decidir após retreino com dados reais.</li>
            <li>Componentes e ações vêm dos casos reais e do plano de manutenção; os prazos são estimativas e não substituem a inspeção.</li>
        </ul>
    </details>
@endif
@endsection
