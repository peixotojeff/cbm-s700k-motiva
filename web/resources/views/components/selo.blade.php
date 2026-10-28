@props(['status'])
<span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $status->selo() }}">
    @if ($status instanceof \App\Enums\Status)<x-icone :nome="$status->icone()" class="h-3.5 w-3.5" />@endif{{ $status->value }}
</span>
