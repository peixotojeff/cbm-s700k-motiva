<?php

namespace Database\Seeders;

use App\Models\EventoFalha;
use Illuminate\Database\Seeder;

/** Importa os 16 eventos reais (2016–2017) extraídos das apresentações de monitoramento. */
class EventoFalhaSeeder extends Seeder
{
    public function run(): void
    {
        $arquivo = database_path('data/eventos_falhas_reais.csv');
        $h = fopen($arquivo, 'r');
        $cab = fgetcsv($h, 0, ',', '"', '');
        EventoFalha::query()->delete();
        while (($l = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $r = array_combine($cab, $l);
            EventoFalha::create([
                'maquina' => $r['maquina'], 'data' => $r['data'], 'fonte' => $r['fonte'], 'slide' => $r['slide'],
                'categoria' => $r['categoria'], 'anomalia_corrente' => $r['anomalia_corrente'], 'sintoma' => $r['sintoma'],
                'causa_acao' => $r['causa_acao'], 'observacao' => $r['observacao'] ?: null,
            ]);
        }
        fclose($h);
    }
}
