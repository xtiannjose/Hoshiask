# Makes the queue worker start automatically when you log in to this Windows PC
# (local testing). Production (Linux) uses docs/helpdesk-queue-worker.service.example.
# Reminder emails, backups and auto-close also need the scheduler: run
# `php artisan schedule:work` in a terminal while testing.
param(
    [string]$TaskName = "Helpdesk Queue Worker"
)

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$StartScript = Join-Path $ProjectRoot "scripts\start-helpdesk-queue-worker.ps1"
$PowerShell = (Get-Command powershell.exe -ErrorAction Stop).Source
$StartupDirectory = [Environment]::GetFolderPath("Startup")

$Action = New-ScheduledTaskAction `
    -Execute $PowerShell `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$StartScript`" -Foreground" `
    -WorkingDirectory $ProjectRoot

$Trigger = New-ScheduledTaskTrigger -AtLogOn
$Principal = New-ScheduledTaskPrincipal `
    -UserId "$env:USERDOMAIN\$env:USERNAME" `
    -LogonType Interactive `
    -RunLevel Limited

$Settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1)

try {
    Register-ScheduledTask `
        -TaskName $TaskName `
        -Action $Action `
        -Trigger $Trigger `
        -Principal $Principal `
        -Settings $Settings `
        -Force | Out-Null

    Write-Host "Registered scheduled task: $TaskName"
} catch {
    $LauncherPath = Join-Path $StartupDirectory "start-helpdesk-queue-worker.cmd"
    $Launcher = @(
        "@echo off",
        "powershell.exe -NoProfile -ExecutionPolicy Bypass -File `"$StartScript`""
    )

    Set-Content -Path $LauncherPath -Value $Launcher -Encoding ASCII

    Write-Host "Scheduled task registration was denied."
    Write-Host "Created Startup launcher instead: $LauncherPath"
}
