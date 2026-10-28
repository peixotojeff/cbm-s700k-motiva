# Prepara o projeto depois do clone (uma vez). Uso, na raiz do projeto:
#   powershell -ExecutionPolicy Bypass -File setup.ps1
# Pré-requisitos no PATH: PHP 8.3+, Composer, Node 20+ e Python 3.11+.
$ErrorActionPreference = 'Stop'
$raiz = $PSScriptRoot
Set-Location $raiz

function Passo($texto) { Write-Host "`n==> $texto" -ForegroundColor Cyan }
function Exigir($cmd, $nome) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) { throw "$nome não encontrado no PATH. Instale e abra um terminal novo." }
}
function Rodar([scriptblock]$bloco, $erro) { & $bloco; if ($LASTEXITCODE -ne 0) { throw $erro } }

Passo 'Conferindo pré-requisitos'
Exigir php 'PHP 8.3+'; Exigir composer 'Composer'; Exigir npm 'Node 20+ (npm)'
$py = if (Get-Command py -ErrorAction SilentlyContinue) { 'py' } elseif (Get-Command python -ErrorAction SilentlyContinue) { 'python' } else { throw 'Python 3.11+ não encontrado no PATH.' }

Passo 'Python: ambiente virtual e bibliotecas do modelo'
if (-not (Test-Path .venv\Scripts\python.exe)) { Rodar { & $py -m venv .venv } 'falha ao criar .venv' }
Rodar { .venv\Scripts\python.exe -m pip install --quiet --upgrade pip } 'falha no pip'
Rodar { .venv\Scripts\python.exe -m pip install --quiet -r requirements.txt } 'falha ao instalar requirements.txt'

Passo 'Banco do modelo: restaurando data\cbm.db a partir de dados\ (~10 s)'
if (Test-Path data\cbm.db) { Write-Host 'já existe; mantido' }
else { Rodar { .venv\Scripts\python.exe -X utf8 -m src.pacote restaurar } 'falha ao restaurar o banco do modelo' }

Passo 'Laravel: dependências e front'
Set-Location web
Rodar { composer install --no-interaction --quiet } 'falha no composer install'
Rodar { npm ci --silent } 'falha no npm ci'
Rodar { npm run build --silent } 'falha no npm run build'

Passo 'Laravel: .env, chave e token da API'
if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    Rodar { php artisan key:generate --force } 'falha no key:generate'
    $token = ([guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N'))
    # UTF-8 sem BOM: o Set-Content do Windows PowerShell 5 gravaria um BOM no início do .env
    $linhas = (Get-Content .env -Encoding UTF8) -replace '^CBM_API_TOKEN=.*$', "CBM_API_TOKEN=$token"
    [IO.File]::WriteAllLines((Join-Path (Get-Location) '.env'), $linhas, (New-Object Text.UTF8Encoding $false))
    Write-Host '.env criado (token da API gerado; o coletor lê daqui)'
} else { Write-Host '.env já existe; mantido' }

Passo 'Laravel: banco do app (tabelas, 16 casos reais, peças e leituras de exemplo)'
if (-not (Test-Path database\database.sqlite)) { New-Item database\database.sqlite -ItemType File | Out-Null }
Rodar { php artisan migrate --seed --force } 'falha no migrate --seed'

Set-Location $raiz
Write-Host "`nPronto. Para abrir o app: powershell -ExecutionPolicy Bypass -File iniciar.ps1" -ForegroundColor Green
