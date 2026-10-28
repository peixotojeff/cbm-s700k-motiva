@php
    // Agrupado pela tarefa: o que a equipe de campo faz todo dia vem primeiro.
    $menu = [
        'Campo' => [
            ['home', 'Início', 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
            ['registro.create', 'Registrar leitura', 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z'],
            ['situacao.*', 'Situação das máquinas', 'M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5'],
        ],
        'Planejamento' => [
            ['plano.index', 'Plano de manutenção', 'M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085'],
            ['calendario.index', 'Calendário × condição', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
            ['estoque.index', 'Estoque de peças', 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
        ],
        'Modelo preditivo' => [
            ['simulacao.index', 'Painel de simulação', 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5'],
            ['simulacao.animacao', 'Simulação ao vivo', 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z'],
        ],
        'Conhecimento' => [
            ['casos.index', 'Casos reais de falha', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z'],
            ['documentos.index', 'Manuais e planos', 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25'],
        ],
        'Administração' => [
            ['admin.*', 'Importação e dados', 'M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5'],
        ],
    ];
    $avisosMenu ??= [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'CBM S700K') · CBM S700K</title>
    @if (request()->boolean('ao_vivo'))
        <meta http-equiv="refresh" content="10">{{-- acompanhar leituras chegando pela API --}}
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800 antialiased">
<a href="#conteudo" class="sr-only z-50 rounded-lg bg-white px-4 py-3 font-semibold text-brand-700 shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Pular para o conteúdo</a>
<div class="flex min-h-full">
    <aside class="menu hidden w-64 shrink-0 flex-col bg-brand-700 text-slate-100 lg:flex">
        <div class="px-5 py-5">
            <div class="text-lg font-bold text-white">CBM · S700K</div>
            <div class="text-xs text-brand-100">Manutenção baseada em condição · Challenge FIAP | Motiva</div>
        </div>
        <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4" aria-label="Menu principal">
            @foreach ($menu as $secao => $itens)
                <div>
                    <div class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-brand-100">{{ $secao }}</div>
                    @foreach ($itens as [$rota, $rotulo, $icone])
                        <a href="{{ route(str_replace('.*', '.index', $rota)) }}" @if (request()->routeIs($rota)) aria-current="page" @endif
                           class="flex min-h-11 items-center gap-3 rounded-lg px-2 py-2 text-sm {{ request()->routeIs($rota) ? 'bg-white/15 font-semibold text-white shadow-[inset_3px_0_0_var(--color-acento)]' : 'hover:bg-white/10' }}">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icone }}"/></svg>
                            <span class="flex-1">{{ $rotulo }}</span>
                            @isset($avisosMenu[$rota])
                                <span class="rounded-full bg-acento px-2 py-0.5 text-xs font-bold tabular-nums text-brand-900" title="{{ $avisosMenu[$rota][1] }}">
                                    <span aria-hidden="true">{{ $avisosMenu[$rota][0] }}</span><span class="sr-only">({{ $avisosMenu[$rota][1] }})</span></span>
                            @endisset
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- navegação compacta (mobile/tablet) --}}
        {{-- <details> abre e fecha sem JS e é operável por teclado; navegar exige escolher um link (não dispara ao rolar opções) --}}
        <details class="menu group relative bg-brand-700 text-white lg:hidden">
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 [&::-webkit-details-marker]:hidden">
                <span class="font-bold">CBM · S700K</span>
                <span class="inline-flex min-h-11 items-center gap-2 rounded-md bg-white/10 px-3 text-sm font-semibold">
                    @if ($avisosMenu)<span class="h-2.5 w-2.5 rounded-full bg-acento" aria-hidden="true"></span>@endif
                    <span class="group-open:hidden">Menu</span><span class="hidden group-open:inline">Fechar</span>
                    <svg class="h-5 w-5 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </span>
            </summary>
            <nav class="absolute inset-x-0 top-full z-40 max-h-[80dvh] space-y-4 overflow-y-auto bg-brand-700 px-3 pb-4 shadow-lg" aria-label="Menu principal">
                @foreach ($menu as $secao => $itens)
                    <div>
                        <div class="px-2 pb-1 text-xs font-semibold uppercase tracking-wider text-brand-100">{{ $secao }}</div>
                        <div class="grid gap-1 sm:grid-cols-2">
                            @foreach ($itens as [$rota, $rotulo])
                                <a href="{{ route(str_replace('.*', '.index', $rota)) }}" @if (request()->routeIs($rota)) aria-current="page" @endif
                                   class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 text-base {{ request()->routeIs($rota) ? 'bg-white/15 font-semibold' : 'hover:bg-white/10' }}">
                                    {{ $rotulo }}
                                    @isset($avisosMenu[$rota])
                                        <span class="rounded-full bg-acento px-2 py-0.5 text-xs font-bold tabular-nums text-brand-900"><span aria-hidden="true">{{ $avisosMenu[$rota][0] }}</span><span class="sr-only">({{ $avisosMenu[$rota][1] }})</span></span>
                                    @endisset
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </details>

        <main id="conteudo" tabindex="-1" class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 focus:outline-none sm:px-8">
            @unless (View::hasSection('semtitulo'))
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900">@yield('titulo')</h1>
                @hasSection('subtitulo')
                    <p class="mt-1 text-sm text-slate-600">@yield('subtitulo')</p>
                @endif
            </div>
            @endunless

            @if (session('sucesso'))
                <div class="mb-5 flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                    <x-icone nome="ok" class="h-5 w-5" />{{ session('sucesso') }}</div>
            @endif
            @if ($errors->any())
                {{-- Resumo focável com link para cada campo; o erro também aparece junto ao campo. --}}
                <div id="resumo-erros" tabindex="-1" class="mb-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" aria-labelledby="resumo-erros-titulo">
                    <p id="resumo-erros-titulo" class="flex items-center gap-2 font-semibold"><x-icone nome="alerta" class="h-5 w-5" />
                        {{ $errors->count() === 1 ? 'Corrija 1 campo para continuar:' : 'Corrija '.$errors->count().' campos para continuar:' }}</p>
                    <ul class="mt-1 list-disc pl-7">
                        @foreach ($errors->getMessages() as $campo => $msgs)
                            <li><a class="underline hover:no-underline" href="#{{ $campo }}">{{ $msgs[0] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('conteudo')
        </main>
        <footer class="px-8 py-4 text-sm text-slate-600">Prazos são estimativas por tendência linear e não substituem a inspeção em campo.</footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
