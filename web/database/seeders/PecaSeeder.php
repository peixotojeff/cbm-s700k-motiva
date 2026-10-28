<?php

namespace Database\Seeders;

use App\Models\Peca;
use Illuminate\Database\Seeder;

/**
 * Peças do S700K por componente do plano. Nomes vêm do manual Siemens 26330-OM-15 (seções 8.3 e 8.4) e do catálogo
 * "S 700 K point machine"; ESTOQUE, MÍNIMO E PRAZO SÃO FICTÍCIOS — substituir pela lista real do almoxarifado.
 */
class PecaSeeder extends Seeder
{
    public function run(): void
    {
        $pecas = [
            // [código, nome, componente, qtd por intervenção, estoque, mínimo, prazo (dias), fonte]
            ['S700K-ISO-01', 'Isolador da haste de detecção', 'isolamento', 1, 3, 2, 10, 'Manual 8.4.5: trocar se < 1 MΩ'],
            ['S700K-CAB-01', 'Chicote de fios do motor', 'isolamento', 1, 1, 1, 15, 'Manual 8.4.3'],
            ['S700K-BAR-01', 'Bucha excêntrica', 'barras', 1, 2, 2, 7, 'Casos reais 7 e 9'],
            ['S700K-BAR-02', 'Pino da barra de detecção', 'barras', 2, 4, 4, 5, 'Caso real 12'],
            ['S700K-EMB-01', 'Embreagem de transmissão', 'embreagem', 1, 1, 1, 30, 'Manual 8.3 (item 20)'],
            ['S700K-AGU-01', 'Kit de fixação e calços da agulha', 'agulha', 1, 4, 2, 3, 'Via permanente'],
            ['S700K-MOT-01', 'Motor trifásico 400 V', 'motor', 1, 0, 1, 45, 'Catálogo: motor 400 V AC 3~'],
            ['S700K-MOT-02', 'Contator do circuito de comando', 'motor', 1, 3, 2, 5, 'Manual 11.2'],
            ['S700K-FUS-01', 'Conjunto de contatos / microswitch de fim de curso', 'fuso', 1, 1, 2, 20, 'Manual 11.3; casos reais 8 e 9'],
            ['S700K-FUS-02', 'Pino e roletes do fuso', 'fuso', 1, 2, 2, 10, 'Caso real 9'],
            ['S700K-TRA-01', 'Kit de rolamentos da transmissão + graxa', 'transmissao', 1, 1, 1, 21, 'Kick-off: inspeção bianual de rolamentos e engrenagens'],
            ['S700K-GAB-01', 'Kit de vedação da tampa do gabinete', 'gabinete', 1, 2, 1, 7, 'Catálogo: gabinete IP54'],
        ];
        foreach ($pecas as [$codigo, $nome, $componente, $qtd, $estoque, $minimo, $prazo, $fonte]) {
            Peca::updateOrCreate(['codigo' => $codigo], [
                'nome' => $nome, 'componente' => $componente, 'qtd_por_intervencao' => $qtd, 'estoque' => $estoque,
                'estoque_minimo' => $minimo, 'prazo_reposicao_dias' => $prazo, 'fonte' => $fonte, 'ficticio' => true,
            ]);
        }
    }
}
