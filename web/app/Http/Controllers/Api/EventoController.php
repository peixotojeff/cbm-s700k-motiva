<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Leitura;
use App\Models\Maquina;
use App\Services\Cbm\Estado;
use App\Services\Cbm\RegistrarLeitura;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Recebe eventos do log do intertravamento (perda de correspondência, travamento). Aceita um objeto ou {"eventos": [...]}.
 * - O evento entra como ocorrência da máquina, sem medição; a regra do fuso o usa na hora (amarelo/vermelho).
 * - Lote tudo-ou-nada; reenvio seguro: o mesmo evento na mesma máquina e no mesmo instante é ignorado.
 */
class EventoController extends Controller
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function store(Request $request, RegistrarLeitura $registrar, Estado $estado): JsonResponse
    {
        $itens = $request->has('eventos') ? $request->input('eventos') : [$request->all()];
        $max = config('cbm.api_lote_max');
        if (! is_array($itens) || $itens === [] || count($itens) > $max) {
            return response()->json(['erro' => "Envie de 1 a {$max} eventos."], 422);
        }
        $tipos = config('cbm.eventos_intertravamento');
        $regras = [
            'maquina' => ['required', 'string', 'max:50'],
            'ocorrido_em' => ['required', 'date'],
            'evento' => ['required', Rule::in(array_keys($tipos))],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
        $erros = [];
        foreach ($itens as $i => $item) {
            $v = Validator::make(is_array($item) ? $item : [], $regras);
            if ($v->fails()) {
                $erros[$i] = $v->errors()->toArray();
            }
        }
        if ($erros) {
            return response()->json(['erro' => 'Eventos inválidos; nada foi gravado.', 'detalhes' => $erros], 422);
        }

        $eventos = collect($itens)->map(fn (array $e) => [
            'maquina' => RegistrarLeitura::normalizarCodigo($e['maquina']),
            'medido_em' => Carbon::parse($e['ocorrido_em']),
            'ocorrencia' => $tipos[$e['evento']],
            'observacoes' => ($e['observacoes'] ?? null) ?: 'Evento recebido do log do intertravamento',
            'responsavel' => 'INTERTRAVAMENTO', 'origem' => 'intertravamento',
        ]);
        $chave = fn (array $d) => $d['maquina'].'|'.$d['medido_em']->format(self::FORMATO).'|'.$d['ocorrencia'];

        $ids = Maquina::whereIn('codigo', $eventos->pluck('maquina')->unique())->pluck('codigo', 'id');
        $existentes = Leitura::whereIn('maquina_id', $ids->keys())
            ->whereIn('medido_em', $eventos->map(fn ($d) => $d['medido_em']->format(self::FORMATO))->unique()->values())
            ->get()->map(fn (Leitura $l) => $ids[$l->maquina_id].'|'.$l->medido_em->format(self::FORMATO).'|'.$l->ocorrencia)->flip();
        $novos = $eventos->reject(fn ($d) => $existentes->has($chave($d)))->unique($chave)->values();

        DB::transaction(fn () => $novos->each(fn ($d) => $registrar->executar($d)));

        $codigos = $eventos->pluck('maquina')->unique()->values();
        $h = MaquinaController::horizonte($request);
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($h);
        $maquinas = Maquina::whereIn('codigo', $codigos)->get()->keyBy('codigo');

        return response()->json([
            'registradas' => $novos->count(),
            'duplicadas_ignoradas' => $eventos->count() - $novos->count(),
            'horizonte_dias' => $h,
            'maquinas' => $codigos->map(fn ($c) => MaquinaController::avaliacao($maquinas[$c], $situacao->firstWhere('maquina', $c), $plano))->all(),
        ], $novos->isNotEmpty() ? 201 : 200);
    }
}
