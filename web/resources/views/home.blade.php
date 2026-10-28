@extends('layouts.app')
@section('titulo', 'Início')
@section('semtitulo', '1')

@section('conteudo')
<section class="mb-6 rounded-2xl bg-gradient-to-r from-brand-700 to-brand-600 p-6 text-white shadow-sm sm:p-8">
    <p class="text-sm text-brand-100">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">{{ $saudacao }}! Bem-vindo ao CBM S700K</h1>
    <p class="mt-2 max-w-2xl text-sm text-brand-100">
        Registre as medições de campo e acompanhe quais máquinas de chave precisam de intervenção, em quais componentes e em quantos dias.
    </p>
    <div class="mt-5 flex flex-wrap gap-3">
        <a href="{{ route('registro.create') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand-900 hover:bg-brand-50">Registrar leitura</a>
        <a href="{{ route('plano.index') }}" class="inline-flex items-center rounded-lg border border-white/40 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">Ver plano de manutenção</a>
    </div>
</section>

@if ($situacao->isEmpty())
    <div class="card text-sm text-slate-600">
        Nenhuma leitura registrada ainda. Comece em <a class="text-brand-600 underline" href="{{ route('registro.create') }}">Registrar leitura</a>
        ou carregue dados de exemplo em <a class="text-brand-600 underline" href="{{ route('admin.index') }}">Importação e dados</a>.
    </div>
@else
    {{-- Um número por pergunta da equipe; cada cartão leva à lista que o explica. --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-kpi titulo="Vermelho" :status="\App\Enums\Status::Vermelho" :valor="$contagem['Vermelho']" cor="text-red-700"
               :detalhe="$imediatos->count().' '.($imediatos->count() === 1 ? 'intervenção imediata' : 'intervenções imediatas')" :href="route('situacao.index', ['status' => 'Vermelho'])" />
        <x-kpi titulo="Amarelo" :status="\App\Enums\Status::Amarelo" :valor="$contagem['Amarelo']" cor="text-amber-700"
               :detalhe="$programar->count().' a programar em '.$horizonte.' dias'" :href="route('situacao.index', ['status' => 'Amarelo'])" />
        <x-kpi titulo="Verde" :status="\App\Enums\Status::Verde" :valor="$contagem['Verde']" cor="text-emerald-700"
               :detalhe="'de '.$situacao->count().' máquinas · '.$contagem['Aguardando'].' aguardando leituras'" :href="route('situacao.index', ['status' => 'Verde'])" />
        <x-kpi titulo="Peças a pedir" :status="$rupturas->isNotEmpty() ? \App\Enums\Status::Vermelho : null" :valor="$pedidos->count()"
               :cor="$rupturas->isNotEmpty() ? 'text-red-700' : 'text-slate-900'" :detalhe="$rupturas->count().' em ruptura antes da reposição'" :href="route('estoque.index')" />
    </div>

    <div class="mb-6 grid gap-4 lg:grid-cols-3">
        <section class="card lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-semibold text-slate-900">Alertas</h2>
                <a class="text-sm text-brand-600 hover:underline" href="{{ route('plano.index') }}">Plano completo →</a>
            </div>
            @if ($imediatos->isEmpty() && $programar->isEmpty() && $desatualizadas->isEmpty() && $rupturas->isEmpty())
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Nenhum alerta: nenhuma intervenção prevista nos próximos {{ $horizonte }} dias.</div>
            @else
                <ul class="space-y-2">
                    @foreach ($imediatos->take(5) as $a)
                        <li class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
                            <x-icone nome="alerta" class="mt-0.5 h-5 w-5 text-red-700" />
                            <div>
                                <div class="font-semibold text-red-900"><a class="hover:underline" href="{{ route('situacao.show', $a['maquina']) }}">{{ $a['maquina'] }}</a> — {{ $a['componente'] }} <span class="font-normal">(agir agora)</span></div>
                                <div class="text-red-800">{{ $a['motivo'] }}.</div>
                            </div>
                        </li>
                    @endforeach
                    @foreach ($programar->take(5) as $a)
                        <li class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                            <x-icone nome="atencao" class="mt-0.5 h-5 w-5 text-amber-700" />
                            <div>
                                <div class="font-semibold text-amber-900"><a class="hover:underline" href="{{ route('situacao.show', $a['maquina']) }}">{{ $a['maquina'] }}</a> — {{ $a['componente'] }}
                                    <span class="font-normal">(<x-prazo :dias="$a['prazo_dias']" />)</span></div>
                                <div class="text-amber-800">{{ $a['motivo'] }}.</div>
                            </div>
                        </li>
                    @endforeach
                    @foreach ($rupturas as $r)
                        <li class="flex gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
                            <x-icone nome="alerta" class="mt-0.5 h-5 w-5 text-red-700" />
                            <div>
                                <div class="font-semibold text-red-900"><a class="hover:underline" href="{{ route('estoque.index') }}">{{ $r['peca']->nome }}</a> <span class="font-normal">(estoque)</span></div>
                                <div class="text-red-800">Falta em {{ $r['falta_em_dias'] }} dias ({{ $r['falta_maquina'] }}); a reposição leva {{ $r['peca']->prazo_reposicao_dias }} dias.</div>
                            </div>
                        </li>
                    @endforeach
                    @foreach ($desatualizadas->take(3) as $d)
                        <li class="flex gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
                            <x-icone nome="aguardando" class="mt-0.5 h-5 w-5 text-slate-600" />
                            <div class="text-slate-700"><a class="font-semibold hover:underline" href="{{ route('situacao.show', $d['maquina']) }}">{{ $d['maquina'] }}</a>
                                sem leitura há {{ (int) floor($d['ultima_leitura']->diffInDays(now())) }} dias (limite de {{ $limiteDias }}).</div>
                        </li>
                    @endforeach
                </ul>
                @if ($imediatos->count() > 5 || $programar->count() > 5)
                    <p class="mt-3 text-sm text-slate-600">Mostrando os mais urgentes; o restante está no plano de manutenção.</p>
                @endif
            @endif
        </section>

        <section class="card">
            <h2 class="mb-3 font-semibold text-slate-900">Distribuição por status</h2>
            <div class="h-56"><canvas id="g-status" aria-label="Máquinas por status" role="img"></canvas></div>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="card overflow-x-auto !p-0">
            <div class="flex items-center justify-between px-5 pt-4">
                <h2 class="font-semibold text-slate-900">Máquinas que pedem atenção</h2>
                <a class="text-sm text-brand-600 hover:underline" href="{{ route('situacao.index') }}">Todas →</a>
            </div>
            @if ($atencao->isEmpty())
                <p class="px-5 py-4 text-sm text-slate-600">Nenhuma máquina em vermelho ou amarelo.</p>
            @else
                <table class="mt-3 min-w-full divide-y divide-slate-200">
                    <thead><tr><th class="th">Máquina</th><th class="th">Status</th><th class="th">Ação recomendada</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($atencao->take(6) as $s)
                        <tr>
                            <td class="td font-medium"><a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $s['maquina']) }}">{{ $s['maquina'] }}</a></td>
                            <td class="td"><x-selo :status="$s['status']" /></td>
                            <td class="td">{{ $s['acao'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="card overflow-x-auto !p-0">
            <div class="flex items-baseline justify-between px-5 pt-4">
                <h2 class="font-semibold text-slate-900">Últimas leituras</h2>
                <span class="text-sm tabular-nums text-slate-600">{{ $leiturasSemana }} nos últimos 7 dias</span>
            </div>
            <table class="mt-3 min-w-full divide-y divide-slate-200">
                <thead><tr><th class="th">Quando</th><th class="th">Máquina</th><th class="th">Isolamento</th><th class="th">Tempo</th><th class="th">Resp.</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($recentes as $l)
                    <tr>
                        <td class="td whitespace-nowrap">{{ $l->medido_em->format('d/m H:i') }}</td>
                        <td class="td"><a class="text-brand-600 hover:underline" href="{{ route('situacao.show', $l->maquina->codigo) }}">{{ $l->maquina->codigo }}</a></td>
                        <td class="td">{{ $l->isolamento_mohm !== null ? number_format($l->isolamento_mohm, 2, ',', '').' MΩ' : '—' }}</td>
                        <td class="td">{{ $l->tempo_manobra_s !== null ? number_format($l->tempo_manobra_s, 2, ',', '').' s' : '—' }}</td>
                        <td class="td">{{ $l->responsavel ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    </div>
@endif
@endsection

@if ($situacao->isNotEmpty())
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const c = @json($contagem);
    const cores = { Vermelho: '#d64545', Amarelo: '#e0a800', Verde: '#2e9e5b', Aguardando: '#94a3b8' };
    const rotulos = Object.keys(c).filter((k) => c[k] > 0);
    descreverGrafico(document.getElementById('g-status'), 'Máquinas por status: ' + rotulos.map((k) => `${k} ${c[k]}`).join(', ') + '.');
    new Chart(document.getElementById('g-status'), {
        type: 'doughnut',
        data: { labels: rotulos, datasets: [{ data: rotulos.map((k) => c[k]), backgroundColor: rotulos.map((k) => cores[k]), borderWidth: 2 }] },
        options: { cutout: '62%', plugins: { legend: { position: 'bottom' } } },
    });
});
</script>
@endpush
@endif
