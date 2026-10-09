$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -LiteralPath $projectRoot

$laragonPhp = 'D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe'
$phpExecutable = if (Test-Path -LiteralPath $laragonPhp) { $laragonPhp } else { (Get-Command php).Source }

if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
if (-not (Test-Path -LiteralPath 'database/database.sqlite')) { New-Item -ItemType File -Path 'database/database.sqlite' | Out-Null }
& $phpExecutable artisan key:generate --force
& $phpExecutable artisan migrate:fresh --seed --force
Write-Host 'Setup complete. Run .\dev.ps1 to start the application.' -ForegroundColor Green
