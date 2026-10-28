@props(['valores' => [], 'limiar' => null, 'largura' => 96, 'altura' => 24])
{{-- Mini-linha do risco (0–1) nas últimas leituras, com o limiar vermelho tracejado. --}}
@php
    $n = count($valores);
    $x = fn ($i) => $n > 1 ? round($i * ($largura - 4) / ($n - 1) + 2, 1) : $largura / 2;
    $y = fn ($v) => round(($altura - 2) - $v * ($altura - 4), 1);
    $pontos = collect($valores)->map(fn ($v, $i) => $x($i).','.$y($v))->implode(' ');
    $ultimo = $n ? end($valores) : 0;
    $cor = $limiar !== null && $ultimo >= $limiar ? '#d64545' : ($limiar !== null && $ultimo >= 0.25 * $limiar ? '#e0a800' : '#2e9e5b');
@endphp
@if ($n)
    <svg width="{{ $largura }}" height="{{ $altura }}" viewBox="0 0 {{ $largura }} {{ $altura }}" role="img"
         aria-label="Risco nas últimas {{ $n }} leituras: de {{ round($valores[0] * 100) }}% a {{ round($ultimo * 100) }}%">
        @if ($limiar !== null)
            <line x1="0" x2="{{ $largura }}" y1="{{ $y($limiar) }}" y2="{{ $y($limiar) }}" stroke="#d64545" stroke-width="0.75" stroke-dasharray="2 2" opacity="0.6" />
        @endif
        <polyline points="{{ $pontos }}" fill="none" stroke="{{ $cor }}" stroke-width="1.5" stroke-linejoin="round" />
        <circle cx="{{ $x($n - 1) }}" cy="{{ $y($ultimo) }}" r="2" fill="{{ $cor }}" />
    </svg>
@endif
