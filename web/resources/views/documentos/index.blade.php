@extends('layouts.app')
@section('titulo', 'Base documental')
@section('subtitulo', 'Manuais do fornecedor e planos/roteiros de manutenção preventiva para consulta da equipe.')

@section('conteudo')
<div class="space-y-8">
@foreach ($grupos as $g)
    <section>
        <h2 class="mb-3 text-lg font-semibold text-slate-900">{{ $g['titulo'] }}</h2>
        @if ($g['arquivos']->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">Nenhum arquivo encontrado em <code>{{ config('cbm.docs_path') }}</code>.</div>
        @else
            <ul class="card divide-y divide-slate-100 !p-0">
                @foreach ($g['arquivos'] as $a)
                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                        <div class="min-w-0"><div class="flex items-center gap-2 text-sm font-medium text-slate-800"><x-icone nome="documento" class="h-5 w-5 text-brand-600" /><span class="truncate" title="{{ $a['nome'] }}">{{ $a['nome'] }}</span></div><div class="pl-7 text-sm text-slate-600">{{ number_format($a['mb'], 1, ',', '') }} MB</div></div>
                        <a class="btn-ghost shrink-0" href="{{ $a['rota'] }}" target="_blank" rel="noopener">Abrir</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach
</div>
@endsection
