<?php

/*
 | Parâmetros do Siemens S700K, plano de manutenção preventiva da Motiva e catálogo de componentes.
 | Fontes: manual Siemens 26330-OM-15 (seções 4.1, 8.2 e 11) e PMP-3AMV-011-10 (Linha 4, tabelas 8–10).
 | Componentes e ações foram inferidos dos casos reais de 2017 e da seção 11 do manual;
 | devem ser validados pela equipe de via permanente.
 */
return [
    // Duas versões do S700K no Metrô de São Paulo (manual 26330-OM-15, seção 4.1).
    'versoes' => [
        'agulha' => ['rotulo' => 'Agulha (com retrocesso)', 'tempo_nominal' => 6.0, 'forca_nominal' => 5500.0, 'forca_tol' => 500.0, 'retencao' => '7.000 N ± 500 N'],
        'jacare' => ['rotulo' => 'Jacaré móvel (sem retrocesso)', 'tempo_nominal' => 6.5, 'forca_nominal' => 6000.0, 'forca_tol' => 500.0, 'retencao' => '90 kN'],
    ],
    // O manual dá só o período nominal; a tolerância é premissa do projeto — validar com a equipe.
    'tempo_tolerancia_s' => 0.5,
    'isolamento_min' => 1.0,     // MΩ (manual 8.4.5)

    // Grandezas de condição pedidas no kick-off da Motiva (opcionais na leitura). Acima de "alerta" a máquina fica
    // amarela e a tendência estima quando chega a "critico"; em "critico" a intervenção é imediata.
    'condicao' => [
        // ISO 10816-1, máquinas pequenas (classe I): zona C a partir de 2,8 mm/s RMS, zona D a partir de 4,5 mm/s.
        'vibracao_mm_s' => ['rotulo' => 'Vibração do motor', 'unidade' => 'mm/s', 'alerta' => 2.8, 'critico' => 4.5, 'componente' => 'transmissao', 'fonte' => 'ISO 10816-1, classe I'],
        // Premissa: o catálogo S 700 K admite até +70 °C de ambiente; acima disso no motor indica atrito/sobrecarga.
        'temperatura_motor_c' => ['rotulo' => 'Temperatura do motor', 'unidade' => '°C', 'alerta' => 70.0, 'critico' => 85.0, 'componente' => 'motor', 'fonte' => 'premissa (catálogo: ambiente até +70 °C)'],
        // Premissa: gabinete IP54; umidade relativa alta indica vedação comprometida e risco ao isolamento.
        'umidade_gabinete_pct' => ['rotulo' => 'Umidade no gabinete', 'unidade' => '%', 'alerta' => 80.0, 'critico' => 95.0, 'componente' => 'gabinete', 'fonte' => 'premissa (gabinete IP54)'],
    ],

    // Plano de Manutenção Preventiva PMP-3AMV-011-10 (semanas por nível, por grupo de AMV).
    'pmp' => [
        'estrategico' => ['rotulo' => 'AMV estratégico', 'locais' => ['X03', 'X04', 'X12', 'X13'], 'intervalos' => ['N1' => 4, 'N2' => 8, 'NL' => 20, 'N3' => 44]],
        'nao_estrategico' => ['rotulo' => 'AMV não estratégico', 'locais' => ['X02', 'X05', 'X06', 'X07', 'X08', 'X09', 'X10', 'X11'], 'intervalos' => ['N1' => 8, 'N2' => 16, 'NL' => 24, 'N3' => 48]],
        'patio' => ['rotulo' => 'Pátio (jacaré fixo / PVS)', 'locais' => ['PVS'], 'intervalos' => ['N1' => 12, 'N2' => 24, 'N3' => 48]],
    ],
    // Ponto cego do monitoramento: o retorno do fuso sem perda de correspondência antes não altera corrente, tempo nem força
    // (casos reais 8, 9 e 16; na base simulada, 55 das 57 falhas sem aviso). Nas máquinas mais críticas, entra na preventiva.
    'inspecao_fuso' => [
        'grupos' => ['estrategico'],
        'nivel' => 'N2',
        'itens' => 'microswitch de fim de curso, pino e roletes do fuso',
    ],
    // Ocorrência registrada => nível do PMP que ela cumpre.
    'ocorrencia_nivel' => [
        'Inspeção N1' => 'N1', 'Inspeção N2' => 'N2', 'Inspeção N3' => 'N3', 'Manutenção NL (lavagem)' => 'NL',
    ],

    'linhas' => ['Linha 4 - Amarela', 'Linha 5 - Lilás', 'Linha 17 - Ouro', 'Linha 8 - Diamante', 'Linha 9 - Esmeralda', 'Metrô Bahia', 'VLT Carioca'],

    'dias_sem_leitura' => 14,    // alerta na Home quando a máquina fica tanto tempo sem leitura
    'min_leituras' => 3,         // mínimo de leituras em datas distintas para extrapolar tendência
    'horizontes' => [7, 15, 30, 60],
    'horizonte_padrao' => 15,

    // O modelo foi treinado com dados sintéticos; só deve decidir após retreino com dados reais.
    'usar_modelo_na_decisao' => false,

    'ml_url' => env('ML_SERVICE_URL', 'http://127.0.0.1:8001'),
    'ml_timeout' => (int) env('ML_SERVICE_TIMEOUT', 15),

    // Token da API de integração (sensores / sistema de monitoramento). Vazio = API desabilitada.
    'api_token' => env('CBM_API_TOKEN'),
    'api_lote_max' => 500,

    'docs_path' => env('CBM_DOCS_PATH', base_path('../extracted')),

    'ocorrencias' => [
        'Leitura de monitoramento', 'Inspeção N1', 'Inspeção N2', 'Inspeção N3', 'Manutenção NL (lavagem)',
        'Inspeção após alerta: defeito confirmado', 'Inspeção após alerta: sem defeito',
        'Reaperto de parafusos', 'Perda de correspondência', 'Travamento', 'Infiltração no gabinete', 'Manutenção corretiva', 'Outro',
    ],
    'ocorrencias_fuso' => ['Perda de correspondência', 'Travamento'],
    'ocorrencias_gabinete' => ['Infiltração no gabinete'],
    // Assertividade em campo: resultado da inspeção feita por causa de um alerta, e eventos que contam como falha.
    'ocorrencias_campo' => [
        'confirmado' => 'Inspeção após alerta: defeito confirmado',
        'sem_defeito' => 'Inspeção após alerta: sem defeito',
        'falhas' => ['Manutenção corretiva', 'Travamento', 'Perda de correspondência'],
    ],
    'origens' => ['manual' => 'Registro manual', 'api' => 'Integração (API)', 'importacao' => 'Importação CSV', 'intertravamento' => 'Intertravamento (evento)'],
    // Eventos que o log do intertravamento envia por POST /api/v1/eventos (código do evento => ocorrência registrada).
    // São o único precursor do retorno do fuso: chegando sozinhos, não dependem de alguém lembrar de registrar.
    'eventos_intertravamento' => ['perda_correspondencia' => 'Perda de correspondência', 'travamento' => 'Travamento'],

    'modos_falha' => [
        'motor_nao_aciona' => 'Motor não aciona',
        'sem_retorno' => 'Ausência de retorno à posição final',
        'embreagem_patinando' => 'Embreagem patinando',
        'talonada' => 'Máquina talonada',
        'nenhuma' => 'Sem anomalia dominante',
    ],

    'acoes' => [
        'isolamento' => 'Medir o isolamento (megômetro) e programar a substituição de cabos/motor antes de chegar a 1 MΩ.',
        'tempo' => 'Tempo de manobra fora da faixa: verificar atrito, lubrificação e regulagem das barras de acionamento.',
        'embreagem_patinando' => 'Inspecionar e ajustar/substituir a embreagem; checar força de acionamento.',
        'talonada' => 'Verificar folga e calçamento da agulha (talonamento); revisar lâminas e fixações.',
        'nenhuma' => 'Manter plano de manutenção preventiva padrão.',
    ],

    'componentes' => [
        'isolamento' => ['Cabos e enrolamentos do motor', 'Medir com megômetro e substituir cabos/motor antes de chegar a 1 MΩ.'],
        'barras' => ['Barras de detecção, bucha excêntrica e placas roletadas', 'Regular as barras, reapertar as fixações (até 200 Nm), lubrificar as placas roletadas e substituir pino/bucha excêntrica se houver folga.'],
        'embreagem' => ['Embreagem de tração', 'Inspecionar, apertar/ajustar ou substituir a embreagem; checar a força de acionamento.'],
        'agulha' => ['Agulha e lâminas (talonamento)', 'Verificar folga e calçamento da agulha; revisar lâminas e fixações.'],
        'motor' => ['Motor e alimentação trifásica', 'Inspecionar alimentação, contatores e enrolamentos; medir isolamento.'],
        'transmissao' => ['Rolamentos e engrenagens da transmissão', 'Inspecionar rolamentos e engrenagens, renovar a graxa e trocar rolamentos com folga (antecipa o item bianual do plano).'],
        'gabinete' => ['Vedação do gabinete, chicotes e aterramento', 'Secar o gabinete, refazer a vedação da tampa (IP54), inspecionar chicotes/aterramento e medir o isolamento.'],
        'fuso' => ['Fuso de movimentação, microswitch e roletes', 'Verificar retorno do fuso após travamento, status da microswitch e desgaste de pino/roletes; substituir a máquina se persistir.'],
    ],
];
