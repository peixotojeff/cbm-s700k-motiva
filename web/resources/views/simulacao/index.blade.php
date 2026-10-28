@extends('layouts.app')
@section('titulo', 'Painel de simulação')
@section('subtitulo', 'Modelo preditivo (XGBoost + SHAP) sobre a base de históricos simulados, calibrados nos 16 casos reais de 2016–2017. Para as máquinas reais, use Registrar leitura.')

@php
    $pct = fn ($v) => $v === null ? '—' : number_format($v * 100, 0).'%';
@endphp

@section('conteudo')
<p class="mb-5 flex items-start gap-1.5 text-sm text-amber-700"><x-icone nome="atencao" class="mt-0.5 h-4 w-4 shrink-0" /><span>Base <strong>simulada</strong> para demonstração. Os números mostram o método funcionando; o desempenho real só se mede com curvas reais da Linha 4 e com o resultado das inspeções após alerta.</span></p>

@isset($campo)
    {{-- Medida real: vem das inspeções após alerta e das falhas registradas em Registrar leitura. --}}
    <section class="card mb-6 border-l-4 border-l-brand-600" aria-labelledby="campo-titulo">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-2xl">
                <h2 id="campo-titulo" class="flex items-center gap-2 font-semibold text-slate-900"><x-icone nome="alvo" class="h-5 w-5 text-brand-600" />Assertividade em campo
                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-semibold text-emerald-800">registros do app</span></h2>
                @if ($campo['com_exemplo'])
                    <p class="mt-1 text-sm text-amber-700">Inclui os dados de exemplo (MOCK). Remova-os em Importação e dados para medir só o campo.</p>
                @endif
                <p class="mt-1 text-sm text-slate-600">
                    Medida com os registros da equipe: <strong>acerto</strong> = inspeção após alerta com defeito confirmado, ou falha com a máquina já em alerta;
                    <strong>erro</strong> = inspeção após alerta sem defeito, ou falha (corretiva, travamento, perda de correspondência) com a máquina verde.
                    Registre o resultado em <a class="text-brand-600 underline" href="{{ route('registro.create') }}">Registrar leitura</a> → tipo de ocorrência
                    “Inspeção após alerta: …”.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-3 text-center">
                @foreach ([['Assertividade', $campo['geral']], ['Da intervenção', $campo['intervencao']]] as [$rot, $v])
                    <div class="min-w-32 rounded-lg bg-brand-50 px-3 py-2 ring-1 ring-brand-100">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ $rot }}</div>
                        <div class="text-2xl font-bold tabular-nums text-brand-700">{{ $v === null ? '—' : number_format($v * 100, 0).'%' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="mt-3 text-sm tabular-nums text-slate-700">
            {{ $campo['acertos'] }} {{ $campo['acertos'] === 1 ? 'acerto' : 'acertos' }} ·
            {{ $campo['sem_aviso'] }} {{ $campo['sem_aviso'] === 1 ? 'falha' : 'falhas' }} sem aviso ·
            {{ $campo['falsos'] }} {{ $campo['falsos'] === 1 ? 'alerta' : 'alertas' }} sem defeito
            ({{ $campo['falsos_vermelho'] }} em vermelho)
            @if ($campo['decisoes'] < $campo['minimo'])
                — <span class="text-slate-600">coletando dados: {{ $campo['decisoes'] }} de {{ $campo['minimo'] }} decisões para mostrar a porcentagem.</span>
            @endif
        </p>
    </section>
@endisset

@if ($erro)
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900" role="alert">
        <p class="font-semibold">{{ $erro }}</p>
        <p class="mt-2">Inicie o serviço do modelo na raiz do projeto e recarregue a página:</p>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100">.venv\Scripts\python run_pipeline.py        # só na primeira vez
.venv\Scripts\uvicorn ml.main:app --port 8001</pre>
    </div>
@else
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @php
            $verm = $met['vermelho'] ?? null;
            $ass = $met['assertividade'] ?? null;
        @endphp
        @if ($ass)
            <x-kpi titulo="Assertividade" :valor="$pct($ass['geral'])" cor="text-brand-700"
                   :detalhe="'da intervenção: '.$pct($ass['intervencao'])"
                   :ajuda="$ass['acertos'].' falhas avisadas ÷ ('.$ass['acertos'].' + '.$ass['sem_aviso'].' sem aviso + '.$ass['falsos_aviso'].' avisos falsos). Na da intervenção, só contam como falsos os '.$ass['falsos_vermelho'].' saudáveis que chegaram a vermelho.'" />
        @endif
        <x-kpi titulo="Falhas com aviso" :valor="$pct($met['taxa_antecipacao'])" cor="text-brand-600"
               :detalhe="$verm ? 'só vermelho: '.$pct($verm['taxa_antecipacao']) : null"
               :ajuda="$met['falhas_antecipadas'].' de '.$met['falhas_teste'].' falhas do teste tiveram aviso (amarelo ou vermelho) até '.number_format($met['antecedencia_max_h'] / 24, 1, ',', '').' dias antes'" />
        <x-kpi titulo="Antecedência do 1º aviso" :valor="$met['antecedencia_mediana_h'] !== null ? number_format($met['antecedencia_mediana_h'], 0).' h' : '—'" cor="text-brand-600"
               :detalhe="$verm && $verm['antecedencia_mediana_h'] !== null ? 'até o vermelho: '.number_format($verm['antecedencia_mediana_h'], 0).' h' : null"
               ajuda="Mediana de quantas horas antes da falha a equipe recebeu o primeiro aviso" />
        <x-kpi titulo="Aviso falso" :valor="$pct($met['taxa_alarme_falso'])" cor="text-amber-700"
               :detalhe="$verm ? 'vermelho falso: '.$pct($verm['taxa_alarme_falso']) : null"
               :ajuda="$met['saudaveis_com_alarme'].' de '.$met['saudaveis_teste'].' históricos saudáveis receberam ao menos um aviso no período'" />
        <x-kpi titulo="Acerto do modo de falha" :valor="$pct($met['acerto_modo_geral'])" :detalhe="'AUC '.number_format($met['auc'], 2, ',', '')"
               ajuda="Entre as leituras que antecedem falhas, quantas tiveram o modo identificado corretamente" />
    </div>

    @if (isset($met['limiar_amarelo']))
        @php
            $num = fn ($v, $c = 2) => number_format($v, $c, ',', '');
            $h = fn ($v) => $v === null ? '—' : number_format($v, 0).' h (~'.number_format($v / 24, 1, ',', '').' dias)';
            $diasH = fn ($ciclos) => number_format($ciclos * $met['intervalo_h'] / 24, 1, ',', '');
            $saida = $met['saida_histerese'] ?? 0.6;
            $teto = $met['teto_aviso_falso'] ?? ['vermelho' => $met['alarme_falso_max'], 'amarelo' => $met['alarme_falso_aviso_max'] ?? null];
        @endphp
        <section class="mb-6" aria-labelledby="regras-titulo">
            <h2 id="regras-titulo" class="mb-3 text-lg font-semibold text-slate-900">Regras dos alertas</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                {{-- AMARELO --}}
                <article class="card border-l-4 border-l-amber-500">
                    <header class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-900 ring-1 ring-amber-600/20">
                            <x-icone nome="atencao" class="h-5 w-5" />Amarelo</span>
                        <span class="text-base font-semibold text-slate-900">Programar inspeção</span>
                    </header>
                    <dl class="mt-3 space-y-3 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold text-slate-900">O que dispara <span class="font-normal text-slate-600">(qualquer um dos dois)</span></dt>
                            <dd>
                                <ol class="mt-1 list-decimal space-y-1 pl-5">
                                    <li><strong>Modelo de prazo longo</strong> — risco de falha em até <strong>{{ $diasH($met['horizonte_aviso_ciclos']) }} dias</strong>
                                        acima de <strong>{{ $num($met['limiar_amarelo']) }}</strong> em <strong>2 das últimas 3 leituras</strong>.</li>
                                    <li><strong>Perda de correspondência</strong> registrada nas últimas 10 leituras (~2,5 dias) — dispara na hora, sem esperar o modelo.
                                        É o único precursor do retorno do fuso, que não altera a corrente.</li>
                                </ol>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Quando desliga</dt>
                            <dd>Quando as <strong>3 últimas leituras</strong> ficam abaixo de {{ number_format($saida * 100, 0) }}% do limiar ({{ $num($met['limiar_amarelo'] * $saida) }})
                                e não há perda de correspondência nas últimas 10 leituras. Uma leitura boa isolada não apaga o aviso.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">O que a equipe faz</dt>
                            <dd>Programar a inspeção do componente indicado (modo provável) e verificar se há peça em estoque. Não exige intervenção imediata.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Calibração</dt>
                            <dd>@if (isset($met['calibracao']))
                                    Os dois limiares são escolhidos juntos, na validação, para a <strong>maior assertividade</strong> — com a trava de
                                    <strong>não avisar menos falhas</strong> que a regra anterior (no máximo {{ $teto['amarelo'] !== null ? number_format($teto['amarelo'] * 100, 0) : '—' }}% de saudáveis em amarelo).
                                @else
                                    Limiar escolhido para avisar o máximo de falhas com no máximo <strong>{{ $teto['amarelo'] !== null ? number_format($teto['amarelo'] * 100, 0) : '—' }}%</strong>
                                    das máquinas saudáveis recebendo aviso (inspeção programada custa menos que intervir).
                                @endif</dd>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-3">
                            <dt class="font-semibold text-amber-900">Desempenho no teste</dt>
                            <dd class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 tabular-nums text-amber-950">
                                <span>Falhas com aviso: <strong>{{ $pct($met['taxa_antecipacao']) }}</strong></span>
                                <span>1º aviso: <strong>{{ $h($met['antecedencia_mediana_h']) }}</strong> antes</span>
                                <span>Folga até o vermelho: <strong>{{ $h($met['folga_amarelo_vermelho_h'] ?? null) }}</strong></span>
                                <span>Aviso falso: <strong>{{ $pct($met['taxa_alarme_falso']) }}</strong></span>
                            </dd>
                        </div>
                    </dl>
                </article>

                {{-- VERMELHO --}}
                <article class="card border-l-4 border-l-red-600">
                    <header class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-900 ring-1 ring-red-600/20">
                            <x-icone nome="alerta" class="h-5 w-5" />Vermelho</span>
                        <span class="text-base font-semibold text-slate-900">Intervir</span>
                    </header>
                    <dl class="mt-3 space-y-3 text-sm text-slate-700">
                        <div>
                            <dt class="font-semibold text-slate-900">O que dispara</dt>
                            <dd><strong>Modelo de curto prazo</strong> — risco de falha em até <strong>{{ $diasH($met['horizonte_ciclos']) }} dias</strong>
                                acima de <strong>{{ $num($met['limiar_vermelho']) }}</strong> em <strong>2 das últimas 3 leituras</strong>.
                                O vermelho tem prioridade: quando ele liga, substitui o amarelo.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Quando desliga</dt>
                            <dd>Quando as <strong>3 últimas leituras</strong> ficam abaixo de {{ number_format($saida * 100, 0) }}% do limiar ({{ $num($met['limiar_vermelho'] * $saida) }}).
                                Se o risco de prazo longo continuar alto, a máquina volta para amarelo, não para verde.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">O que a equipe faz</dt>
                            <dd>Intervir na máquina: o diagnóstico mostra o modo de falha provável, os fatores que elevaram o risco (SHAP) e a ação recomendada.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-slate-900">Calibração</dt>
                            <dd>@if (isset($met['calibracao']))
                                    Escolhido junto com o amarelo para a <strong>maior assertividade</strong>, sem avisar menos falhas que a regra anterior
                                    (no máximo {{ number_format($teto['vermelho'] * 100, 0) }}% de saudáveis em vermelho). Cada vermelho falso é uma ida desnecessária a campo.
                                @else
                                    Limiar escolhido para antecipar o máximo de falhas com no máximo <strong>{{ number_format($teto['vermelho'] * 100, 0) }}%</strong>
                                    das máquinas saudáveis em vermelho (cada alarme falso é uma ida desnecessária a campo).
                                @endif</dd>
                        </div>
                        <div class="rounded-lg bg-red-50 p-3">
                            <dt class="font-semibold text-red-900">Desempenho no teste</dt>
                            <dd class="mt-1 grid grid-cols-2 gap-x-4 gap-y-1 tabular-nums text-red-950">
                                <span>Falhas com vermelho: <strong>{{ $pct($verm['taxa_antecipacao'] ?? null) }}</strong></span>
                                <span>Vermelho: <strong>{{ $h($verm['antecedencia_mediana_h'] ?? null) }}</strong> antes</span>
                                <span>Alarme falso: <strong>{{ $pct($verm['taxa_alarme_falso'] ?? null) }}</strong></span>
                                <span>Liga/desliga por saudável: <strong>{{ ($verm['saudaveis_com_alarme'] ?? 0) ? $num($verm['trocas_status_saudaveis'] / $verm['saudaveis_com_alarme'], 1) : '—' }}</strong></span>
                            </dd>
                        </div>
                    </dl>
                </article>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <p class="card flex items-start gap-2 !p-4 text-sm text-slate-700">
                    <x-icone nome="ok" class="mt-0.5 h-5 w-5 text-emerald-700" />
                    <span><strong class="text-emerald-800">Verde</strong> — nenhuma das condições acima. Segue o plano de manutenção preventiva normal.</span>
                </p>
                @if (isset($met['avisos_com_folga_reposicao']))
                    <div class="card !p-4 text-sm text-slate-700" role="note">
                        <p class="flex items-start gap-2"><x-icone nome="aguardando" class="mt-0.5 h-5 w-5 text-slate-600" />
                            <span><strong class="text-slate-900">Reposição de peças em {{ $met['dias_reposicao'] }} dias:</strong>
                                o amarelo dá, em mediana, {{ $h($met['folga_amarelo_vermelho_h']) }} de folga até o vermelho, e só
                                <strong>{{ number_format($met['avisos_com_folga_reposicao'] * 100, 1, ',', '') }}%</strong> dos avisos dão {{ $met['dias_reposicao'] }} dias ou mais.
                                Para peças com esse prazo, o aviso sozinho não basta: é preciso <strong>estoque de segurança</strong>, dimensionado pela previsão de demanda de peças.</span></p>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <a href="{{ route('simulacao.animacao') }}" class="card flex items-center gap-4 border-brand-200 bg-gradient-to-r from-brand-700 to-brand-600 text-white hover:opacity-95">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15"><x-icone nome="play" class="h-6 w-6" /></span>
            <span><span class="block font-semibold">Simulação ao vivo</span><span class="text-sm text-brand-100">Ciclos {{ $meta['ciclo_min'] }} a {{ $meta['ciclo_max'] }} animados: alarmes e falhas acontecendo</span></span>
        </a>
        @if ($sem)
            <div class="card overflow-x-auto lg:col-span-2">
                <h2 class="text-sm font-semibold text-slate-900">Comparação dos níveis de alerta</h2>
                <table class="mt-2 min-w-full text-sm">
                    <thead><tr><th class="th !px-2"></th><th class="th !px-2">Falhas avisadas</th><th class="th !px-2">Antecedência mediana</th><th class="th !px-2">Aviso falso</th><th class="th !px-2">Liga/desliga por saudável avisado</th></tr></thead>
                    <tbody>
                    @foreach (array_filter([['Amarelo ou vermelho', $met, true], isset($met['vermelho']) ? ['Só vermelho', $met['vermelho'], false] : null, ['Vermelho por leitura isolada', $sem, false]]) as [$rot, $m, $atual])
                        <tr class="{{ $atual ? 'font-semibold text-slate-900' : 'text-slate-600' }}">
                            <td class="td !px-2 !py-1.5">{{ $rot }} @if ($atual)<span class="ml-1 rounded bg-brand-50 px-1.5 text-xs font-medium text-brand-700">em uso</span>@endif</td>
                            <td class="td !px-2 !py-1.5">{{ $pct($m['taxa_antecipacao']) }}</td>
                            <td class="td !px-2 !py-1.5">{{ $m['antecedencia_mediana_h'] !== null ? number_format($m['antecedencia_mediana_h'], 0).' h' : '—' }}</td>
                            <td class="td !px-2 !py-1.5">{{ $pct($m['taxa_alarme_falso']) }}</td>
                            <td class="td !px-2 !py-1.5">{{ $m['saudaveis_com_alarme'] ? number_format($m['trocas_status_saudaveis'] / $m['saudaveis_com_alarme'], 1, ',', '') : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="mt-2 text-sm text-slate-600">O amarelo chega antes do vermelho: a equipe programa a inspeção com dias de folga. Persistência e histerese fazem cada aviso ligar e desligar uma vez (valor ~2,0), sem piscar.</p>
            </div>
        @endif
    </div>

    @if ($cen = $met['cenario_correspondencia'] ?? null)
        <section class="card mb-6 border-dashed border-brand-200 bg-brand-50/40">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="max-w-2xl">
                    <h2 class="font-semibold text-slate-900">Cenário: monitorar o tempo de confirmação da correspondência
                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">hipótese</span></h2>
                    <p class="mt-1 text-sm text-slate-600">
                        O retorno do fuso não deixa sinal na corrente (casos 8, 9 e 16), mas alterou o status da microswitch.
                        Se o sistema medir quanto a detecção demora para confirmar a posição após cada manobra, o modelo passa a enxergar esse modo.
                        Os números abaixo supõem que esse atraso cresce antes da falha — <strong>precisa ser validado com a Motiva</strong>; não entram no modelo em uso.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-3 text-center">
                    @foreach (array_filter([['Antecipadas', $met['taxa_antecipacao'], $cen['taxa_antecipacao']], ['Fuso', $met['por_modo']['fuso_retorno']['taxa'] ?? null, $cen['por_modo']['fuso_retorno'] ?? null], ['Alarme falso', $met['taxa_alarme_falso'], $cen['taxa_alarme_falso']],
                        isset($cen['assertividade']) ? ['Assertividade', $met['assertividade']['geral'] ?? null, $cen['assertividade']['geral']] : null,
                        isset($cen['assertividade']) ? ['Da intervenção', $met['assertividade']['intervencao'] ?? null, $cen['assertividade']['intervencao']] : null]) as [$rot, $hoje, $com])
                        <div class="rounded-lg bg-white px-3 py-2 ring-1 ring-slate-200">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $rot }}</div>
                            <div class="text-sm text-slate-500">hoje {{ $pct($hoje) }}</div>
                            <div class="text-xl font-bold text-brand-700">{{ $pct($com) }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="card mb-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-slate-900">Base de casos simulados</h2>
            <p class="text-sm text-slate-600">{{ number_format($casos['falhas'], 0, ',', '.') }} falhas + {{ number_format($casos['saudaveis'], 0, ',', '.') }} históricos saudáveis ·
                {{ $casos['maquinas_fisicas'] }} máquinas · {{ $pct($casos['jacare']) }} jacaré móvel · {{ \Illuminate\Support\Carbon::parse($casos['periodo'][0])->format('m/Y') }} a {{ \Illuminate\Support\Carbon::parse($casos['periodo'][1])->format('m/Y') }}</p>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead><tr><th class="th">Modo de falha</th><th class="th">Casos</th><th class="th">Inspirado nos casos reais</th><th class="th">Antecipadas (teste)</th><th class="th">Antecedência mediana</th><th class="th">Acerto do modo</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($casos['por_modo'] as $m)
                    <tr>
                        <td class="td font-medium">{{ $m['rotulo'] }}</td>
                        <td class="td whitespace-nowrap">{{ $m['casos'] }} <span class="text-sm text-slate-600">({{ $pct($m['participacao']) }})</span></td>
                        <td class="td">{{ $m['casos_reais_base'] ? 'nº '.implode(', ', $m['casos_reais_base']) : 'manual Siemens (sem caso real)' }}</td>
                        <td class="td whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ ($m['taxa_antecipacao'] ?? 0) >= 0.6 ? 'bg-brand-600' : 'bg-amber-500' }}" style="width: {{ ($m['taxa_antecipacao'] ?? 0) * 100 }}%"></div></div>
                                {{ $pct($m['taxa_antecipacao']) }} <span class="text-sm text-slate-600">{{ $m['antecipadas'] }}/{{ $m['teste_casos'] }}</span>
                            </div>
                        </td>
                        <td class="td whitespace-nowrap">{{ $m['antecedencia_mediana_h'] !== null ? number_format($m['antecedencia_mediana_h'], 0).' h (~'.number_format($m['antecedencia_mediana_h'] / 24, 1, ',', '').' d)' : '—' }}</td>
                        <td class="td">{{ $pct($m['acerto_modo']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <details class="mt-4 text-sm text-slate-700">
            <summary class="cursor-pointer font-medium text-slate-900">Como a base foi construída a partir dos 16 casos reais</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li><strong>Proporção dos modos</strong> segue os 10 casos reais com causa (barras/folga 4, atrito/travamento/fim de curso 4, retorno do fuso 3); motor, embreagem e talonamento — que não aparecem nos casos reais — entram com ~6% cada, com assinatura do manual Siemens (seção 11).</li>
                <li><strong>Retorno do fuso não altera corrente nem tempo</strong> (casos 8, 9 e 16): só {{ $casos['com_precursor_fuso'] }} desses casos tiveram perda de correspondência antes. Por isso o modelo antecipa bem menos esse modo — o mesmo limite observado em 2017, que reforça a necessidade de monitorar a microswitch / correspondência.</li>
                <li><strong>Curva de corrente na escala real</strong> (patamar ~1,3 A; no atrito, sobe para ~1,65 A no fim do curso, como no caso 4) e <strong>limites por versão</strong> (agulha 6,0 s / jacaré 6,5 s).</li>
                <li><strong>Realismo:</strong> severidade variável (inclui casos sutis), 30% dos casos com sinais misturados de dois modos, e ruído operacional em máquinas saudáveis (variação de tempo, perda de correspondência isolada).</li>
                <li><strong>Avaliação honesta:</strong> os limiares são calibrados num conjunto de validação (maior assertividade sem avisar menos falhas) e as métricas vêm de {{ $met['historicos_teste'] }} históricos nunca vistos no treino.</li>
            </ul>
        </details>
    </section>

    <form method="GET" class="card mb-6 grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
        <div>
            <label class="label" for="ciclo">Momento da simulação: ciclo <span id="ciclo-valor" class="font-semibold">{{ $ciclo }}</span>
                <span class="font-normal text-slate-500">(1 ciclo = {{ $meta['intervalo_h'] }} h)</span></label>
            <input class="h-11 w-full cursor-pointer accent-brand-600" type="range" id="ciclo" name="ciclo" min="{{ $meta['ciclo_min'] }}" max="{{ $meta['ciclo_max'] }}" value="{{ $ciclo }}"
                   oninput="document.getElementById('ciclo-valor').textContent = this.value">
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            @foreach ($severidades as $sv)
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2"><input class="h-5 w-5 accent-brand-600" type="checkbox" name="sev[]" value="{{ $sv }}" @checked(in_array($sv, $filtro))> {{ $sv }} <span class="tabular-nums text-slate-600">({{ $sim['contagem'][$sv] }})</span></label>
            @endforeach
            <button class="btn-primary" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="card mb-6 max-h-96 overflow-auto !p-0">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="sticky top-0 bg-white"><tr><th class="th">Histórico</th><th class="th">Máquina</th><th class="th">Severidade</th><th class="th">Risco</th><th class="th">Últimos 12 ciclos</th><th class="th">Modo previsto</th><th class="th">Gabarito da simulação</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($itens as $i)
                <tr class="{{ $i['maquina_id'] === $maquina ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                    <td class="td font-medium"><a class="text-brand-600 hover:underline" href="{{ request()->fullUrlWithQuery(['maquina' => $i['maquina_id']]) }}#diagnostico">{{ $i['maquina_id'] }}</a></td>
                    <td class="td whitespace-nowrap">{{ $i['maquina'] }}</td>
                    <td class="td"><x-selo :status="\App\Enums\Status::from($i['severidade'])" /></td>
                    <td class="td">{{ number_format($i['risco'] * 100, 0) }}%</td>
                    <td class="td"><x-tendencia :valores="$i['tendencia'] ?? []" :limiar="$met['limiar_vermelho']" /></td>
                    <td class="td">{{ $i['severidade'] === 'Verde' ? '—' : $i['modo_rotulo'] }}</td>
                    <td class="td">
                        @if ($i['desfecho_real'] === 'falha')
                            <span class="{{ $i['severidade'] !== 'Verde' && $i['modo_provavel'] === $i['modo_real'] ? 'text-emerald-700' : 'text-slate-700' }}">
                                {{ $i['severidade'] !== 'Verde' && $i['modo_provavel'] === $i['modo_real'] ? '✓' : '' }} falhou: {{ $i['modo_real_rotulo'] }}</span>
                        @else
                            <span class="text-slate-500">saudável</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p class="-mt-4 mb-6 text-sm text-slate-600">Mostrando os {{ count($sim['itens']) }} históricos de maior risco de {{ number_format($sim['total'], 0, ',', '.') }}. O gabarito só existe porque a base é simulada — serve para comparar a previsão com o que aconteceu.</p>

    @if ($diag)
        <h2 id="diagnostico" class="mb-3 text-lg font-semibold text-slate-900">Diagnóstico e orientação prática — {{ $diag['maquina_id'] }} · {{ $diag['maquina'] }}</h2>
        <div class="grid gap-4 lg:grid-cols-5">
            <div class="space-y-4 lg:col-span-3">
                <div class="card"><div class="h-72"><canvas id="g-curva"></canvas></div></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="card"><div class="h-48"><canvas id="g-tempo"></canvas></div></div>
                    <div class="card"><div class="h-48"><canvas id="g-iso"></canvas></div></div>
                </div>
            </div>
            <div class="card space-y-3 text-sm lg:col-span-2">
                <p><strong>Severidade:</strong> <x-selo :status="\App\Enums\Status::from($diag['severidade'])" /> — risco {{ number_format($diag['risco'] * 100, 0) }}%</p>
                <p><strong>Modo de falha provável:</strong> {{ $diag['severidade'] === 'Verde' ? '—' : $diag['modo_rotulo'] }}</p>
                <p><strong>Fatores que elevaram o risco (SHAP):</strong> {{ $diag['fatores'] ?: '—' }}</p>
                <div class="rounded-lg bg-brand-50 p-3 text-brand-900">{{ $diag['acao'] }}</div>
                <div class="border-t border-slate-200 pt-3 text-xs text-slate-600">
                    <p class="font-semibold text-slate-800">Gabarito da simulação</p>
                    @if ($diag['caso']['desfecho'] === 'falha')
                        <p>Falha: {{ $diag['modo_real_rotulo'] }} em {{ \Illuminate\Support\Carbon::parse($diag['caso']['data_falha'])->format('d/m/Y') }}
                            · severidade {{ number_format($diag['caso']['severidade'] * 100, 0) }}% · degradação em {{ $diag['caso']['ciclos_degradacao'] }} ciclos · anomalia na corrente: {{ $diag['caso']['anomalia_corrente'] }}</p>
                        <p class="mt-1"><strong>Sintoma:</strong> {{ $diag['caso']['sintoma'] }}</p>
                        <p><strong>Causa / ação:</strong> {{ $diag['caso']['causa_acao'] }}</p>
                        @if ($diag['caso']['evento_base'])<p class="mt-1">Inspirado no caso real nº {{ $diag['caso']['evento_base'] }} (<a class="text-brand-600 underline" href="{{ route('casos.index') }}">Casos reais</a>).</p>@endif
                    @else
                        <p>Histórico saudável: não houve falha no período.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
@endif
@endsection

@if (empty($erro) && ! empty($diag))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const d = @json($diag);
    const cor = { Vermelho: '#d64545', Amarelo: '#e0a800', Verde: '#2e9e5b' }[d.severidade];
    const pontos = (c) => c.t.map((t, i) => ({ x: t, y: c.a[i] }));
    const resumo = (c) => `pico ${Math.max(...c.a).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} A, duração ${(c.t.at(-1)).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} s`;
    descreverGrafico(document.getElementById('g-curva'), `Curva de corrente do motor. Referência (ciclo ${d.curva_base.ciclo}): ${resumo(d.curva_base)}. `
        + `Ciclo ${d.curva_atual.ciclo}: ${resumo(d.curva_atual)}.`);
    new Chart(document.getElementById('g-curva'), {
        type: 'line',
        data: { datasets: [
            { label: `Referência (ciclo ${d.curva_base.ciclo})`, data: pontos(d.curva_base), borderColor: '#888', borderDash: [4, 4], pointRadius: 0 },
            { label: `Ciclo ${d.curva_atual.ciclo}`, data: pontos(d.curva_atual), borderColor: cor, pointRadius: 0 },
        ] },
        options: { parsing: false, scales: { x: { type: 'linear', title: { display: true, text: 't (s)' } }, y: { title: { display: true, text: 'A' } } },
                   plugins: { title: { display: true, text: 'Curva de corrente do motor', align: 'start' } } },
    });
    graficoLinha({ id: 'g-tempo', titulo: 'Tempo de manobra (s)', rotulos: d.tempo.ciclo, valores: d.tempo.s, cor: '#1f77b4' });
    graficoLinha({ id: 'g-iso', titulo: 'Isolamento (MΩ)', rotulos: d.isolamento.ciclo, valores: d.isolamento.mohm, cor: '#2e9e5b', limites: [@json($isolamento_min)] });
});
</script>
@endpush
@endif
