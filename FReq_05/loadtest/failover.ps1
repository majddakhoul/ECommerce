param(
    [int]$StopAfter = 60,
    [int]$StartAfter = 100,
    [string]$Instance = "app2",
    [string]$Workload = "read",
    [string]$Total = "500",
    [string]$Seconds = "120"
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path -Parent $PSScriptRoot)

New-Item -ItemType Directory -Force -Path "loadtest/results" | Out-Null

$arguments = @(
    "compose", "--profile", "tools", "run", "--rm",
    "-e", "WORKLOAD=$Workload",
    "-e", "TOTAL=$Total",
    "-e", "SECONDS=$Seconds",
    "-e", "LABEL=failover-$Workload",
    "-e", "FAIL_RATE=0.05",
    "k6", "run", "/scripts/scale.js"
)

$k6 = Start-Process -FilePath "docker" -ArgumentList $arguments -NoNewWindow -PassThru

Start-Sleep -Seconds $StopAfter
Write-Host "Stopping $Instance"
docker compose stop $Instance

$remaining = [Math]::Max(0, $StartAfter - $StopAfter)
Start-Sleep -Seconds $remaining
Write-Host "Starting $Instance"
docker compose start $Instance

$k6.WaitForExit()
exit $k6.ExitCode
