@php
    $s = $v['situacao'];
    $tem = $v['itens']->isNotEmpty();
@endphp
@if ($tem)
    <div class="rounded-xl border p-5 {{ $v['urgente'] ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50' }}" role="alert">
        <p class="flex items-start gap-2 text-base font-semibold {{ $v['urgente'] ? 'text-red-900' : 'text-amber-900' }}">
            <x-icone :nome="$s['status']->icone()" class="mt-0.5 h-5 w-5" />
            <span>{{ $v['maquina'] }}: PRECISA DE INTERVENÇÃO — {{ $v['urgente'] ? 'agir agora' : "programar nos próximos {$horizonte} dias" }}</span>
        </p>
        <ul class="mt-3 space-y-3 text-base text-slate-800">
            @foreach ($v['itens'] as $i)
                <li>
                    <strong>{{ $i['componente'] }}</strong>
                    (<x-prazo :dias="$i['prazo_dias']" />) — {{ $i['motivo'] }}.<br>
                    <em class="text-slate-700">Ação:</em> {{ $i['acao'] }}
                </li>
            @endforeach
        </ul>
        <p class="mt-4 border-t border-black/10 pt-3 text-sm text-slate-700">
            Depois de inspecionar, registre o resultado com o tipo de ocorrência <strong>“Inspeção após alerta: defeito confirmado”</strong>
            ou <strong>“… sem defeito”</strong>. É assim que o sistema mede a própria assertividade em campo.
        </p>
    </div>
@elseif ($s['status'] === \App\Enums\Status::Aguardando)
    <div class="flex items-start gap-2 rounded-xl border border-slate-300 bg-slate-100 p-5 text-base text-slate-800" role="status">
        <x-icone :nome="$s['status']->icone()" class="mt-0.5 h-5 w-5" />
        <span><strong>{{ $v['maquina'] }}:</strong> sem alertas. {{ $s['leituras'] }} de {{ $v['min_leituras'] }} leituras para a previsão.</span>
    </div>
@else
    <div class="flex items-start gap-2 rounded-xl border border-emerald-300 bg-emerald-50 p-5 text-base text-emerald-900" role="status">
        <x-icone :nome="$s['status']->icone()" class="mt-0.5 h-5 w-5" />
        <span><strong>{{ $v['maquina'] }}: não precisa de intervenção</strong> nos próximos {{ $horizonte }} dias.</span>
    </div>
@endif
@if ($v['monitorar']->isNotEmpty())
    <p class="mt-3 text-sm text-slate-600">Em monitoramento (fora do horizonte):
        {{ $v['monitorar']->map(fn ($m) => $m['componente'].' (~'.round($m['prazo_dias']).' d)')->implode('; ') }}</p>
@endif
<p class="mt-3"><a class="inline-flex min-h-11 items-center text-base font-medium text-brand-700 underline" href="{{ route('situacao.show', $v['maquina']) }}">Ver histórico e gráficos de {{ $v['maquina'] }}</a></p>
