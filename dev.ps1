$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
. (Join-Path $PSScriptRoot 'scripts/php-runtime.ps1')
try {
    $runningClinic = Invoke-WebRequest -Uri 'http://127.0.0.1:8000' -UseBasicParsing -TimeoutSec 3
    if ($runningClinic.Headers['X-Clinic-App'] -eq 'n05-group3') {
        Write-Host 'Clinic is already running at http://127.0.0.1:8000 (Laragon Apache).' -ForegroundColor Green
        return
    }
} catch {
    # No working clinic server: fall through to the regular Laravel dev server.
}
if (Get-NetTCPConnection -State Listen -LocalPort 8000 -ErrorAction SilentlyContinue) {
    throw 'Port 8000 is occupied. Check the Laragon Apache site or stop the other server before starting Laravel.'
}
Invoke-ClinicPhp artisan serve --host=127.0.0.1 --port=8000
