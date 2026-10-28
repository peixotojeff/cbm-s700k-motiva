<?php

namespace App\Http\Requests;

use App\Models\Leitura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeituraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maquina' => ['required', 'string', 'max:50'],
            'tipo' => ['nullable', Rule::in(array_keys(config('cbm.versoes')))],
            'responsavel' => ['nullable', 'string', 'max:100'],
            'data' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'tempo_manobra_s' => ['required', 'numeric', 'between:0,60'],
            'forca_n' => ['required', 'numeric', 'between:0,20000'],
            'isolamento_mohm' => ['required', 'numeric', 'between:0,1000'],
            'corrente_pico' => ['nullable', 'numeric', 'between:0,50'],
            'corrente_media' => ['nullable', 'numeric', 'between:0,50'],
            ...Leitura::REGRAS_CONDICAO,
            'ocorrencia' => ['nullable', Rule::in(config('cbm.ocorrencias'))],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'maquina' => 'máquina de chave', 'tipo' => 'versão da máquina','tempo_manobra_s' => 'tempo de manobra', 'forca_n' => 'força de acionamento',
            'isolamento_mohm' => 'isolamento', 'corrente_pico' => 'corrente de pico', 'corrente_media' => 'corrente média',
            'vibracao_mm_s' => 'vibração do motor', 'temperatura_motor_c' => 'temperatura do motor', 'umidade_gabinete_pct' => 'umidade no gabinete',
            'ocorrencia' => 'tipo de ocorrência', 'observacoes' => 'observações', 'responsavel' => 'responsável',
        ];
    }

    /** Dados prontos para RegistrarLeitura::executar(). */
    public function paraLeitura(): array
    {
        $d = $this->validated();
        $d['medido_em'] = $d['data'].' '.$d['hora'];
        $d['origem'] = 'manual';
        unset($d['data'], $d['hora']);

        return $d;
    }
}
