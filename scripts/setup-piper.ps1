# Instala Piper TTS + voz pt-BR no CriaSys (Windows)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Bin = Join-Path $Root "bin\piper"
New-Item -ItemType Directory -Force -Path $Bin | Out-Null

$PiperZip = "https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_windows_amd64.zip"
$PiperExe = Join-Path $Bin "piper\piper.exe"
$ModelBase = "https://huggingface.co/rhasspy/piper-voices/resolve/main/pt/pt_BR/faber/medium"
$ModelOnnx = Join-Path $Bin "pt_BR-faber-medium.onnx"
$ModelJson = Join-Path $Bin "pt_BR-faber-medium.onnx.json"

Write-Host "==> Baixando Piper..." -ForegroundColor Cyan
$zipPath = Join-Path $env:TEMP "piper_win.zip"
Invoke-WebRequest -Uri $PiperZip -OutFile $zipPath
Expand-Archive -Path $zipPath -DestinationPath $Bin -Force
Get-ChildItem -Path (Join-Path $Bin "piper") -Recurse -Filter "piper.exe" -ErrorAction SilentlyContinue | Select-Object -First 1 | ForEach-Object {
    # piper.exe já está em piper/ após Expand-Archive
}

Write-Host "==> Baixando voz pt_BR Faber (feminina)..." -ForegroundColor Cyan
Invoke-WebRequest -Uri "$ModelBase/pt_BR-faber-medium.onnx" -OutFile $ModelOnnx
Invoke-WebRequest -Uri "$ModelBase/pt_BR-faber-medium.onnx.json" -OutFile $ModelJson

$EdressonBase = "https://huggingface.co/rhasspy/piper-voices/resolve/main/pt/pt_BR/edresson/low"
$EdressonOnnx = Join-Path $Bin "pt_BR-edresson-low.onnx"
$EdressonJson = Join-Path $Bin "pt_BR-edresson-low.onnx.json"
Write-Host "==> Baixando voz pt_BR Edresson (masculina)..." -ForegroundColor Cyan
try {
    Invoke-WebRequest -Uri "$EdressonBase/pt_BR-edresson-low.onnx" -OutFile $EdressonOnnx
    Invoke-WebRequest -Uri "$EdressonBase/pt_BR-edresson-low.onnx.json" -OutFile $EdressonJson
} catch {
    Write-Host "   (Edresson opcional falhou — Faber já basta)" -ForegroundColor DarkYellow
}

Write-Host ""
Write-Host "Pronto! Adicione ao .env:" -ForegroundColor Green
Write-Host "PIPER_PATH=$PiperExe"
Write-Host "PIPER_MODEL=$ModelOnnx"
Write-Host "TTS_DEFAULT_ENGINE=piper"
Write-Host ""
Write-Host "Teste: echo Ola mundo | `"$PiperExe`" --model `"$ModelOnnx`" --output_file test.wav" -ForegroundColor Yellow
