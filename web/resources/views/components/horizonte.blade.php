{{-- Seletor do horizonte de previsão (dias); o valor fica na sessão (middleware HorizonteDias). --}}
<div {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-2']) }}>
    <span class="text-sm font-medium text-slate-700" id="horizonte-rotulo">Horizonte de previsão</span>
    <div class="inline-flex overflow-hidden rounded-lg border border-slate-300 bg-white" role="group" aria-labelledby="horizonte-rotulo">
        @foreach ($horizontes as $h)
            <a href="{{ request()->fullUrlWithQuery(['h' => $h]) }}" @if ($h === $horizonte) aria-current="true" @endif
               class="inline-flex min-h-11 items-center border-l border-slate-300 px-3 text-sm first:border-l-0 {{ $h === $horizonte ? 'bg-brand-600 font-semibold text-white' : 'text-slate-700 hover:bg-slate-50' }}">{{ $h }} dias</a>
        @endforeach
    </div>
</div>
