@props(['titulo', 'valor', 'ajuda' => null, 'cor' => 'text-slate-900', 'status' => null, 'detalhe' => null, 'href' => null])
{{-- $status (App\Enums\Status) acrescenta o ícone do status antes do título; $detalhe é uma linha de comparação abaixo do valor;
     $href torna o cartão um link para a lista correspondente. --}}
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="card !p-4 {{ $href ? 'block transition hover:border-brand-200 hover:shadow-md' : '' }}" @if($ajuda) title="{{ $ajuda }}" @endif>
    <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
        @if ($status)<x-icone :nome="$status->icone()" class="h-4 w-4 {{ $status->corIcone() }}" />@endif
        {{ $titulo }}
    </div>
    <div class="mt-1 text-3xl font-bold tabular-nums {{ $cor }}">{{ $valor }}</div>
    @if ($detalhe)<div class="mt-0.5 text-sm tabular-nums text-slate-600">{{ $detalhe }}</div>@endif
    @if ($ajuda)<p class="sr-only">{{ $ajuda }}</p>@endif
</{{ $tag }}>
