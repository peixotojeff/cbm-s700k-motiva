<?php

namespace App\Services\Cbm;

use App\Models\Leitura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Importa leituras em lote de um CSV (vírgula ou ponto e vírgula; decimais com vírgula aceitos).
 * Colunas: maquina, medido_em, tempo_manobra_s
 * Opcionais: forca_n, isolamento_mohm, corrente_pico, corrente_media, vibracao_mm_s, temperatura_motor_c,
 *            umidade_gabinete_pct, ocorrencia, observacoes, responsavel, tipo (agulha|jacare)
 */
class ImportarLeituras
{
    public const OBRIGATORIAS = ['maquina', 'medido_em', 'tempo_manobra_s'];

    public const OPCIONAIS = [
        'forca_n', 'isolamento_mohm', 'corrente_pico', 'corrente_media', 'vibracao_mm_s', 'temperatura_motor_c',
        'umidade_gabinete_pct', 'ocorrencia', 'observacoes', 'responsavel', 'tipo',
    ];

    private const NUMERICAS = [
        'tempo_manobra_s', 'forca_n', 'isolamento_mohm', 'corrente_pico', 'corrente_media',
        'vibracao_mm_s', 'temperatura_motor_c', 'umidade_gabinete_pct',
    ];

    public function __construct(private RegistrarLeitura $registrar) {}

    /** @return array{importadas:int, erros:list<array{linha:int,mensagens:list<string>}>, colunas_ausentes:list<string>} */
    public function executar(string $caminho): array
    {
        $h = fopen($caminho, 'r');
        $primeira = fgets($h);
        rewind($h);
        $sep = substr_count($primeira, ';') > substr_count($primeira, ',') ? ';' : ',';
        $cab = array_map(fn ($c) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $c))), fgetcsv($h, 0, $sep, '"', '') ?: []);

        $ausentes = array_values(array_diff(self::OBRIGATORIAS, $cab));
        if ($ausentes) {
            fclose($h);

            return ['importadas' => 0, 'erros' => [], 'colunas_ausentes' => $ausentes];
        }

        $importadas = 0;
        $erros = [];
        $linha = 1;
        while (($cols = fgetcsv($h, 0, $sep, '"', '')) !== false) {
            $linha++;
            if ($cols === [null]) {
                continue;
            }
            $r = array_combine($cab, array_pad(array_slice($cols, 0, count($cab)), count($cab), null));
            foreach (self::NUMERICAS as $n) {
                if (isset($r[$n]) && $r[$n] !== '') {
                    $r[$n] = str_replace(',', '.', $r[$n]);
                } else {
                    $r[$n] = null;
                }
            }
            $v = Validator::make($r, [
                'maquina' => 'required|string|max:50',
                'medido_em' => 'required|date',
                'tempo_manobra_s' => 'required|numeric|between:0,60',
                'forca_n' => 'nullable|numeric|between:0,20000',
                'isolamento_mohm' => 'nullable|numeric|between:0,1000',
                'corrente_pico' => 'nullable|numeric|between:0,50',
                'corrente_media' => 'nullable|numeric|between:0,50',
                ...Leitura::REGRAS_CONDICAO,
                'tipo' => 'nullable|in:'.implode(',', array_keys(config('cbm.versoes'))),
            ]);
            if ($v->fails()) {
                $erros[] = ['linha' => $linha, 'mensagens' => $v->errors()->all()];

                continue;
            }
            $this->registrar->executar([
                'maquina' => $r['maquina'], 'medido_em' => Carbon::parse($r['medido_em']),
                'tempo_manobra_s' => $r['tempo_manobra_s'], 'forca_n' => $r['forca_n'], 'isolamento_mohm' => $r['isolamento_mohm'],
                'corrente_pico' => $r['corrente_pico'], 'corrente_media' => $r['corrente_media'],
                'vibracao_mm_s' => $r['vibracao_mm_s'], 'temperatura_motor_c' => $r['temperatura_motor_c'],
                'umidade_gabinete_pct' => $r['umidade_gabinete_pct'],
                'ocorrencia' => $r['ocorrencia'] ?? null, 'observacoes' => $r['observacoes'] ?? null,
                'responsavel' => $r['responsavel'] ?? 'IMPORTAÇÃO',
                'tipo' => ($r['tipo'] ?? null) ?: null,
                'origem' => 'importacao',
            ]);
            $importadas++;
        }
        fclose($h);

        return ['importadas' => $importadas, 'erros' => $erros, 'colunas_ausentes' => []];
    }
}
