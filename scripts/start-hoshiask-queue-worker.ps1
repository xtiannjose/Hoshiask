# Starts this project's Laravel queue worker (sends the queued ticket emails) on a
# Windows PC, for local testing.
param(
    [switch]$Foreground
)

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Artisan = Join-Path $ProjectRoot "artisan"
$Php = (Get-Command php -ErrorAction Stop).Source
$WorkerOptions = @("queue:work", "--sleep=3", "--tries=3", "--max-time=3600")

# The worker is started with the full path to this project's artisan file, so only a
# worker belonging to this project counts as "already running" (not another app's).
$ExistingWorker = Get-CimInstance Win32_Process |
    Where-Object {
        $_.CommandLine -and
        $_.CommandLine.IndexOf($Artisan, [StringComparison]::OrdinalIgnoreCase) -ge 0 -and
        $_.CommandLine.Contains("queue:work")
    } |
    Select-Object -First 1

if ($ExistingWorker) {
    Write-Host "Hoshiask queue worker already running with PID $($ExistingWorker.ProcessId)."
    exit 0
}

if ($Foreground) {
    Set-Location $ProjectRoot
    & $Php $Artisan @WorkerOptions
    exit $LASTEXITCODE
}

$Process = Start-Process `
    -FilePath $Php `
    -ArgumentList (@("`"$Artisan`"") + $WorkerOptions) `
    -WorkingDirectory $ProjectRoot `
    -WindowStyle Hidden `
    -PassThru

Write-Host "Started Hoshiask queue worker with PID $($Process.Id)."
