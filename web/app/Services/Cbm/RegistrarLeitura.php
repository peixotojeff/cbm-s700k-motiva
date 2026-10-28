<?php

namespace App\Services\Cbm;

use App\Models\Leitura;
use App\Models\Maquina;
use Illuminate\Support\Str;

class RegistrarLeitura
{
    /** Padroniza o código: espaços colapsados e caixa alta ("mch  4123 jm" -> "MCH 4123 JM"). */
    public static function normalizarCodigo(string $codigo): string
    {
        return Str::upper(preg_replace('/\s+/', ' ', trim($codigo)));
    }

    /**
     * Cria a máquina na primeira leitura (versão inferida pelo sufixo AG/JM, salvo se 'tipo' vier informado).
     *
     * @param  array<string, mixed>  $dados  inclui 'maquina' (código), opcionalmente 'tipo', e os campos de Leitura
     */
    public function executar(array $dados): Leitura
    {
        $codigo = self::normalizarCodigo($dados['maquina']);
        $tipo = $dados['tipo'] ?? null;
        unset($dados['maquina'], $dados['tipo']);

        $maquina = Maquina::firstOrCreate(['codigo' => $codigo], ['tipo' => $tipo ?: Maquina::inferirTipo($codigo)]);
        if ($tipo && $maquina->tipo !== $tipo) {
            $maquina->update(['tipo' => $tipo]);
        }

        return $maquina->leituras()->create($dados);
    }
}
