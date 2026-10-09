$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location -LiteralPath $projectRoot
$laragonPhp = 'D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe'
$phpExecutable = if (Test-Path -LiteralPath $laragonPhp) { $laragonPhp } else { (Get-Command php).Source }
& $phpExecutable artisan serve --host=127.0.0.1 --port=8000
