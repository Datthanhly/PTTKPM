param([switch]$RebuildDiagrams)
$ErrorActionPreference = 'Stop'
Push-Location -LiteralPath $PSScriptRoot
try {
    if (-not (Get-Command xelatex -ErrorAction SilentlyContinue)) {
        throw 'Cần XeLaTeX (MiKTeX hoặc TeX Live) để biên dịch báo cáo.'
    }
    if ($RebuildDiagrams) {
        if (-not (Get-Command python -ErrorAction SilentlyContinue)) {
            throw 'Cần Python với PyMuPDF để xuất lại các sơ đồ TikZ.'
        }
        & python tools/export_diagrams.py
        if ($LASTEXITCODE -ne 0) { throw 'Xuất sơ đồ lỗi. Xem tools/diagram-build.txt' }
    }
    for ($pass = 1; $pass -le 3; $pass++) {
        & xelatex -interaction=nonstopmode -halt-on-error -jobname=BaoCao_PhongKham_AnTam main.tex |
            Out-File -LiteralPath "tools/rebuild-$pass.txt" -Encoding UTF8
        if ($LASTEXITCODE -ne 0) { throw "Biên dịch lần $pass lỗi. Xem tools/rebuild-$pass.txt" }
    }
    Write-Output 'Đã tạo BaoCao_PhongKham_AnTam.pdf; không thay đổi code hoặc database ứng dụng.'
} finally {
    Pop-Location
}
