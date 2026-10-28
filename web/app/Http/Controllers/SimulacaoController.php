<?php

namespace App\Http\Controllers;

use App\Services\Cbm\AssertividadeCampo;
use App\Services\Cbm\MlClient;
use App\Services\Cbm\MlIndisponivel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Painel de simulação: demonstra o modelo preditivo (XGBoost + SHAP) sobre a base de históricos simulados. */
class SimulacaoController extends Controller
{
    private const SEVERIDADES = ['Vermelho', 'Amarelo', 'Verde'];

    public function index(Request $request, MlClient $ml, AssertividadeCampo $campo): View
    {
        view()->share('campo', $campo->calcular()); // medida real, não depende do serviço do modelo
        try {
            $meta = $ml->meta();
            $casos = $ml->casos();
            $ciclo = (int) $request->query('ciclo', $meta['ciclo_max']);
            $sim = $ml->simulacao($ciclo);
            $filtro = $request->query('sev', self::SEVERIDADES);
            $itens = collect($sim['itens'])->whereIn('severidade', $filtro)->values();
            $maquina = $request->query('maquina', $itens->first()['maquina_id'] ?? null);
            $diag = $maquina ? $ml->diagnostico($maquina, $ciclo) : null;
        } catch (MlIndisponivel $e) {
            return view('simulacao.index', ['erro' => $e->getMessage()]);
        }

        return view('simulacao.index', [
            'erro' => null, 'meta' => $meta, 'met' => $meta['metricas'], 'sem' => $meta['metricas']['sem_persistencia'] ?? null, 'casos' => $casos, 'sim' => $sim, 'ciclo' => $sim['ciclo'],
            'itens' => $itens, 'filtro' => $filtro, 'severidades' => self::SEVERIDADES, 'maquina' => $maquina, 'diag' => $diag,
            'isolamento_min' => config('cbm.isolamento_min'),
        ]);
    }

    /** Simulação ao vivo: os ciclos 5 a 119 animados, para apresentar o modelo em funcionamento. */
    public function animacao(MlClient $ml): View
    {
        try {
            return view('simulacao.animacao', ['erro' => null, 'dados' => $ml->animacao(), 'met' => $ml->meta()['metricas']]);
        } catch (MlIndisponivel $e) {
            return view('simulacao.animacao', ['erro' => $e->getMessage()]);
        }
    }
}
