@extends('layouts.app')
@section('titulo', 'Simulação ao vivo')
@section('subtitulo', 'O modelo preditivo acompanhando históricos simulados, ciclo a ciclo. Cada ponto é um histórico; as cores mostram o que o modelo diz naquele momento.')

@section('conteudo')
@if ($erro)
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900" role="alert">
        <p class="font-semibold">{{ $erro }}</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">.venv\Scripts\uvicorn ml.main:app --port 8001</pre>
    </div>
@else
<div class="card mb-4 flex flex-wrap items-center gap-4">
    <button id="play" class="btn-primary min-w-36" type="button" aria-label="Iniciar a simulação (barra de espaço)">
        <x-icone nome="play" class="h-5 w-5" /><span>Iniciar</span></button>
    <button id="reiniciar" class="btn-ghost" type="button"><x-icone nome="reiniciar" class="h-5 w-5" />Reiniciar</button>
    <div class="flex items-center gap-1.5 text-sm" role="group" aria-label="Velocidade da simulação">
        <span class="mr-1 text-slate-700">Velocidade</span>
        @foreach ([1 => '1×', 2 => '2×', 4 => '4×', 8 => '8×'] as $v => $r)
            <button type="button" data-vel="{{ $v }}" aria-pressed="{{ $v === 2 ? 'true' : 'false' }}" aria-label="Velocidade {{ $v }} vezes"
                    class="vel min-h-11 min-w-11 rounded-md border border-slate-300 px-3 font-semibold tabular-nums {{ $v === 2 ? 'bg-brand-600 text-white' : 'bg-white' }}">{{ $r }}</button>
        @endforeach
    </div>
    <div class="flex items-center gap-1.5 text-sm" role="group" aria-label="Históricos exibidos">
        <button type="button" data-conj="teste" aria-pressed="true" class="conj min-h-11 rounded-md border border-slate-300 bg-brand-600 px-3 font-semibold text-white"
                title="Só os históricos que o modelo nunca viu no treino — os números batem com o resultado oficial">Teste ({{ number_format(collect($dados['historicos'])->where('teste', true)->count(), 0, ',', '.') }})</button>
        <button type="button" data-conj="todos" aria-pressed="false" class="conj min-h-11 rounded-md border border-slate-300 bg-white px-3 font-semibold"
                title="Inclui os históricos usados no treino: o modelo já os conhece e os números ficam otimistas">Todos ({{ number_format(count($dados['historicos']), 0, ',', '.') }})</button>
    </div>
    <div class="min-w-64 flex-1">
        <label for="barra" class="sr-only">Ciclo da simulação</label>
        <input id="barra" class="h-11 w-full cursor-pointer accent-brand-600" type="range" min="0" max="{{ count($dados['ciclos']) - 1 }}" value="0">
    </div>
    <div class="text-right" aria-live="off">
        <div class="text-2xl font-bold tabular-nums text-slate-900">Ciclo <span id="ciclo">{{ $dados['ciclos'][0] }}</span></div>
        <div class="text-sm text-slate-600">dia <span id="dia" class="tabular-nums"></span> do monitoramento · 1 ciclo = {{ $dados['intervalo_h'] }} h</div>
    </div>
</div>

<div class="mb-4 grid gap-3 sm:grid-cols-3 lg:grid-cols-8">
    <div class="rounded-xl bg-gradient-to-r from-brand-700 to-brand-600 p-3 text-white shadow-sm sm:col-span-3 lg:col-span-2">
        <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-brand-100"><x-icone nome="alvo" class="h-4 w-4" />Assertividade do modelo</div>
        <div class="mt-1 flex items-baseline gap-2">
            <span id="k-assert" class="text-3xl font-bold tabular-nums">—</span>
            <span id="k-assert-det" class="text-sm tabular-nums text-brand-100">aguardando eventos</span>
        </div>
        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/20" aria-hidden="true"><div id="k-assert-barra" class="h-full rounded-full bg-white transition-all" style="width: 0%"></div></div>
        <div class="mt-1.5 text-sm text-brand-100">Da intervenção: <strong id="k-assert-int" class="tabular-nums text-white">—</strong>
            · Precisão: <strong id="k-prec" class="tabular-nums text-white">—</strong></div>
        <details class="mt-1 text-xs text-brand-100">
            <summary class="cursor-pointer underline">Como é calculada</summary>
            <p class="mt-1">Acertos ÷ (acertos + erros). Acertos = alarmes que anteciparam uma falha real, contados quando o alarme dispara.
                Erros = falhas sem aviso + alarmes falsos. Máquinas saudáveis sem alarme não entram na conta.</p>
            <p class="mt-1"><strong>Da intervenção</strong>: só conta como falso a máquina saudável que chegou a <strong>vermelho</strong>.
                O amarelo manda programar inspeção; inspecionar e não achar defeito é verificação, não intervenção errada.</p>
            <p class="mt-1"><strong>Precisão</strong>: dos alarmes dados, quantos eram falhas reais.</p>
        </details>
    </div>
    @foreach ([
        ['k-r', 'Intervir', 'text-red-700', 'Vermelho agora: risco de falha em até 2,5 dias — intervir', 'alerta'],
        ['k-a', 'Programar inspeção', 'text-amber-700', 'Amarelo agora: risco em até 7,5 dias ou perda de correspondência — programar inspeção', 'atencao'],
        ['k-f', 'Falhas ocorridas', 'text-slate-900', 'Históricos que já chegaram à falha', null],
        ['k-ant', 'Antecipadas', 'text-brand-600', 'Falhas que tiveram aviso (amarelo ou vermelho) antes', null],
        ['k-sem', 'Sem aviso', 'text-slate-900', 'Falhas que aconteceram sem alarme prévio', null],
        ['k-falso', 'Avisos falsos', 'text-amber-700', 'Históricos saudáveis que já receberam aviso (amarelo ou vermelho)', null],
    ] as [$id, $rot, $cor, $ajuda, $ico])
        <div class="card !p-3" title="{{ $ajuda }}">
            <div class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                @if ($ico)<x-icone :nome="$ico" class="h-4 w-4 {{ $cor }}" />@endif{{ $rot }}</div>
            <div id="{{ $id }}" class="mt-1 text-2xl font-bold tabular-nums {{ $cor }}">0</div>
            <p class="sr-only">{{ $ajuda }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-4 lg:grid-cols-3">
    <div class="card lg:col-span-2">
        {{-- legenda da grade: além da cor, cada estado tem uma marca (ponto, check, x) --}}
        <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-700">
            @foreach ([['#2e9e5b', null, 'Verde'], ['#e0a800', null, 'Amarelo'], ['#d64545', 'ponto', 'Vermelho'], ['#5f22f3', 'check', 'Falhou — antecipada'], ['#1e1b2e', 'x', 'Falhou — sem aviso']] as [$c, $marca, $rot])
                <span class="inline-flex items-center gap-1.5">
                    <svg class="h-4 w-4" viewBox="0 0 16 16" aria-hidden="true"><rect width="16" height="16" rx="3" fill="{{ $c }}"/>
                        @if ($marca === 'ponto')<circle cx="8" cy="8" r="2.5" fill="#fff"/>@endif
                        @if ($marca === 'check')<path d="M4 8.5l2.5 2.5L12 5.5" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/>@endif
                        @if ($marca === 'x')<path d="M5 5l6 6M11 5l-6 6" stroke="#fff" stroke-width="2" stroke-linecap="round"/>@endif
                    </svg>{{ $rot }}</span>
            @endforeach
            <span class="ml-auto text-slate-600">passe o mouse para ver o histórico; clique para o diagnóstico</span>
        </div>
        <canvas id="grade" class="w-full cursor-pointer" aria-label="Grade com os históricos coloridos pelo estado do modelo" role="img"></canvas>
        <p id="dica" class="mt-1 min-h-5 text-sm text-slate-700"></p>
        <p class="text-sm text-slate-600">Lista completa com risco e diagnóstico de cada histórico no
            <a class="font-medium text-brand-700 underline" href="{{ route('simulacao.index') }}">Painel de simulação</a>.</p>
    </div>
    <div class="card flex flex-col !p-0">
        <h2 class="px-4 pt-4 text-sm font-semibold text-slate-900">Eventos</h2>
        <ul id="eventos" class="mt-2 flex-1 space-y-1 overflow-y-auto px-4 pb-4 text-xs" style="max-height: 420px" aria-label="Eventos da simulação"></ul>
        <p id="anuncio" class="sr-only" role="status"></p>
    </div>
</div>

<div id="aviso-todos" class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-900">
    Exibindo todos os históricos, inclusive os usados no treino: o modelo já os conhece, então a antecipação aparece otimista.
    Para a apresentação, use “Teste”, que reproduz o resultado oficial.
</div>

@php
    // série do gráfico: [cor, traço (css), nome, tipo, o que significa]
    $series = [
        ['#d64545', 'solid 2px', 'Intervir (vermelho)', 'agora',
            'Quantos históricos estão vermelhos neste ciclo: risco de falha em até 2,5 dias, a equipe deve intervir. Cai quando elas falham (saem da conta) ou se recuperam.'],
        ['#e0a800', 'solid 2px', 'Programar inspeção (amarelo)', 'agora',
            'Quantos estão amarelos neste ciclo: o modelo de prazo longo vê risco em até 7,5 dias, ou houve perda de correspondência. A equipe já é avisada para programar a inspeção — dias antes do vermelho.'],
        ['#5f22f3', 'solid 4px', 'Falhas antecipadas', 'acumulado',
            'Total de falhas que já aconteceram e tiveram aviso amarelo ou vermelho antes (até '.number_format($dados['antecedencia_max_h'] / 24, 1, ',', '').' dias). É o ganho do CBM: falhas que a equipe veria chegando.'],
        ['#1e1b2e', 'solid 4px', 'Falhas sem aviso', 'acumulado',
            'Total de falhas que aconteceram sem alarme antes — as que o modelo deixou passar. A maioria é retorno do fuso, que não deixa sinal na corrente.'],
        ['#94a3b8', 'dashed 2px', 'Avisos falsos', 'acumulado',
            'Total de máquinas saudáveis que já receberam aviso (amarelo ou vermelho). No amarelo, é uma inspeção programada sem necessidade; no vermelho, uma intervenção.'],
    ];
@endphp
<div class="card mt-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="font-semibold text-slate-900">Evolução ao longo dos ciclos</h2>
        <details class="max-w-xl text-sm text-slate-700" open>
            <summary class="cursor-pointer font-medium text-brand-700">Como ler este gráfico</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-slate-600">
                <li><strong>Linhas finas</strong> (vermelha e amarela) mostram a situação <strong>no ciclo</strong>: sobem e descem.</li>
                <li><strong>Linhas grossas</strong> e a <strong>tracejada</strong> são <strong>totais acumulados</strong>: só sobem.</li>
                <li>O desejável: a <span class="font-semibold text-brand-700">roxa</span> bem acima da <span class="font-semibold text-slate-900">preta</span>, e a <span class="font-semibold text-slate-500">cinza</span> baixa.</li>
                <li>A amarela sobe <strong>antes</strong> da vermelha, e as duas antes da roxa: é o aviso chegando dias antes da falha — a antecipação acontecendo.</li>
            </ul>
        </details>
    </div>
    <div class="mt-2 h-56"><canvas id="evolucao" aria-label="Gráfico de evolução: alertas, falhas antecipadas, falhas sem aviso e alarmes falsos ao longo dos ciclos" role="img"></canvas></div>
    <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-5" aria-label="Legenda do gráfico (clique para mostrar ou esconder a linha)">
        @foreach ($series as $k => [$cor, $traco, $nome, $tipo, $texto])
            <li>
                <button type="button" data-serie="{{ $k }}" aria-pressed="true"
                        class="serie flex h-full w-full flex-col rounded-lg border border-slate-200 p-3 text-left transition hover:border-slate-300 aria-[pressed=false]:opacity-40">
                    <span class="flex w-full items-center gap-2">
                        <span class="inline-block w-6" style="border-top: {{ $traco }} {{ $cor }}"></span>
                        <span class="text-sm font-semibold text-slate-900">{{ $nome }}</span>
                    </span>
                    <span class="mt-1 flex items-center gap-2 text-xs">
                        <span class="rounded px-1.5 py-0.5 font-medium {{ $tipo === 'agora' ? 'bg-slate-100 text-slate-600' : 'bg-brand-50 text-brand-700' }}">{{ $tipo === 'agora' ? 'situação no ciclo' : 'total acumulado' }}</span>
                        <span class="text-slate-500">neste ciclo: <strong id="leg-{{ $k }}" class="text-slate-900">0</strong></span>
                    </span>
                    <span class="mt-1.5 text-xs leading-snug text-slate-600">{{ $texto }}</span>
                </button>
            </li>
        @endforeach
    </ul>
    <p class="mt-2 text-sm text-slate-600">Clique num item da legenda para mostrar ou esconder a linha.</p>
</div>

<p class="mt-4 text-sm text-slate-600">
    Base simulada: os históricos estão alinhados pelo início do monitoramento (ciclo 0) para serem vistos em paralelo.
    Níveis: {{ $dados['niveis'] ?? '' }} {{ $dados['regra'] }}. Uma falha conta como antecipada se houve aviso até {{ number_format($dados['antecedencia_max_h'] / 24, 1, ',', '') }} dias antes.
    Assertividade = alarmes certos ÷ (alarmes certos + falhas sem aviso + alarmes falsos); cada alarme é julgado quando dispara, e máquinas saudáveis sem alarme não entram na conta.
    Na assertividade da intervenção, só o vermelho em máquina saudável conta como falso.
    Resultado do conjunto de teste: {{ number_format($met['taxa_antecipacao'] * 100, 0) }}% das falhas antecipadas, {{ number_format($met['taxa_alarme_falso'] * 100, 0) }}% de alarme falso.
</p>
@endif
@endsection

@if (empty($erro))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const D = @json($dados);
    const TODOS = D.historicos, CICLOS = D.ciclos, N = CICLOS.length;
    const SO_TESTE = TODOS.filter((h) => h.teste);
    let H = SO_TESTE.length ? SO_TESTE : TODOS;
    const urlDiag = @json(route('simulacao.index'));
    const COR = { G: '#2e9e5b', A: '#e0a800', R: '#d64545', FA: '#5f22f3', FS: '#1e1b2e' };
    const $ = (id) => document.getElementById(id);

    // ---- pré-cálculo: contadores e eventos por ciclo (refeito ao trocar o conjunto exibido)
    let serie, eventos;
    function precalcular() {
    serie = { r: [], a: [], f: [], ant: [], sem: [], falso: [], falsoV: [], certo: [] };
    eventos = Array.from({ length: N }, () => []);
    const falsoAte = new Array(H.length).fill(false), falsoVAte = new Array(H.length).fill(false);
    // ciclo em que cada falha antecipada recebeu o 1º alarme: o acerto conta quando o alarme dispara,
    // do mesmo jeito que o alarme falso conta como erro quando dispara
    const alarmeCerto = H.map((h) => (h.antecedencia_h !== null ? h.ciclo_falha - h.antecedencia_h / D.intervalo_h : Infinity));
    for (let i = 0; i < N; i++) {
        let r = 0, a = 0, f = 0, ant = 0, sem = 0, falso = 0, falsoV = 0, certo = 0;
        H.forEach((h, k) => {
            if (alarmeCerto[k] <= CICLOS[i]) certo++;
            const e = h.estados[i], ant0 = i ? h.estados[i - 1] : 'G';
            if (e === 'R') r++; else if (e === 'A') a++;
            if (e === 'F') { f++; h.antecedencia_h !== null ? ant++ : sem++; }
            if (h.desfecho !== 'falha' && (e === 'R' || e === 'A')) falsoAte[k] = true;
            if (falsoAte[k]) falso++;
            if (h.desfecho !== 'falha' && e === 'R') falsoVAte[k] = true;
            if (falsoVAte[k]) falsoV++;
            if (e === 'F' && ant0 !== 'F') eventos[i].push({ k, tipo: h.antecedencia_h !== null ? 'antecipada' : 'sem' });
            else if (e === 'R' && ant0 !== 'R' && ant0 !== 'F') eventos[i].push({ k, tipo: h.desfecho === 'falha' ? 'alarme' : 'falso' });
        });
        serie.r.push(r); serie.a.push(a); serie.f.push(f); serie.ant.push(ant); serie.sem.push(sem); serie.falso.push(falso); serie.falsoV.push(falsoV); serie.certo.push(certo);
    }
    }
    precalcular();

    // ---- grade de pontos
    const tela = $('grade'), ctx = tela.getContext('2d');
    let COLS = 60, LIN = 0, lado = 0;
    function dimensionar() {
        COLS = H.length > 800 ? 60 : 30;
        LIN = Math.ceil(H.length / COLS);
        const w = tela.clientWidth, dpr = window.devicePixelRatio || 1;
        lado = w / COLS;
        tela.style.height = `${lado * LIN}px`;
        tela.width = w * dpr; tela.height = lado * LIN * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        desenhar();
    }
    function cor(h, e) { return e === 'F' ? (h.antecedencia_h !== null ? COR.FA : COR.FS) : COR[e]; }
    function desenhar() {
        ctx.clearRect(0, 0, tela.width, tela.height);
        const m = Math.max(1, lado * 0.12);
        H.forEach((h, k) => {
            const x = (k % COLS) * lado, y = Math.floor(k / COLS) * lado;
            ctx.fillStyle = cor(h, h.estados[idx]);
            ctx.beginPath();
            ctx.roundRect ? ctx.roundRect(x + m, y + m, lado - 2 * m, lado - 2 * m, 2) : ctx.rect(x + m, y + m, lado - 2 * m, lado - 2 * m);
            ctx.fill();
            marcar(h, h.estados[idx], x + lado / 2, y + lado / 2, (lado - 2 * m) / 2);
        });
    }
    // marca branca além da cor (para quem não distingue cores): ponto = vermelho, check = antecipada, x = sem aviso
    function marcar(h, e, cx, cy, r) {
        if (r < 4 || (e !== 'R' && e !== 'F')) return;
        ctx.save();
        ctx.strokeStyle = ctx.fillStyle = '#fff';
        ctx.lineWidth = Math.max(1.5, r * 0.28);
        ctx.lineCap = 'round';
        ctx.beginPath();
        if (e === 'R') {
            ctx.arc(cx, cy, r * 0.32, 0, 2 * Math.PI); ctx.fill();
        } else if (h.antecedencia_h !== null) {
            ctx.moveTo(cx - r * 0.5, cy + r * 0.05); ctx.lineTo(cx - r * 0.12, cy + r * 0.42); ctx.lineTo(cx + r * 0.52, cy - r * 0.38); ctx.stroke();
        } else {
            ctx.moveTo(cx - r * 0.42, cy - r * 0.42); ctx.lineTo(cx + r * 0.42, cy + r * 0.42);
            ctx.moveTo(cx + r * 0.42, cy - r * 0.42); ctx.lineTo(cx - r * 0.42, cy + r * 0.42); ctx.stroke();
        }
        ctx.restore();
    }
    function pontoEm(ev) {
        const b = tela.getBoundingClientRect();
        const k = Math.floor((ev.clientY - b.top) / lado) * COLS + Math.floor((ev.clientX - b.left) / lado);
        return H[k];
    }
    const ROT = { G: 'verde', A: 'amarelo', R: 'vermelho', F: 'falhou' };
    tela.addEventListener('mousemove', (ev) => {
        const h = pontoEm(ev);
        $('dica').textContent = h ? `${h.id} · ${h.maquina} · agora: ${ROT[h.estados[idx]]} · gabarito: ${h.desfecho === 'falha' ? 'falha (' + h.modo_rotulo + ') no ciclo ' + h.ciclo_falha : 'saudável'}` : '';
    });
    tela.addEventListener('click', (ev) => {
        const h = pontoEm(ev);
        if (h) window.open(`${urlDiag}?maquina=${h.id}&ciclo=${Math.min(CICLOS[idx], (h.ciclo_falha ?? 1e9) - 1)}#diagnostico`, '_blank');
    });

    // ---- gráfico de evolução
    const graf = new Chart($('evolucao'), {
        type: 'line',
        data: { labels: CICLOS, datasets: [
            { label: 'Intervir (vermelho) — no ciclo', data: [], borderColor: '#d64545', backgroundColor: '#d64545', pointRadius: 0, tension: 0.2, borderWidth: 1.75 },
            { label: 'Programar inspeção (amarelo) — no ciclo', data: [], borderColor: '#e0a800', backgroundColor: '#e0a800', pointRadius: 0, tension: 0.2, borderWidth: 1.75 },
            { label: 'Falhas antecipadas — acumulado', data: [], borderColor: '#5f22f3', backgroundColor: '#5f22f3', pointRadius: 0, borderWidth: 3.5 },
            { label: 'Falhas sem aviso — acumulado', data: [], borderColor: '#1e1b2e', backgroundColor: '#1e1b2e', pointRadius: 0, borderWidth: 3.5 },
            { label: 'Avisos falsos — acumulado', data: [], borderColor: '#94a3b8', backgroundColor: '#94a3b8', pointRadius: 0, borderWidth: 2, borderDash: [5, 4] },
        ] },
        options: { animation: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false } },  // legenda explicada fica abaixo do gráfico
            scales: { x: { ticks: { maxTicksLimit: 12 } }, y: { beginAtZero: true, suggestedMax: Math.max(...serie.r, ...serie.ant) } } },
    });

    // ---- feed de eventos
    const TXT = {
        antecipada: (h) => `<span class="font-semibold text-brand-700">FALHA ANTECIPADA</span> ${h.id} · ${h.maquina} — ${h.modo_rotulo}; aviso ${h.antecedencia_h} h antes` + (h.antecedencia_vermelho_h !== null && h.antecedencia_vermelho_h !== undefined && h.antecedencia_vermelho_h < h.antecedencia_h ? ` (vermelho ${h.antecedencia_vermelho_h} h antes)` : ''),
        sem: (h) => `<span class="font-semibold text-slate-900">FALHA SEM AVISO</span> ${h.id} · ${h.maquina} — ${h.modo_rotulo}`,
        alarme: (h) => `<span class="font-semibold text-red-700">ALARME</span> ${h.id} · ${h.maquina} entrou em vermelho`,
        falso: (h) => `<span class="font-semibold text-amber-700">ALARME</span> ${h.id} · ${h.maquina} entrou em vermelho <span class="text-slate-500">(gabarito: saudável)</span>`,
    };
    function publicar(i) {
        const ul = $('eventos');
        eventos[i].filter((e) => e.tipo !== 'alarme' || eventos[i].length < 12).forEach((e) => {
            const li = document.createElement('li');
            li.className = 'rounded border-l-2 bg-slate-50 px-2 py-1 ' + ({ antecipada: 'border-brand-600', sem: 'border-slate-900', alarme: 'border-red-500', falso: 'border-amber-400' })[e.tipo];
            li.innerHTML = `<span class="text-slate-500">c${CICLOS[i]}</span> ` + TXT[e.tipo](H[e.k]);
            ul.prepend(li);
        });
        while (ul.children.length > 60) ul.lastChild.remove();
    }

    // ---- reprodução
    let idx = 0, vel = 2, timer = null;
    function mostrar(i, comEventos = true) {
        idx = i;
        $('barra').value = i;
        $('ciclo').textContent = CICLOS[i];
        $('dia').textContent = (CICLOS[i] * D.intervalo_h / 24).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
        $('k-r').textContent = serie.r[i]; $('k-a').textContent = serie.a[i]; $('k-f').textContent = serie.f[i];
        $('k-ant').textContent = serie.ant[i]; $('k-sem').textContent = serie.sem[i]; $('k-falso').textContent = serie.falso[i];
        // assertividade = acertos / (acertos + erros)
        // acertos = alarmes que anteciparam uma falha real; erros = falhas sem aviso + alarmes falsos
        const acertos = serie.certo[i], erros = serie.sem[i] + serie.falso[i], pct = (v) => `${Math.round(v * 100)}%`;
        if (acertos + erros && acertos + erros < 20) {  // poucas decisões: uma porcentagem aqui não diria nada
            $('k-assert').textContent = '—';
            $('k-assert-det').textContent = `coletando dados (${acertos + erros} de 20 decisões)`;
            $('k-assert-barra').style.width = '0%';
        } else if (acertos + erros) {
            $('k-assert').textContent = pct(acertos / (acertos + erros));
            $('k-assert-det').textContent = `${acertos} alarmes certos · ${erros} erros`;
            $('k-assert-barra').style.width = pct(acertos / (acertos + erros));
        } else {
            $('k-assert').textContent = '—'; $('k-assert-det').textContent = 'aguardando eventos'; $('k-assert-barra').style.width = '0%';
        }
        $('k-prec').textContent = acertos + serie.falso[i] >= 20 ? pct(acertos / (acertos + serie.falso[i])) : '—';
        const errosInt = serie.sem[i] + serie.falsoV[i];
        $('k-assert-int').textContent = acertos + errosInt >= 20 ? pct(acertos / (acertos + errosInt)) : '—';
        const ate = (s) => s.slice(0, i + 1);
        graf.data.datasets[0].data = ate(serie.r); graf.data.datasets[1].data = ate(serie.a);
        graf.data.datasets[2].data = ate(serie.ant); graf.data.datasets[3].data = ate(serie.sem); graf.data.datasets[4].data = ate(serie.falso);
        [serie.r, serie.a, serie.ant, serie.sem, serie.falso].forEach((s, k) => { $(`leg-${k}`).textContent = s[i]; });
        graf.update('none');
        if (comEventos) publicar(i);
        desenhar();
    }
    // botão de reprodução: ícone SVG + texto + nome acessível
    const ICONE = {
        play: '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/></svg>',
        pausa: '<svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/></svg>',
    };
    function botao(icone, texto) {
        $('play').innerHTML = `${ICONE[icone]}<span>${texto}</span>`;
        $('play').setAttribute('aria-label', `${texto} a simulação (barra de espaço)`);
    }
    // ao pausar, um resumo único para leitores de tela (o feed não é lido a cada ciclo para não sobrecarregar)
    function anunciar() {
        $('anuncio').textContent = `Pausado no ciclo ${CICLOS[idx]}: ${serie.r[idx]} para intervir, ${serie.a[idx]} para programar inspeção, ${serie.ant[idx]} falhas antecipadas, `
            + `${serie.sem[idx]} sem aviso, ${serie.falso[idx]} avisos falsos. Assertividade ${$('k-assert').textContent}, da intervenção ${$('k-assert-int').textContent}.`;
    }
    function parar() { clearInterval(timer); timer = null; botao('play', 'Continuar'); anunciar(); }
    function tocar() {
        if (idx >= N - 1) reiniciar();
        botao('pausa', 'Pausar');
        timer = setInterval(() => { if (idx >= N - 1) { parar(); botao('play', 'Iniciar'); return; } mostrar(idx + 1); }, 700 / vel);
    }
    function reiniciar() { parar(); $('eventos').innerHTML = ''; mostrar(0); botao('play', 'Iniciar'); }
    $('play').addEventListener('click', () => (timer ? parar() : tocar()));
    $('reiniciar').addEventListener('click', reiniciar);
    $('barra').addEventListener('input', (e) => { parar(); $('eventos').innerHTML = ''; mostrar(+e.target.value, false); });
    document.querySelectorAll('.vel').forEach((b) => b.addEventListener('click', () => {
        vel = +b.dataset.vel;
        document.querySelectorAll('.vel').forEach((x) => {
            x.classList.toggle('bg-brand-600', x === b);
            x.classList.toggle('text-white', x === b);
            x.classList.toggle('bg-white', x !== b);
            x.setAttribute('aria-pressed', String(x === b));
        });
        if (timer) { parar(); tocar(); }
    }));
    document.querySelectorAll('.serie').forEach((b) => b.addEventListener('click', () => {
        const k = +b.dataset.serie, visivel = !graf.isDatasetVisible(k);
        graf.setDatasetVisibility(k, visivel);
        b.setAttribute('aria-pressed', String(visivel));
        graf.update('none');
    }));
    document.querySelectorAll('.conj').forEach((b) => b.addEventListener('click', () => {
        H = b.dataset.conj === 'teste' && SO_TESTE.length ? SO_TESTE : TODOS;
        document.querySelectorAll('.conj').forEach((x) => {
            x.classList.toggle('bg-brand-600', x === b);
            x.classList.toggle('text-white', x === b);
            x.classList.toggle('bg-white', x !== b);
            x.setAttribute('aria-pressed', String(x === b));
        });
        $('aviso-todos').classList.toggle('hidden', b.dataset.conj === 'teste');
        precalcular();
        graf.options.scales.y.suggestedMax = Math.max(...serie.r, ...serie.ant);
        $('eventos').innerHTML = '';
        dimensionar();
        mostrar(idx, false);
    }));
    document.addEventListener('keydown', (e) => { if (e.code === 'Space' && e.target === document.body) { e.preventDefault(); timer ? parar() : tocar(); } });
    window.addEventListener('resize', dimensionar);
    dimensionar();
    mostrar(0, false);
});
</script>
@endpush
@endif
