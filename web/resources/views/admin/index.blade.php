@extends('layouts.app')
@section('titulo', 'Importação e dados')
@section('subtitulo', 'Importe leituras em lote e gerencie os dados de exemplo.')

@section('conteudo')
<div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-2">
    <x-kpi titulo="Leituras no banco" :valor="$total" />
    <x-kpi titulo="Leituras de exemplo (MOCK)" :valor="$mock" />
</div>

<section class="card mb-6">
    <h2 class="font-semibold text-slate-900">Importar leituras (CSV)</h2>
    <p class="mt-1 text-sm text-slate-600">
        Colunas obrigatórias: <code class="text-xs">{{ implode(', ', $obrigatorias) }}</code>.
        Opcionais: <code class="text-xs">{{ implode(', ', $opcionais) }}</code>.
        Aceita vírgula ou ponto e vírgula, e decimais com vírgula.
    </p>
    <form method="POST" action="{{ route('admin.importar') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf
        <div>
            <label class="label" for="arquivo">Arquivo CSV</label>
            <input class="input file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700" type="file" id="arquivo" name="arquivo" accept=".csv,.txt" required @error('arquivo') aria-invalid="true" aria-describedby="erro-arquivo" @enderror>
            <x-erro campo="arquivo" />
        </div>
        <button class="btn-primary" type="submit" data-enviando-texto="Importando…">Importar</button>
    </form>

    @if ($r = session('importacao'))
        <div class="mt-5 text-sm">
            @if ($r['colunas_ausentes'])
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">Colunas ausentes: {{ implode(', ', $r['colunas_ausentes']) }}</div>
            @else
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-emerald-800">{{ $r['importadas'] }} leituras importadas; {{ count($r['erros']) }} linhas rejeitadas.</div>
                @if ($r['erros'])
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-red-800">
                        @foreach (array_slice($r['erros'], 0, 50) as $e)<li>Linha {{ $e['linha'] }}: {{ implode(' · ', $e['mensagens']) }}</li>@endforeach
                    </ul>
                @endif
            @endif
        </div>
    @endif
</section>

<section class="card mb-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-semibold text-slate-900">Integração com sensores e sistema de monitoramento (API)</h2>
        @if ($apiAtiva)
            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Ativa</span>
        @else
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">Desabilitada — defina CBM_API_TOKEN no .env</span>
        @endif
    </div>
    <p class="mt-1 text-sm text-slate-600">
        Leituras enviadas pela API entram com origem “Integração (API)” e passam pela mesma avaliação do registro manual.
        Aceita uma leitura ou um lote de até {{ config('cbm.api_lote_max') }} (tudo ou nada). Só máquina, instante e tempo de manobra são
        obrigatórios: força, isolamento, corrente, vibração (<code class="text-xs">vibracao_mm_s</code>), temperatura do motor
        (<code class="text-xs">temperatura_motor_c</code>) e umidade do gabinete (<code class="text-xs">umidade_gabinete_pct</code>) entram quando o
        sensor os mede. Reenvios da mesma leitura são ignorados.
    </p>
    <ul class="mt-3 space-y-1 text-sm text-slate-700">
        <li><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">POST {{ url('/api/v1/leituras') }}</code> — registra leituras e devolve a avaliação das máquinas</li>
        <li><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">POST {{ url('/api/v1/eventos') }}</code> — eventos do log do intertravamento
            (<code class="text-xs">perda_correspondencia</code>, <code class="text-xs">travamento</code>), sem medição; é o precursor do retorno do fuso e dispara o alerta na hora</li>
        <li><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">GET {{ url('/api/v1/maquinas') }}</code> — status e plano de todas as máquinas (<code class="text-xs">?h=7|15|30|60</code>)</li>
        <li><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">GET {{ url('/api/v1/maquinas/{código}') }}</code> — status e plano de uma máquina</li>
    </ul>
    <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">curl -X POST {{ url('/api/v1/leituras') }} \
  -H "Authorization: Bearer $CBM_API_TOKEN" -H "Content-Type: application/json" \
  -d '{"maquina":"MCH 4123 JM","medido_em":"2026-10-27T08:00:00","tempo_manobra_s":6.62,
       "corrente_pico":4.0,"corrente_media":1.8}'

curl -X POST {{ url('/api/v1/eventos') }} \
  -H "Authorization: Bearer $CBM_API_TOKEN" -H "Content-Type: application/json" \
  -d '{"maquina":"MCH 4134 JM","ocorrido_em":"2026-10-28T07:42:10","evento":"perda_correspondencia"}'</pre>
</section>

<section class="card">
    <h2 class="font-semibold text-slate-900">Dados de exemplo</h2>
    <p class="mt-1 text-sm text-slate-600">Carrega leituras fictícias (responsável = MOCK) para demonstrar o sistema. Remover apaga somente as de exemplo.</p>
    <div class="mt-4 flex flex-wrap gap-3">
        <form method="POST" action="{{ route('admin.mock.carregar') }}">@csrf<button class="btn-primary" type="submit">Carregar dados de exemplo</button></form>
        <form method="POST" action="{{ route('admin.mock.remover') }}" onsubmit="return confirm('Remover somente as leituras de exemplo?')">@csrf @method('DELETE')<button class="btn-danger" type="submit">Remover dados de exemplo</button></form>
    </div>
</section>
@endsection
