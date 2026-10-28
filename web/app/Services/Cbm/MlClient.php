<?php

namespace App\Services\Cbm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Cliente HTTP do serviço do modelo preditivo (ml/main.py). */
class MlClient
{
    public function meta(): array
    {
        return $this->get('/meta');
    }

    public function simulacao(?int $ciclo = null, int $limite = 300): array
    {
        return $this->get('/simulacao', array_filter(['ciclo' => $ciclo, 'limite' => $limite], fn ($v) => $v !== null));
    }

    /** Linha do tempo de todos os históricos (um estado por ciclo) para a simulação ao vivo. */
    public function animacao(): array
    {
        return $this->get('/animacao');
    }

    /** Resumo da base simulada (casos por modo, relação com os casos reais, desempenho por modo). */
    public function casos(): array
    {
        return $this->get('/casos');
    }

    public function diagnostico(string $maquina, ?int $ciclo = null): array
    {
        return $this->get('/simulacao/'.rawurlencode($maquina), array_filter(['ciclo' => $ciclo], fn ($v) => $v !== null));
    }

    private function get(string $path, array $query = []): array
    {
        try {
            return Http::baseUrl(config('cbm.ml_url'))
                ->timeout(config('cbm.ml_timeout'))
                ->acceptJson()
                ->get($path, $query)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $e) {
            throw new MlIndisponivel('Serviço do modelo preditivo indisponível ('.config('cbm.ml_url').').', previous: $e);
        }
    }
}
