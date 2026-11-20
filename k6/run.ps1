# One-command load test: clean log, run the ramp, rebuild the report.
#   .\k6\run.ps1
Set-Location (Split-Path $PSScriptRoot -Parent)
Remove-Item k6/replies.log -ErrorAction SilentlyContinue  # k6 appends across runs
$env:K6_PROMETHEUS_RW_SERVER_URL = "http://localhost:9091/api/v1/write"
$env:K6_PROMETHEUS_RW_TREND_STATS = "p(50),p(95),p(99)"
$k6 = (Get-Command k6 -ErrorAction SilentlyContinue).Source
if (-not $k6) { $k6 = 'C:\Program Files\k6\k6.exe' }  # winget install often missing from PATH
& $k6 run --out experimental-prometheus-rw --log-format=raw --log-output=file=k6/replies.log k6/load-model.js
php k6/build-report.php
Write-Host "`nReport: http://localhost:8000/load-test-report.html"
Read-Host 'Done - press Enter to close'