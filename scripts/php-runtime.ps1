$ErrorActionPreference = 'Stop'
function Get-ClinicPhp {
    if ($env:CLINIC_PHP -and (Test-Path -LiteralPath $env:CLINIC_PHP)) { return $env:CLINIC_PHP }
    $candidateRoot = $PSScriptRoot
    while ($candidateRoot) {
        $phpFolder = Join-Path $candidateRoot 'bin/php'
        if (Test-Path -LiteralPath $phpFolder) {
            $candidate = Get-ChildItem -LiteralPath $phpFolder -Filter php.exe -File -Recurse | Where-Object { $_.Directory.Name -like 'php-8.*' } | Sort-Object FullName -Descending | Select-Object -First 1
            if ($candidate) { return $candidate.FullName }
        }
        $parent = Split-Path -Parent $candidateRoot
        if ($parent -eq $candidateRoot) { break }
        $candidateRoot = $parent
    }
    return (Get-Command php -ErrorAction Stop).Source
}
function Invoke-ClinicPhp {
    param([Parameter(ValueFromRemainingArguments=$true)][string[]] $PhpArguments)
    & $script:clinicPhpExecutable @PhpArguments
    if ($LASTEXITCODE -ne 0) { throw "PHP command failed (exit $LASTEXITCODE)." }
}
$script:clinicPhpExecutable = Get-ClinicPhp
Invoke-ClinicPhp (Join-Path $PSScriptRoot 'check-runtime.php')
