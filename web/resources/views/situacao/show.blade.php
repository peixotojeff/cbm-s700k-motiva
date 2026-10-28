@extends('layouts.app')
@section('titulo', $maquina->codigo)
@section('subtitulo', $maquina->rotuloTipo().($maquina->linha ? ' · '.$maquina->linha : '').($maquina->local ? ' · '.$maquina->local : '').' — histórico, tendência e componentes sob risco.')

@section('conteudo')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-4">
        <a class="text-sm text-brand-600 hover:underline" href="{{ route('situacao.index') }}">← Todas as máquinas</a>
        @if (request()->boolean('ao_vivo'))
            <a class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200" href="{{ request()->fullUrlWithoutQuery(['ao_vivo']) }}">
                <span class="h-2 w-2 animate-pulse rounded-full bg-red-600"></span> Ao vivo · atualiza a cada 10 s (parar)</a>
        @else
            <a class="text-sm text-slate-600 hover:underline" href="{{ request()->fullUrlWithQuery(['ao_vivo' => 1]) }}">Acompanhar ao vivo</a>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-3">
        <x-horizonte />
        <a class="btn-primary" href="{{ route('registro.create', ['maquina' => $maquina->codigo]) }}">Registrar leitura</a>
    </div>
</div>

@if ($situacao)
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="card !p-4"><div class="text-xs font-medium uppercase text-slate-500">Status</div><div class="mt-2"><x-selo :status="$situacao['status']" /></div></div>
        <x-kpi titulo="Leituras" :valor="$situacao['leituras']" />
        <x-kpi titulo="Última leitura" :valor="$situacao['ultima_leitura']->format('d/m/Y H:i')" cor="text-lg text-slate-900" />
    </div>
    @if ($situacao['alertas'])
        @php($vermelho = $situacao['status'] === \App\Enums\Status::Vermelho)
        <div class="mb-6 rounded-xl border p-4 text-sm {{ $vermelho ? 'border-red-200 bg-red-50 text-red-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
            <strong class="flex items-center gap-2"><x-icone :nome="$situacao['status']->icone()" class="h-5 w-5 {{ $situacao['status']->corIcone() }}" />Alertas da última leitura</strong>
            <ul class="mt-1 list-disc pl-5">@foreach ($situacao['alertas'] as $a)<li>{{ $a }}</li>@endforeach</ul>
        </div>
    @endif
@endif

<details class="card mb-6" @if (! $maquina->grupo_pmp) open @endif>
    <summary class="cursor-pointer text-sm font-semibold text-slate-900">Cadastro da máquina
        <span class="font-normal text-slate-500">— versão, linha e local no plano de manutenção (PMP)</span></summary>
    <form method="POST" action="{{ route('situacao.update', $maquina->codigo) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        @csrf @method('PATCH')
        <div class="lg:col-span-2">
            <label class="label" for="tipo">Versão do S700K</label>
            <select class="input" id="tipo" name="tipo" @error('tipo') aria-invalid="true" aria-describedby="erro-tipo" @enderror>
                @foreach ($versoes as $k => $v)
                    <option value="{{ $k }}" @selected($maquina->tipo === $k)>{{ $v['rotulo'] }} — {{ number_format($v['tempo_nominal'], 1, ',', '') }} s · {{ number_format($v['forca_nominal'], 0, ',', '.') }} ± {{ number_format($v['forca_tol'], 0, ',', '.') }} N</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="linha">Linha</label>
            <select class="input" id="linha" name="linha" @error('linha') aria-invalid="true" aria-describedby="erro-linha" @enderror>
                <option value="">—</option>
                @foreach ($linhas as $ln)<option @selected($maquina->linha === $ln)>{{ $ln }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="local">Local (AMV)</label>
            <input class="input" id="local" name="local" value="{{ old('local', $maquina->local) }}" maxlength="10" placeholder="ex.: X12" autocapitalize="characters" @error('local') aria-invalid="true" aria-describedby="erro-local" @enderror>
            <x-erro campo="local" />
        </div>
        <div>
            <label class="label" for="grupo_pmp">Grupo do PMP</label>
            <select class="input" id="grupo_pmp" name="grupo_pmp" @error('grupo_pmp') aria-invalid="true" aria-describedby="erro-grupo_pmp" @enderror>
                <option value="">Detectar pelo local</option>
                @foreach ($grupos as $k => $g)<option value="{{ $k }}" @selected($maquina->grupo_pmp === $k)>{{ $g['rotulo'] }}</option>@endforeach
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-5 flex items-center justify-between gap-3">
            <p class="text-sm text-slate-600">Limites da versão: manual Siemens 26330-OM-15, seção 4.1. Intervalos: PMP-3AMV-011-10.</p>
            <button class="btn-primary" type="submit" data-enviando-texto="Salvando…">Salvar cadastro</button>
        </div>
    </form>
</details>

@if ($plano->isNotEmpty())
    {{-- O que exige ação fica à vista; o que está em monitoramento (fora do horizonte) fica recolhido. --}}
    @foreach ([[$ativos, 'Componentes que exigem ação', false], [$monitorar, 'Em monitoramento (fora do horizonte)', true]] as [$itens, $titulo, $recolhido])
        @continue($itens->isEmpty())
        <details class="card mb-6 !p-0" @if (! $recolhido) open @endif>
            <summary class="my-0 cursor-pointer px-5 pt-4 font-semibold text-slate-900 {{ $recolhido ? 'pb-4' : '' }}">{{ $titulo }} — {{ $itens->count() }}
                <span class="font-normal text-slate-600">(horizonte de {{ $horizonte }} dias)</span></summary>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="th">Componente</th><th class="th">Prioridade</th><th class="th">Prazo</th><th class="th">Motivo</th><th class="th">Ação</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($itens as $p)
                        <tr><td class="td">{{ $p['componente'] }}</td><td class="td"><x-selo :status="$p['prioridade']" /></td><td class="td whitespace-nowrap"><x-prazo :dias="$p['prazo_dias']" /></td><td class="td">{{ $p['motivo'] }}</td><td class="td max-w-sm">{{ $p['acao'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
@endif

<div class="grid gap-4 lg:grid-cols-2">
    @foreach (['g-tempo', 'g-forca', 'g-iso', 'g-pico', ...array_map(fn ($c) => "g-$c", array_keys($graficos['condicao']))] as $g)
        <div class="card"><div class="h-64"><canvas id="{{ $g }}"></canvas></div></div>
    @endforeach
</div>

<details class="card mt-6">
    <summary class="cursor-pointer text-sm font-semibold text-slate-900">Histórico de leituras e exclusão</summary>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead><tr><th class="th">Data</th><th class="th">Tempo (s)</th><th class="th">Força (N)</th><th class="th">Isolamento (MΩ)</th><th class="th">Pico (A)</th><th class="th">Vibração (mm/s)</th><th class="th">Temp. motor (°C)</th><th class="th">Umidade (%)</th><th class="th">Ocorrência</th><th class="th">Origem</th><th class="th">Resp.</th><th class="th">Observações</th><th class="th"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($leituras as $l)
                <tr>
                    <td class="td whitespace-nowrap">{{ $l->medido_em->format('d/m/Y H:i') }}</td>
                    <td class="td">{{ $l->tempo_manobra_s !== null ? number_format($l->tempo_manobra_s, 2, ',', '') : '—' }}</td>
                    <td class="td">{{ $l->forca_n !== null ? number_format($l->forca_n, 0, ',', '.') : '—' }}</td>
                    <td class="td">{{ $l->isolamento_mohm !== null ? number_format($l->isolamento_mohm, 2, ',', '') : '—' }}</td>
                    <td class="td">{{ $l->corrente_pico !== null ? number_format($l->corrente_pico, 2, ',', '') : '—' }}</td>
                    @foreach (['vibracao_mm_s', 'temperatura_motor_c', 'umidade_gabinete_pct'] as $campo)
                        <td class="td">{{ $l->{$campo} !== null ? number_format($l->{$campo}, 1, ',', '') : '—' }}</td>
                    @endforeach
                    <td class="td">{{ $l->ocorrencia }}</td>
                    <td class="td whitespace-nowrap">{{ $origens[$l->origem] ?? $l->origem }}</td>
                    <td class="td">{{ $l->responsavel }}</td>
                    <td class="td">{{ $l->observacoes }}</td>
                    <td class="td">
                        <form method="POST" action="{{ route('situacao.leitura.destroy', [$maquina->codigo, $l->id]) }}" onsubmit="return confirm('Excluir este lançamento?')">
                            @csrf @method('DELETE')
                            <button class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-red-700 underline hover:bg-red-50" type="submit" aria-label="Excluir a leitura de {{ $l->medido_em->format('d/m/Y H:i') }}">Excluir</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</details>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const g = @json($graficos);
    graficoLinha({ id: 'g-tempo', titulo: `Tempo de manobra (s) — nominal ${g.limites.tempo_nominal.toFixed(1).replace('.', ',')} s`, rotulos: g.rotulos, valores: g.tempo, cor: '#1f77b4', faixa: g.limites.tempo, limites: g.limites.tempo });
    graficoLinha({ id: 'g-forca', titulo: `Força de acionamento (N) — nominal ${g.limites.forca_nominal.toLocaleString('pt-BR')} N`, rotulos: g.rotulos, valores: g.forca, cor: '#8e44ad', faixa: g.limites.forca, limites: g.limites.forca });
    graficoLinha({ id: 'g-iso', titulo: 'Isolamento (MΩ)', rotulos: g.rotulos, valores: g.isolamento, cor: '#2e9e5b', limites: [g.limites.isolamento] });
    graficoLinha({ id: 'g-pico', titulo: 'Corrente de pico (A)', rotulos: g.rotulos, valores: g.pico, cor: '#e0a800' });
    // linhas de limite: alerta e crítico
    Object.entries(g.condicao).forEach(([campo, c]) => graficoLinha({ id: `g-${campo}`, titulo: c.titulo, rotulos: g.rotulos, valores: c.valores, cor: '#5f22f3', limites: c.limites }));
});
</script>
@endpush
