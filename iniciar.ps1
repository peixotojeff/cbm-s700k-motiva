# Sobe o serviço do modelo (porta 8001) e depois o app (porta 8000), cada um na sua janela, e abre o navegador.
#   powershell -ExecutionPolicy Bypass -File iniciar.ps1
# A ordem importa: se o app subir primeiro e a 8000 estiver ocupada, o artisan serve pega a 8001 e o painel fica sem modelo.
$ErrorActionPreference = 'Stop'
$raiz = $PSScriptRoot

function NoAr($url) { try { Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 3 | Out-Null; $true } catch { $false } }

if (-not (Test-Path "$raiz\.venv\Scripts\uvicorn.exe")) { throw 'Rode antes: powershell -ExecutionPolicy Bypass -File setup.ps1' }

if (NoAr 'http://127.0.0.1:8001/health') { Write-Host 'Modelo já está no ar (8001).' }
else {
    Write-Host 'Subindo o serviço do modelo (porta 8001)...'
    Start-Process powershell -WorkingDirectory $raiz -ArgumentList '-NoExit', '-Command', '$host.UI.RawUI.WindowTitle = ''CBM - modelo (8001)''; .\.venv\Scripts\uvicorn ml.main:app --port 8001'
    $ok = $false
    for ($i = 0; $i -lt 60 -and -not $ok; $i++) { Start-Sleep 2; $ok = NoAr 'http://127.0.0.1:8001/health' }
    if (-not $ok) { throw 'O serviço do modelo não respondeu em 2 min; veja a janela "CBM - modelo".' }
}

if (NoAr 'http://127.0.0.1:8000/') { Write-Host 'App já está no ar (8000).' }
else {
    Write-Host 'Subindo o app (porta 8000)...'
    Start-Process powershell -WorkingDirectory "$raiz\web" -ArgumentList '-NoExit', '-Command', '$host.UI.RawUI.WindowTitle = ''CBM - app (8000)''; php artisan serve --port=8000'
    for ($i = 0; $i -lt 30 -and -not (NoAr 'http://127.0.0.1:8000/'); $i++) { Start-Sleep 1 }
}

Write-Host "`nApp: http://127.0.0.1:8000  (o Painel de simulação leva ~15 s na primeira abertura)" -ForegroundColor Green
Start-Process 'http://127.0.0.1:8000'
