@extends('layouts.app')
@section('titulo', 'Registrar leitura')
@section('subtitulo', 'Preencha a medição de campo. Ao registrar, o sistema diz se a máquina precisa de intervenção e em quais componentes.')

@php
    // atributos de acessibilidade do campo: inválido + ligação com a dica e com o erro
    $aria = function (string $campo, bool $ajuda = false) use ($errors) {
        $ids = trim(($ajuda ? "ajuda-$campo " : '').($errors->has($campo) ? "erro-$campo" : ''));
        return ($errors->has($campo) ? 'aria-invalid="true" ' : '').($ids ? "aria-describedby=\"$ids\"" : '');
    };
@endphp

@section('conteudo')
<form method="POST" action="{{ route('registro.store') }}" class="card space-y-6" novalidate>
    @csrf
    <fieldset>
        <legend class="mb-3 text-base font-semibold text-slate-900">Identificação</legend>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="sm:col-span-2">
                <label class="label" for="maquina">Máquina de chave <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" id="maquina" name="maquina" list="maquinas" value="{{ old('maquina', request('maquina')) }}" required maxlength="50"
                       placeholder="ex.: MCH 4123 JM" autocomplete="off" autocapitalize="characters" {!! $aria('maquina', true) !!}>
                <datalist id="maquinas">@foreach ($maquinas as $m)<option value="{{ $m }}">@endforeach</datalist>
                <p id="ajuda-maquina" class="ajuda">Escolha da lista ou digite uma nova. A versão sai do sufixo: AG = agulha, JM = jacaré móvel.</p>
                <x-erro campo="maquina" />
            </div>
            <div>
                <label class="label" for="tipo">Versão</label>
                <select class="input" id="tipo" name="tipo" {!! $aria('tipo') !!}>
                    <option value="">Automática</option>
                    @foreach ($versoes as $k => $v)<option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $v['rotulo'] }}</option>@endforeach
                </select>
                <x-erro campo="tipo" />
            </div>
            <div>
                <label class="label" for="responsavel">Responsável</label>
                <input class="input" id="responsavel" name="responsavel" value="{{ old('responsavel') }}" maxlength="100" autocomplete="name" {!! $aria('responsavel') !!}>
                <x-erro campo="responsavel" />
            </div>
            <div>
                <label class="label" for="data">Data <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" type="date" id="data" name="data" value="{{ old('data', now()->format('Y-m-d')) }}" required {!! $aria('data') !!}>
                <x-erro campo="data" />
            </div>
            <div>
                <label class="label" for="hora">Hora <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" type="time" id="hora" name="hora" value="{{ old('hora', now()->format('H:i')) }}" required {!! $aria('hora') !!}>
                <x-erro campo="hora" />
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend class="mb-3 text-base font-semibold text-slate-900">Medições</legend>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="label" for="tempo_manobra_s">Tempo de manobra (s) <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" type="number" inputmode="decimal" step="0.01" min="0" max="60" id="tempo_manobra_s" name="tempo_manobra_s"
                       value="{{ old('tempo_manobra_s', 6.25) }}" required {!! $aria('tempo_manobra_s', true) !!}>
                <p id="ajuda-tempo_manobra_s" class="ajuda">Nominal: agulha 6,0 s · jacaré móvel 6,5 s</p>
                <x-erro campo="tempo_manobra_s" />
            </div>
            <div>
                <label class="label" for="forca_n">Força de acionamento (N) <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" type="number" inputmode="numeric" step="1" min="0" max="20000" id="forca_n" name="forca_n"
                       value="{{ old('forca_n', 5750) }}" required {!! $aria('forca_n', true) !!}>
                <p id="ajuda-forca_n" class="ajuda">Agulha 5.500 ± 500 N · jacaré móvel 6.000 ± 500 N</p>
                <x-erro campo="forca_n" />
            </div>
            <div>
                <label class="label" for="isolamento_mohm">Isolamento (MΩ) <span class="text-red-700" aria-hidden="true">*</span><span class="sr-only">(obrigatório)</span></label>
                <input class="input" type="number" inputmode="decimal" step="0.01" min="0" max="1000" id="isolamento_mohm" name="isolamento_mohm"
                       value="{{ old('isolamento_mohm', 10) }}" required {!! $aria('isolamento_mohm', true) !!}>
                <p id="ajuda-isolamento_mohm" class="ajuda">Crítico: ≤ 1 MΩ</p>
                <x-erro campo="isolamento_mohm" />
            </div>
            <div>
                <label class="label" for="corrente_pico">Corrente de pico (A)</label>
                <input class="input" type="number" inputmode="decimal" step="0.01" min="0" max="50" id="corrente_pico" name="corrente_pico"
                       value="{{ old('corrente_pico', 4.0) }}" {!! $aria('corrente_pico') !!}>
                <x-erro campo="corrente_pico" />
            </div>
            <div>
                <label class="label" for="corrente_media">Corrente média (A)</label>
                <input class="input" type="number" inputmode="decimal" step="0.01" min="0" max="50" id="corrente_media" name="corrente_media"
                       value="{{ old('corrente_media', 1.8) }}" {!! $aria('corrente_media') !!}>
                <x-erro campo="corrente_media" />
            </div>
            <div>
                <label class="label" for="ocorrencia">Tipo de ocorrência</label>
                <select class="input" id="ocorrencia" name="ocorrencia" {!! $aria('ocorrencia') !!}>
                    @foreach ($ocorrencias as $o)<option @selected(old('ocorrencia', 'Inspeção N1') === $o)>{{ $o }}</option>@endforeach
                </select>
                <x-erro campo="ocorrencia" />
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend class="mb-1 text-base font-semibold text-slate-900">Condição do equipamento <span class="font-normal text-slate-600">(opcional)</span></legend>
        <p class="mb-3 text-sm text-slate-600">Preencha se houver instrumento: vibrômetro, termômetro infravermelho no motor, higrômetro no gabinete.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($condicao as $campo => $g)
                <div>
                    <label class="label" for="{{ $campo }}">{{ $g['rotulo'] }} ({{ $g['unidade'] }})</label>
                    <input class="input" type="number" inputmode="decimal" step="0.1" id="{{ $campo }}" name="{{ $campo }}"
                           value="{{ old($campo) }}" {!! $aria($campo, true) !!}>
                    <p id="ajuda-{{ $campo }}" class="ajuda">Alerta ≥ {{ number_format($g['alerta'], 1, ',', '') }} · crítico ≥ {{ number_format($g['critico'], 1, ',', '') }} {{ $g['unidade'] }}</p>
                    <x-erro :campo="$campo" />
                </div>
            @endforeach
        </div>
        <div class="mt-4">
            <label class="label" for="observacoes">Observações</label>
            <textarea class="input" id="observacoes" name="observacoes" rows="3" maxlength="2000"
                      placeholder="Ex.: ruído anormal, folga na barra de detecção…" {!! $aria('observacoes') !!}>{{ old('observacoes') }}</textarea>
            <x-erro campo="observacoes" />
        </div>
    </fieldset>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-600"><span class="text-red-700" aria-hidden="true">*</span> obrigatórios · valores iniciais = nominais.</p>
        <button class="btn-primary min-w-48" type="submit" data-enviando-texto="Registrando…">Registrar leitura</button>
    </div>
</form>

@if ($veredito)
    <h2 class="mb-3 mt-8 text-lg font-semibold text-slate-900">Resultado da análise</h2>
    @include('registro._veredito', ['v' => $veredito])
@endif
@endsection
