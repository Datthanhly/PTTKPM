$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
. (Join-Path $PSScriptRoot 'scripts/php-runtime.ps1')
if (-not (Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
$phpBinRoot = Split-Path -Parent (Split-Path -Parent (Split-Path -Parent $script:clinicPhpExecutable))
$composerPhar = Join-Path $phpBinRoot 'composer/composer.phar'
if (Test-Path -LiteralPath $composerPhar) { Invoke-ClinicPhp $composerPhar install --no-interaction --prefer-dist }
else {
    & composer install --no-interaction --prefer-dist
    if ($LASTEXITCODE -ne 0) { throw 'Composer install failed.' }
}
Invoke-ClinicPhp artisan config:clear
$environmentText = Get-Content -LiteralPath '.env' -Raw
if ($environmentText -notmatch '(?m)^APP_KEY=.+$') { Invoke-ClinicPhp artisan key:generate --force }
Invoke-ClinicPhp artisan clinic:database
Invoke-ClinicPhp artisan clinic:backup
Invoke-ClinicPhp artisan migrate --force
Invoke-ClinicPhp artisan db:seed --force
Invoke-ClinicPhp artisan view:clear
Write-Host 'Setup complete. Existing data was preserved. Run .\dev.ps1 and .\worker.ps1.' -ForegroundColor Green
