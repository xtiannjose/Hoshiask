param(
    [string]$RemoteHost = "redmine.siphosted.com",
    [string]$RemoteUser = "admin",
    [string]$Branch = "main",
    [switch]$SkipPush
)

$ErrorActionPreference = "Stop"
$RemotePath = "/opt/helpdesk"

if ($Branch -notmatch '^[A-Za-z0-9._/-]+$') {
    throw "Invalid branch name '$Branch': only letters, digits, '.', '_', '/', and '-' are allowed."
}

if (-not $SkipPush) {
    git push origin $Branch
}

$remoteCommand = @"
set -euo pipefail
cd '$RemotePath'
bash './scripts/deploy-helpdesk-remote.sh' '$Branch'
"@

ssh "$RemoteUser@$RemoteHost" $remoteCommand
