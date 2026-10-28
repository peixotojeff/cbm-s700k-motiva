<?php

namespace App\Http\Controllers;

use App\Enums\Prioridade;
use App\Http\Requests\StoreLeituraRequest;
use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Support\Carbon;
use App\Services\Cbm\Estado;
use App\Services\Cbm\RegistrarLeitura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistroController extends Controller
{
    public function create(Request $request, Estado $estado): View
    {
        $maquina = $request->query('maquina');

        return view('registro.create', [
            'maquinas' => Maquina::orderBy('codigo')->pluck('codigo'),
            'ocorrencias' => config('cbm.ocorrencias'),
            'versoes' => config('cbm.versoes'),
            'condicao' => config('cbm.condicao'),
            'veredito' => $maquina ? $this->veredito($estado, $maquina, $request->attributes->get('horizonte')) : null,
        ]);
    }

    public function store(StoreLeituraRequest $request, RegistrarLeitura $registrar): RedirectResponse
    {
        $dados = $request->paraLeitura();
        $codigo = RegistrarLeitura::normalizarCodigo($dados['maquina']);

        // envio repetido (toque duplo, reenvio do navegador): a mesma leitura não é gravada duas vezes
        $repetida = Leitura::whereHas('maquina', fn ($q) => $q->where('codigo', $codigo))
            ->where('medido_em', Carbon::parse($dados['medido_em']))
            ->where('tempo_manobra_s', $dados['tempo_manobra_s'])->where('forca_n', $dados['forca_n'])
            ->where('isolamento_mohm', $dados['isolamento_mohm'])->exists();
        if ($repetida) {
            return redirect()->route('registro.create', ['maquina' => $codigo])
                ->with('sucesso', "Esta leitura de $codigo já estava registrada; nada foi duplicado.");
        }

        $leitura = $registrar->executar($dados);

        return redirect()
            ->route('registro.create', ['maquina' => $leitura->maquina->codigo])
            ->with('sucesso', 'Leitura de '.$leitura->maquina->codigo.' registrada.');
    }

    /** Resposta direta ao funcionário: precisa intervir? em quais componentes e em quanto tempo? */
    private function veredito(Estado $estado, string $codigo, int $horizonte): ?array
    {
        $codigo = RegistrarLeitura::normalizarCodigo($codigo);
        ['situacao' => $situacao, 'plano' => $plano] = $estado->atual($horizonte);
        $s = $situacao->firstWhere('maquina', $codigo);
        if (! $s) {
            return null;
        }
        $doMaquina = $plano->where('maquina', $codigo);

        return [
            'maquina' => $codigo,
            'situacao' => $s,
            'itens' => $doMaquina->where('prioridade', '!=', Prioridade::Monitorar)->values(),
            'monitorar' => $doMaquina->where('prioridade', Prioridade::Monitorar)->values(),
            'urgente' => $doMaquina->contains(fn ($i) => $i['prioridade'] === Prioridade::Imediato),
            'min_leituras' => config('cbm.min_leituras'),
        ];
    }
}
