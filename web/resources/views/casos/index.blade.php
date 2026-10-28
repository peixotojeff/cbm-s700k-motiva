@extends('layouts.app')
@section('titulo', 'Casos reais de falha')
@section('subtitulo', 'Eventos extraídos das apresentações de monitoramento (Maio/2017, Setembro/2017) — linhas X-08, X-12 e X-13.')

@section('conteudo')
<div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-4">
    <x-kpi titulo="Eventos" :valor="$total" />
    <x-kpi titulo="Máquinas distintas" :valor="$maquinasDistintas" />
    <x-kpi titulo="Sem anomalia na corrente" :valor="$semAnomalia" ajuda="Falhas que a curva de corrente sozinha não detectou (retorno do fuso)" />
    <x-kpi titulo="Falhas evitadas (preditiva)" :valor="$evitadas" />
</div>

<div class="mb-6 rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm text-brand-900">
    <strong>Insight para o modelo:</strong> {{ $semAnomalia }} de {{ $total }} eventos (retorno do fuso após travamento) tiveram corrente e tempo de percurso
    normais. Para cobri-los, o CBM precisa também do estado da microswitch / correspondência, não só da curva de corrente.
</div>

<div class="mb-6 grid gap-4 lg:grid-cols-2">
    <div class="card"><div class="h-80"><canvas id="g-cat"></canvas></div></div>
    <div class="card"><div class="h-80"><canvas id="g-linha"></canvas></div></div>
</div>

<form method="GET" class="card mb-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
    <div>
        <label class="label" for="maquina">Máquina</label>
        <select class="input" id="maquina" name="maquina[]" multiple size="4">
            @foreach ($opcoesMaquina as $o)<option @selected(in_array($o, $maq))>{{ $o }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="label" for="categoria">Categoria</label>
        <select class="input" id="categoria" name="categoria[]" multiple size="4">
            @foreach ($opcoesCategoria as $o)<option @selected(in_array($o, $cat))>{{ $o }}</option>@endforeach
        </select>
    </div>
    <div class="flex gap-2"><button class="btn-primary" type="submit">Filtrar</button><a class="btn-ghost" href="{{ route('casos.index') }}">Limpar</a></div>
</form>

<div class="space-y-3">
@forelse ($eventos as $e)
    <details class="card">
        <summary class="cursor-pointer text-sm font-semibold text-slate-900">{{ $e->data->format('d/m/Y') }} — {{ $e->maquina }} — {{ $e->categoria }}</summary>
        <div class="mt-4 grid gap-4 lg:grid-cols-5">
            <div class="space-y-2 text-sm lg:col-span-2">
                <p><strong>Sintoma:</strong> {{ $e->sintoma }}</p>
                <p><strong>Causa / ação:</strong> {{ $e->causa_acao }}</p>
                <p><strong>Anomalia na corrente:</strong> {{ $e->anomalia_corrente }}</p>
                @if ($e->observacao)<p class="text-sm text-slate-600">Obs.: {{ $e->observacao }}</p>@endif
                <p class="text-sm text-slate-600">Fonte: apresentação {{ $e->fonte }}</p>
            </div>
            <div class="flex flex-wrap gap-3 lg:col-span-3">
                @foreach ($e->imagens() as $img)
                    <a href="{{ asset($img) }}" target="_blank"><img class="h-44 rounded-lg border border-slate-200 object-contain" src="{{ asset($img) }}" alt="Gráfico do evento {{ $e->maquina }} em {{ $e->data->format('d/m/Y') }}" loading="lazy"></a>
                @endforeach
            </div>
        </div>
    </details>
@empty
    <div class="card text-sm text-slate-600">Nenhum evento para o filtro escolhido.</div>
@endforelse
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cat = @json($grafCategorias);
    descreverGrafico(document.getElementById('g-cat'), 'Eventos por categoria: ' + cat.rotulos.map((r, i) => `${r} ${cat.valores[i]}`).join('; ') + '.');
    new Chart(document.getElementById('g-cat'), {
        type: 'bar',
        data: { labels: cat.rotulos, datasets: [{ data: cat.valores, backgroundColor: '#5f22f3' }] },
        options: { indexAxis: 'y', plugins: { legend: { display: false }, title: { display: true, text: 'Eventos por categoria', align: 'start' } }, scales: { x: { ticks: { precision: 0 } } } },
    });
    const linha = @json($grafLinha);
    const maquinas = [...new Set(linha.map((p) => p.y))].sort();
    const cats = [...new Set(linha.map((p) => p.cat))];
    const cores = ['#5f22f3', '#ff692d', '#00c9e5', '#b93ae2', '#4510d2', '#2e9e5b', '#d64545', '#94a3b8'];
    descreverGrafico(document.getElementById('g-linha'), `Linha do tempo: ${linha.length} eventos em ${maquinas.length} máquinas, de `
        + `${new Date(linha[0].x).toLocaleDateString('pt-BR')} a ${new Date(linha.at(-1).x).toLocaleDateString('pt-BR')}. A lista completa está abaixo.`);
    new Chart(document.getElementById('g-linha'), {
        type: 'scatter',
        data: { datasets: cats.map((c, i) => ({
            label: c, backgroundColor: cores[i % cores.length], pointRadius: 5,
            data: linha.filter((p) => p.cat === c).map((p) => ({ x: new Date(p.x).getTime(), y: maquinas.indexOf(p.y) })),
        })) },
        options: {
            plugins: { legend: { display: false }, title: { display: true, text: 'Linha do tempo', align: 'start' },
                tooltip: { callbacks: { label: (c) => `${c.dataset.label} — ${maquinas[c.parsed.y]} — ${new Date(c.parsed.x).toLocaleDateString('pt-BR')}` } } },
            scales: {
                x: { type: 'linear', ticks: { callback: (v) => new Date(v).toLocaleDateString('pt-BR', { month: '2-digit', year: '2-digit' }), maxTicksLimit: 8 } },
                y: { type: 'linear', min: -0.5, max: maquinas.length - 0.5, ticks: { stepSize: 1, callback: (v) => maquinas[v] ?? '' } },
            },
        },
    });
});
</script>
@endpush
