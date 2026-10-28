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
 * Recebe leituras de sensores / sistema de monitoramento. Aceita um objeto ou {"leituras": [...]}.
 * - O lote é tudo-ou-nada: se uma leitura for inválida, nenhuma é gravada.
 * - Força, isolamento, vibração, temperatura e umidade são opcionais (um sensor de corrente mede só tempo e corrente).
 * - Reenvio é seguro: a mesma máquina no mesmo instante (medido_em) é ignorada, não duplicada.
 */
class LeituraController extends Controller
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function store(Request $request, RegistrarLeitura $registrar, Estado $estado): JsonResponse
    {
        $itens = $request->has('leituras') ? $request->input('leituras') : [$request->all()];
        $max = config('cbm.api_lote_max');
        if (! is_array($itens) || $itens === [] || count($itens) > $max) {
            return response()->json(['erro' => "Envie de 1 a {$max} leituras."], 422);
        }

        $regras = [
            'maquina' => ['required', 'string', 'max:50'],
            'tipo' => ['nullable', Rule::in(array_keys(config('cbm.versoes')))],
            'medido_em' => ['required', 'date'],
            'tempo_manobra_s' => ['required', 'numeric', 'between:0,60'],
            'forca_n' => ['nullable', 'numeric', 'between:0,20000'],
            'isolamento_mohm' => ['nullable', 'numeric', 'between:0,1000'],
            'corrente_pico' => ['nullable', 'numeric', 'between:0,50'],
            'corrente_media' => ['nullable', 'numeric', 'between:0,50'],
            ...Leitura::REGRAS_CONDICAO,
            'ocorrencia' => ['nullable', Rule::in(config('cbm.ocorrencias'))],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'responsavel' => ['nullable', 'string', 'max:100'],
        ];
        $erros = [];
        foreach ($itens as $i => $item) {
            $v = Validator::make(is_array($item) ? $item : [], $regras);
            if ($v->fails()) {
                $erros[$i] = $v->errors()->toArray();
            }
        }
        if ($erros) {
            return response()->json(['erro' => 'Leituras inválidas; nada foi gravado.', 'detalhes' => $erros], 422);
        }

        $leituras = collect($itens)->map(function (array $item) use ($regras) {
            $d = array_intersect_key($item, $regras);
            $d['maquina'] = RegistrarLeitura::normalizarCodigo($d['maquina']);
            $d['medido_em'] = Carbon::parse($d['medido_em']);
            $d['ocorrencia'] = ($d['ocorrencia'] ?? null) ?: 'Leitura de monitoramento';
            $d['responsavel'] = ($d['responsavel'] ?? null) ?: 'API';
            $d['origem'] = 'api';

            return $d;
        });
        $chave = fn (array $d) => $d['maquina'].'|'.$d['medido_em']->format(self::FORMATO);

        // Já gravadas antes (reenvio) ou repetidas dentro do próprio lote.
        $ids = Maquina::whereIn('codigo', $leituras->pluck('maquina')->unique())->pluck('codigo', 'id');
        $existentes = Leitura::whereIn('maquina_id', $ids->keys())
            ->whereIn('medido_em', $leituras->map(fn ($d) => $d['medido_em']->format(self::FORMATO))->unique()->values())
            ->get()->map(fn (Leitura $l) => $ids[$l->maquina_id].'|'.$l->medido_em->format(self::FORMATO))->flip();
        $novas = $leituras->reject(fn ($d) => $existentes->has($chave($d)))->unique($chave)->values();

        DB::transaction(fn () => $novas->each(fn ($d) => $registrar->executar($d)));

        $codigos = $leituras->pluck('maquina')->unique()->values();
        $h = MaquinaController::horizonte($request);
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($h);
        $maquinas = Maquina::whereIn('codigo', $codigos)->get()->keyBy('codigo');

        return response()->json([
            'registradas' => $novas->count(),
            'duplicadas_ignoradas' => $leituras->count() - $novas->count(),
            'horizonte_dias' => $h,
            'maquinas' => $codigos->map(fn ($c) => MaquinaController::avaliacao($maquinas[$c], $situacao->firstWhere('maquina', $c), $plano))->all(),
        ], $novas->isNotEmpty() ? 201 : 200);
    }
}
