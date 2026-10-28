<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leitura extends Model
{
    use HasFactory;

    public const MARCA_MOCK = 'MOCK';

    protected $table = 'leituras';

    protected $fillable = [
        'maquina_id', 'medido_em', 'tempo_manobra_s', 'forca_n', 'isolamento_mohm',
        'corrente_pico', 'corrente_media', 'vibracao_mm_s', 'temperatura_motor_c', 'umidade_gabinete_pct',
        'ocorrencia', 'observacoes', 'responsavel', 'origem',
    ];

    /** Regras das grandezas de condição (opcionais em todas as entradas: formulário, API e CSV). */
    public const REGRAS_CONDICAO = [
        'vibracao_mm_s' => ['nullable', 'numeric', 'between:0,100'],
        'temperatura_motor_c' => ['nullable', 'numeric', 'between:-40,200'],
        'umidade_gabinete_pct' => ['nullable', 'numeric', 'between:0,100'],
    ];

    protected $casts = [
        'medido_em' => 'datetime',
        'tempo_manobra_s' => 'float',
        'forca_n' => 'float',
        'isolamento_mohm' => 'float',
        'corrente_pico' => 'float',
        'corrente_media' => 'float',
        'vibracao_mm_s' => 'float',
        'temperatura_motor_c' => 'float',
        'umidade_gabinete_pct' => 'float',
    ];

    public function maquina(): BelongsTo
    {
        return $this->belongsTo(Maquina::class);
    }
}
