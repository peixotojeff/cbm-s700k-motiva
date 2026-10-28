@extends('layouts.app')
@section('titulo', 'Situação das máquinas')
@section('subtitulo', 'Status de cada máquina de chave, ordenado por severidade. O risco do modelo preditivo fica no Painel de simulação.')

@section('conteudo')
<x-horizonte class="mb-5" />
@if ($situacao->isEmpty())
    <div class="card text-sm text-slate-600">Nenhuma leitura registrada ainda. Use <a class="text-brand-600 underline" href="{{ route('registro.create') }}">Registrar leitura</a> para começar.</div>
@else
    {{-- Cartões de status funcionam como filtro; o selecionado fica marcado. --}}
    <div class="mb-4 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-4">
        @foreach ([null, \App\Enums\Status::Vermelho, \App\Enums\Status::Amarelo, \App\Enums\Status::Verde] as $s)
            @php($ativo = $status === $s)
            <a href="{{ route('situacao.index', array_filter(['status' => $s?->value, 'q' => $busca])) }}" @if ($ativo) aria-current="true" @endif
               class="card block !p-4 transition hover:shadow-md {{ $ativo ? 'ring-2 ring-brand-600' : 'hover:border-brand-200' }}">
                <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    @if ($s)<x-icone :nome="$s->icone()" class="h-4 w-4 {{ $s->corIcone() }}" />@endif
                    {{ $s?->value ?? 'Todas' }}
                </div>
                <div class="mt-1 text-3xl font-bold tabular-nums {{ $s?->corIcone() ?? 'text-slate-900' }}">{{ $s ? ($contagem[$s->value] ?? 0) : $situacao->count() }}</div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('situacao.index') }}" class="mb-4 flex flex-wrap items-end gap-3" role="search">
        @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
        <div class="min-w-56 flex-1 sm:max-w-sm">
            <label class="label" for="q">Buscar máquina</label>
            <input class="input" type="search" id="q" name="q" value="{{ $busca }}" placeholder="ex.: 4123" list="maquinas-lista" autocomplete="off">
            <datalist id="maquinas-lista">@foreach ($situacao as $s)<option value="{{ $s['maquina'] }}">@endforeach</datalist>
        </div>
        <button class="btn-primary" type="submit">Buscar</button>
        @if ($status || $busca !== '')
            <a class="btn-ghost" href="{{ route('situacao.index') }}">Limpar filtros</a>
        @endif
    </form>

    <p class="mb-2 text-sm text-slate-600" role="status">
        {{ $filtradas->count() }} de {{ $situacao->count() }} máquinas{{ $status ? ' em '.mb_strtolower($status->value) : '' }}{{ $busca !== '' ? ' com “'.$busca.'”' : '' }}.
    </p>

    @if ($filtradas->isEmpty())
        <div class="card text-sm text-slate-600">
            Nenhuma máquina encontrada. Confira o código (ex.: <strong>MCH 4123 JM</strong>) ou
            <a class="text-brand-600 underline" href="{{ route('situacao.index') }}">veja todas as máquinas</a>.
            Máquina nova? <a class="text-brand-600 underline" href="{{ route('registro.create', ['maquina' => $busca]) }}">Registre a primeira leitura</a>.
        </div>
    @else
        <div class="card overflow-x-auto !p-0">
            <table class="min-w-full divide-y divide-slate-200">
                <thead><tr><th class="th">Máquina</th><th class="th">Status</th><th class="th">Alertas</th><th class="th">Leituras</th><th class="th">Última leitura</th><th class="th">Ação recomendada</th><th class="th"><span class="sr-only">Ações</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($filtradas as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="td whitespace-nowrap font-medium"><a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $s['maquina']) }}">{{ $s['maquina'] }}</a></td>
                        <td class="td"><x-selo :status="$s['status']" /></td>
                        <td class="td">{{ count($s['alertas']) }}</td>
                        <td class="td">{{ $s['leituras'] }}</td>
                        <td class="td whitespace-nowrap">{{ $s['ultima_leitura']->format('d/m/Y H:i') }}</td>
                        <td class="td max-w-md">{{ $s['acao'] }}</td>
                        <td class="td whitespace-nowrap">
                            <a class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-brand-600 underline hover:bg-brand-50"
                               href="{{ route('registro.create', ['maquina' => $s['maquina']]) }}" aria-label="Registrar leitura de {{ $s['maquina'] }}">Registrar leitura</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
@endsection
