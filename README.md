# Challenge FIAP | Motiva — CBM para máquinas de chave Siemens S700K

Sistema de manutenção baseada em condição: a equipe registra medições de campo e o sistema diz se a máquina precisa de intervenção, **quais componentes** e **em quantos dias**.

## Arquitetura

```
web/      Laravel 13 (PHP 8.3+)  → aplicação: registro, plano, calendário × condição, situação, casos reais, documentos, API
coletor/  Python                 → lê curvas de corrente, extrai as grandezas e envia para a API (simulador incluso)
ml/       FastAPI (Python)       → serviço do modelo preditivo (XGBoost + TreeSHAP), usado no Painel de simulação
src/      Python                 → base simulada (3.000 falhas + 1.500 saudáveis), features e treino do modelo
```

- **Decisão operacional** (status, componentes, prazos): regras nominais + tendência linear por data, em PHP (`web/app/Services/Cbm/Avaliador.php`). Mín. 3 leituras em datas distintas para extrapolar.
- **Modelo preditivo**: continua em Python (XGBoost/TreeSHAP não têm equivalente razoável em PHP) e é consumido por HTTP (`MlClient`). Se o serviço estiver fora, o Painel de simulação mostra um aviso e o resto do sistema segue funcionando.
- O modelo foi treinado com telemetria **sintética**; por isso `config/cbm.php` mantém `usar_modelo_na_decisao = false` até haver retreino com dados reais.

## Como rodar

Pré-requisitos: PHP 8.3+, Composer, Node 20+, Python 3.11+ (todos no PATH).

**Depois do clone, dois comandos** (na raiz do projeto, PowerShell):

```powershell
powershell -ExecutionPolicy Bypass -File setup.ps1     # uma vez: venv + bibliotecas, composer, npm build, .env com token, banco do app, banco do modelo
powershell -ExecutionPolicy Bypass -File iniciar.ps1   # sobe o modelo (8001) e o app (8000) e abre http://127.0.0.1:8000
```

O que vem no repositório para o projeto funcionar sem treinar nada:
- `models/`: os três modelos treinados e as métricas (2,7 MB).
- `dados/cbm.db.xz.001`, `.002`: o banco do serviço do modelo, enxuto e compactado (~92 MB em partes de até 45 MB, abaixo do limite do GitHub). Na primeira consulta, `src/db.py` o restaura em `data/cbm.db` (~10 s).
- `web/database/data/eventos_falhas_reais.csv`: os 16 eventos reais da Motiva, importados pelo `migrate --seed`.

Após retreinar, `python run_pipeline.py --empacotar` atualiza as partes em `dados/` (~2 min a mais).

**Passo a passo manual** (o mesmo que os scripts fazem; para retreinar do zero):

```powershell
# 1) Modelo: gera a base simulada, treina e popula data/cbm.db (usado só pelo serviço do modelo; ~10 min, ~250 MB)
python -m venv .venv ; .venv\Scripts\pip install -r requirements.txt
.venv\Scripts\python run_pipeline.py            # --regerar recria a base

# 2) Serviço do modelo — terminal 1 (raiz do projeto)
.venv\Scripts\uvicorn ml.main:app --port 8001

# 3) Aplicação — terminal 2
cd web
composer install ; npm install ; npm run build
copy .env.example .env ; php artisan key:generate   # e defina CBM_API_TOKEN no .env para a API/coletor
php artisan migrate --seed          # cria tabelas, importa os 16 casos reais e carrega leituras de exemplo
php artisan serve                   # http://127.0.0.1:8000
```

Desenvolvimento do front com recarga automática: `npm run dev` (em paralelo ao `php artisan serve`).

## Telas

| Rota | Função |
|---|---|
| `/` | Início: boas-vindas, KPIs por status, alertas (imediatos, a programar, máquinas sem leitura há 14+ dias), atenção e últimas leituras |
| `/registrar` | Formulário de leitura + veredito imediato (precisa intervir? componentes e prazo) |
| `/plano` | Intervenções no horizonte de 7/15/30/60 dias, com CSV |
| `/calendario` | Calendário × condição: próxima preventiva do PMP contra o prazo da condição (antecipar, incluir na preventiva, manter) e dias de antecedência ganhos |
| `/estoque` | Estoque de peças: demanda do plano por peça (até horizonte + prazo de reposição) contra estoque e mínimo; Ruptura (falta antes de a reposição chegar), Pedir até, Pedir agora. Tabela `pecas` com estoque e prazos **fictícios** (`PecaSeeder`) |
| `/situacao`, `/situacao/{máquina}` | Status por máquina, cadastro (versão, linha, local/grupo do PMP), gráficos com os limites da versão, histórico e exclusão |
| `/simulacao` | Painel de simulação do modelo (40 máquinas sintéticas, SHAP, curva de corrente) |
| `/casos-reais` | 16 eventos reais de 2016–2017 extraídos das apresentações |
| `/documentos` | Manuais Siemens e roteiros de manutenção (PDFs em `extracted/`) |
| `/admin` | Importação de leituras por CSV e carga/remoção de dados de exemplo |

Comandos úteis: `php artisan cbm:mock` / `php artisan cbm:mock --limpar` (dados de exemplo marcados com `responsavel = MOCK`), `php artisan test`.

## API de integração (sensores / monitoramento remoto)

Autenticação por `Authorization: Bearer <CBM_API_TOKEN>` (definido no `.env`; vazio = API desabilitada). Leituras da API entram com origem `api` e passam pela mesma avaliação do registro manual.

| Método | Rota | Função |
|---|---|---|
| POST | `/api/v1/leituras` | Uma leitura ou `{"leituras": [...]}` (até 500, tudo ou nada); devolve a avaliação das máquinas |
| POST | `/api/v1/eventos` | Eventos do log do intertravamento, um ou `{"eventos": [...]}`: `maquina`, `ocorrido_em`, `evento` (`perda_correspondencia`\|`travamento`), `observacoes` opcional. Entram sem medição (origem `intertravamento`) e disparam a regra do fuso na hora |
| GET | `/api/v1/maquinas?h=15` | Status e plano de todas as máquinas |
| GET | `/api/v1/maquinas/{código}` | Status e plano de uma máquina |

Campos: `maquina`, `medido_em`, `tempo_manobra_s` (obrigatórios); `forca_n`, `isolamento_mohm`, `corrente_pico`, `corrente_media`, `vibracao_mm_s`, `temperatura_motor_c`, `umidade_gabinete_pct`, `ocorrencia`, `observacoes`, `responsavel`, `tipo` (`agulha`|`jacare`).

Grandezas de condição pedidas no kick-off (`config/cbm.php` → `condicao`): vibração do motor (ISO 10816-1 classe I: alerta 2,8, crítico 4,5 mm/s → rolamentos e engrenagens), temperatura do motor (premissa 70/85 °C → motor) e umidade do gabinete (premissa 80/95% → vedação). Acima do alerta o componente entra no plano como "Programar"; no crítico, "Imediato". A ocorrência "Infiltração no gabinete" aponta a vedação, e "Reaperto de parafusos" registra o reaperto. Ele passa a ser indicado pela condição, na ação das barras, e não mais a cada 3–6 semanas.

- **Sensor só de corrente:** força e isolamento são opcionais, pois saem da inspeção ou de sensores próprios. A avaliação usa o **último valor conhecido** de cada grandeza, então uma leitura de sensor não "apaga" um isolamento crítico medido em campo; a tendência de cada grandeza usa só as leituras que a mediram.
- **Reenvio seguro:** leitura da mesma máquina no mesmo instante (`medido_em`) é ignorada, inclusive repetida dentro do lote. A resposta traz `registradas` e `duplicadas_ignoradas`, com HTTP 201 se gravou algo e 200 se tudo era repetido. Nos eventos, a chave é máquina + instante + tipo do evento.
- **Por que eventos têm rota própria:** a perda de correspondência é o único precursor do retorno do fuso. Na base simulada, o sistema avisou as 149 de 149 falhas do fuso que tiveram esse precursor. Vinda direto do log, ela não depende de alguém lembrar de registrar.

## Coletor (medição automática)

`coletor/` faz o papel do equipamento de campo, como o sistema de monitoramento NI que a Linha 4 usou em 2017–18. Ele lê a **curva de corrente** de cada manobra, extrai **tempo de manobra, pico, média e área** e envia para a API. Se a API estiver fora, guarda as leituras em `coletor/fila.jsonl` e reenvia depois.

```powershell
.venv\Scripts\python -m coletor simular --maquina "MCH 5001 JM" --cenario atrito --ao-vivo   # histórico de 20 dias + manobras ao vivo
.venv\Scripts\python -m coletor arquivo curvas.csv      # curvas reais: maquina, medido_em, t_s, corrente_a (uma linha por amostra)
.venv\Scripts\python -m coletor eventos coletor\exemplo_intertravamento.csv               # log do intertravamento (maquina, ocorrido_em, evento)
.venv\Scripts\python -m coletor eventos log.csv --acompanhar                             # continua lendo o log e envia as linhas novas
.venv\Scripts\python -m coletor reenviar                # envia o que ficou nas filas (leituras e eventos)
.venv\Scripts\python -m unittest coletor.test_coletor   # testes
```

- Cenários do simulador: `normal`, `atrito` (tempo e corrente de fim de curso sobem, como no caso da MCH 4123 JM de 2017) e `motor` (pico de partida cai). As curvas são ilustrativas.
- Força e isolamento não saem da corrente: continuam vindo da inspeção (manual 8.4.5). A força poderá ser estimada da corrente e calibrada com as medições manuais.
- Quando a Motiva enviar o formato do arquivamento do sistema NI, basta uma nova função em `coletor/fontes.py`.
- Log do intertravamento: `fontes.ler_eventos` traduz os nomes do log ("Perda de correspondência", "Maquina travada"…) para os códigos da API e ignora os demais eventos (comandos de manobra etc.). Quando a Motiva enviar o formato real do log, basta ajustar o dicionário `EVENTOS`. Os eventos têm fila própria (`coletor/fila_eventos.jsonl`).
- **Inspeção dirigida do fuso** (`config/cbm.php` → `inspecao_fuso`): o que continua invisível ao monitoramento entra na preventiva. O Calendário × condição lembra, nas máquinas estratégicas, de inspecionar microswitch, pino e roletes do fuso na próxima N2.
- As leituras simuladas levam `responsavel = MOCK` e saem em "Remover dados de exemplo".

**Roteiro de demonstração:** abra `/situacao/MCH 5001 JM?ao_vivo=1` (a página se atualiza a cada 10 s) e rode o `simular ... --ao-vivo`. A máquina começa amarela, com prazo de alguns dias, e fica vermelha ("agir agora") quando o tempo de manobra cruza o limite da versão jacaré (7,0 s).

## Modelo preditivo e base simulada

`src/gerar_casos.py` cria **3.000 históricos que terminam em falha + 1.500 saudáveis** (uma leitura a cada 6 h, ~30 dias cada), calibrados nos 16 casos reais:

| Modo | Peso | Base | Assinatura simulada |
|---|---|---|---|
| Atrito / travamento no fim de curso | 30% | casos 4, 10, 11, 13 | tempo ↑, corrente no fim do curso 1,3 → ~1,65 A |
| Barras de detecção / folga mecânica | 29% | casos 7, 12, 14, 15 | tempo excessivo, perdas de correspondência intermitentes |
| Retorno do fuso após travamento | 23% | casos 8, 9, 16 | **nenhuma na corrente**; perda de correspondência antes em 60% dos casos |
| Motor / embreagem / talonada | 6% cada | manual Siemens, seção 11 | pico ↓ / força ↓ / força e pico ↑ |

Para se aproximar do campo, a base tem casos sutis, 30% de sinais misturados e ruído benigno nas máquinas saudáveis.

`src/stage3_model.py` treina dois modelos:
- **Detector de falha:** XGBoost que estima a chance de falha nos próximos 10 ciclos (~2,5 dias).
- **Classificador do modo de falha.**

Além das grandezas medidas, o modelo usa a **base própria de cada máquina**: tempo, força e corrente comparados com as primeiras leituras do histórico, porque cada S700K tem seu "normal". Usa também a **tendência explícita**: a inclinação do tempo, da área e do pico nas últimas 10 manobras.

**Dois níveis de aviso:**
- 🔴 **Vermelho, "intervir":** modelo de curto prazo, que prevê falha em até 2,5 dias.
- 🟡 **Amarelo, "programar inspeção":** um **segundo modelo, de prazo longo**, que prevê falha em até 7,5 dias e aprende os sinais mais precoces, **ou** perda de correspondência recente, o único precursor do retorno do fuso.

**Calibração pela assertividade:** os dois limiares são escolhidos juntos, na validação (grade de 18 × 18 quantis), para a maior assertividade. A trava de segurança é avisar ao menos 99% das falhas que a regra anterior avisava (teto de 10% de falso no vermelho e de 20% no amarelo). Sem a tolerância de 1%, o ruído da validação força limiares baixos e o aviso falso sobe (testado: 83%).

Métricas de 1.350 históricos nunca vistos no treino (`models/metricas.json`):

| | Só vermelho | **Amarelo ou vermelho (em uso)** |
|---|---|---|
| Falhas com aviso | 91% | **94%** (851 de 908) |
| Antecedência mediana do 1º aviso | 90 h | **108 h (4,5 dias)** |
| Retorno do fuso avisado | — | **74%** |
| Máquinas saudáveis com algum aviso | 11% | 16% |
| **Assertividade** | da intervenção: **89%** | geral: **87%** |

Antes da calibração pela assertividade: 84% geral, 850 falhas avisadas e 106 avisos falsos. Agora são 851 avisadas e 71 avisos falsos.

**Como passar de 95% (experimento na base atual):** com os sinais de hoje, o teto é ~88%, mesmo com o limiar ajustado no próprio teste. Quase todo erro que sobra é retorno do fuso sem sinal na corrente. **Com o tempo de correspondência (hipótese), a assertividade é de 92% geral e 98% da intervenção.** "Da intervenção" conta como falso só o vermelho em máquina saudável: o amarelo manda inspecionar, e inspecionar sem achar defeito é verificação. A assertividade real é medida no Painel com as ocorrências "Inspeção após alerta: defeito confirmado / sem defeito" (`AssertividadeCampo`).

O amarelo chega, na mediana, **30 h antes do vermelho**. Das falhas que continuam sem aviso, quase todas são retorno do fuso **sem nenhum sinal** nos dados; só o monitoramento da correspondência resolveria (cenário hipotético abaixo). Acerto do modo: 94%.

**Folga para reposição de peças (5 dias, `DIAS_REPOSICAO`):** o amarelo dá, em mediana, **30 h** de folga até o vermelho; só 0,4% dos avisos dão 5 dias ou mais. Testamos um amarelo de prazo mais longo (10, 12,5 e 15 dias, com teto de até 30% de aviso falso): a folga não passou de 54 h e só ~5% das falhas tiveram 5 dias, com 31% de aviso falso. O limite é físico: na base simulada a degradação começa ~8 dias antes da falha e o vermelho vem ~3,5 dias antes. Para peças com 5 dias de reposição, a saída é **estoque de segurança** dimensionado pela previsão de demanda, e não forçar o amarelo mais cedo. O Painel mostra as **regras de cada alerta** (gatilhos, quando desliga, ação, calibração e desempenho), lidas das métricas do modelo.

Experimento que levou a essa escolha (média de 2 bases): só vermelho, 89% e 87 h; + amarelo do modelo atual, 92,5% e 96 h; + amarelo do modelo de prazo longo, 93,1% e 111 h; + regra da perda de correspondência, 93,7% e 117 h.

**Como chegamos a 88%:** cada alavanca foi medida com 3 bases diferentes, com o mesmo teto de alarme falso (`scratchpad`, não versionado):

| Configuração | Antecipadas (média, faixa) | Fuso |
|---|---|---|
| Modelo anterior (1.000 falhas) | 79% (78–80%) | 48% |
| + base própria da máquina e tendência | 85% (82–89%) | 54% |
| **+ 3× amostras (em uso)** | **89% (88–90%)** | 62% |
| + tempo de confirmação da correspondência (**hipótese**) | 96% (96–97%) | 87% |

Os 74% medidos antes vinham de uma base sorteada "azarada"; só o sorteio já variava ~5 pontos.

**Cenário da correspondência:** é uma hipótese. Supõe que o tempo de confirmação da microswitch atrasa antes do retorno do fuso, como indicam os casos 8 e 9. O Painel o mostra à parte, e ele **não entra no modelo em uso** (`MONITORAR_CORRESPONDENCIA = False` em `src/config.py`). Serve de argumento para a Motiva avaliar se o intertravamento já registra esse tempo.

O Painel de simulação mostra o **gabarito** de cada histórico ao lado da previsão.

**Persistência + histerese (vale para os dois níveis):** o aviso liga quando 2 das últimas 3 leituras passam do limiar e só desliga quando as 3 últimas ficam abaixo de 60% dele. Uma oscilação isolada não vira aviso, e o status não pisca. No vermelho, cada máquina saudável que alarma liga e desliga 2,1 vezes em média; por leitura isolada, seriam 3,2.

**Por que o gráfico só mostra falhas antecipadas a partir do ciclo 70:** na base simulada, toda falha acontece entre os ciclos 70 e 119. Os vermelhos e amarelos antes disso **são** a antecipação: no ciclo 70, 98% dos vermelhos são de máquinas que vão falhar logo depois.

**Simulação ao vivo** (`/simulacao/ao-vivo`): anima os ciclos 5 a 119.
- Cada histórico é um ponto colorido pelo estado do modelo.
- Contadores ao vivo: falhas antecipadas, falhas sem aviso, alarmes falsos.
- **Assertividade do modelo** = avisos certos ÷ (avisos certos + falhas sem aviso + avisos falsos). Conta amarelo ou vermelho, cada aviso é julgado quando dispara, e máquinas saudáveis sem aviso não entram, para o modelo não ganhar ponto por "não fazer nada". Só aparece a partir de 20 decisões. No teste, termina em 87%; **da intervenção** (só vermelho conta como falso), 89%; precisão dos avisos, 92%.
- Feed de eventos, gráfico de evolução, play/pausa (barra de espaço), velocidade de 1× a 8×; clicar num ponto abre o diagnóstico.
- Por padrão mostra só os **1.350 históricos de teste**, que o modelo nunca viu, e por isso termina com os mesmos números oficiais. "Todos (4.500)" inclui o treino e exibe um aviso de números otimistas.
- O serviço do modelo leva ~13 s para preparar os dados ao subir (464 mil leituras). Abra a página depois disso.
- **Para apresentar:** suba primeiro o serviço do modelo (porta 8001) e depois o Laravel (8000). Se a 8000 estiver ocupada, o `artisan serve` pega a 8001 e o painel fica sem modelo.

## Regras e fontes

| Regra | Valor | Fonte |
|---|---|---|
| Versão agulha (com retrocesso) | período 6,0 s; força 5.500 ± 500 N | Manual Siemens 26330-OM-15, 4.1 |
| Versão jacaré móvel (sem retrocesso) | período 6,5 s; força 6.000 ± 500 N | Manual Siemens 26330-OM-15, 4.1 |
| Tolerância do tempo de manobra | ± 0,5 s | **Premissa do projeto** (o manual dá só o nominal) — validar |
| Isolamento do detector | > 1 MΩ | Manual Siemens, 8.4.5 |
| Preventivas N1/N2/NL/N3 | 4/8/20/44 sem (estratégicos X03, X04, X12, X13); 8/16/24/48 sem (demais X); 12/24/–/48 sem (pátio) | PMP-3AMV-011-10, tabelas 8–10 |
| Versão pelo código | sufixo `AG` = agulha, `JM` = jacaré móvel | Inferência — editável no cadastro |

## Dados e limitações

- `raw_data/` contém a base **simulada** (calibrada nos casos reais, mas simulada). As métricas mostram o método funcionando, não o desempenho real, que só se mede com curvas reais da Linha 4.
- Componentes e ações foram inferidos dos casos reais de 2017 e dos 4 modos de falha do desafio; a equipe de via permanente deve validá-los.
- Insight dos casos reais: 3 de 16 falhas (retorno do fuso após travamento) tiveram corrente e tempo normais — a corrente sozinha não as detecta. O sistema trata ocorrências de perda de correspondência/travamento como sinal do fuso/microswitch.
- Os dados de exemplo (locais X, datas de preventiva, medições) são fictícios; o PMP e o manual são reais.
- Ainda **sem autenticação** nas telas: antes de uso fora de demonstração, adicionar login e perfis (o campo "Responsável" passaria a vir do usuário). A API já exige token.
