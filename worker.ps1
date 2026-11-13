$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
. (Join-Path $PSScriptRoot 'scripts/php-runtime.ps1')
Invoke-ClinicPhp artisan schedule:work
