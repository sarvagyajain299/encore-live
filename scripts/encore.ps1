<#
.SYNOPSIS
  Encore Live task runner for Windows PowerShell. Twin of scripts/encore.sh.

.EXAMPLE
  .\scripts\encore.ps1 up
  .\scripts\encore.ps1 install
  .\scripts\encore.ps1 wp plugin list
  .\scripts\encore.ps1 phpcs
#>
param(
    [Parameter(Position = 0)]
    [ValidateSet('up', 'down', 'install', 'wp', 'composer', 'phpcs', 'phpcbf', 'test', 'seed', 'reset', 'shell', 'destroy', 'help')]
    [string]$Command = 'help',

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Rest = @()
)

$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

# Allocate a TTY only when the console is interactive.
$RunFlags = @('--rm')
if ([Console]::IsInputRedirected -or [Console]::IsOutputRedirected) { $RunFlags += '-T' }

function Invoke-Compose {
    docker compose @args
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

function Initialize-Env {
    if (-not (Test-Path .env)) {
        Copy-Item .env.example .env
        Write-Host 'Created .env from .env.example. Change the passwords in it before sharing anything.'
    }
}

# vendor/ is a Docker volume, so check for it inside the container, not on the host.
function Initialize-Vendor {
    Invoke-Compose run @RunFlags tools sh -c 'test -f vendor/autoload.php || composer install --no-interaction'
}

switch ($Command) {
    'up' {
        Initialize-Env
        Invoke-Compose up -d --wait
        Write-Host 'Site: http://localhost:8080  phpMyAdmin: http://localhost:8081  Mailpit: http://localhost:8025'
    }
    'down'     { Invoke-Compose down }
    'install'  { Invoke-Compose run @RunFlags wpcli sh /scripts/install.sh }
    'wp'       { Invoke-Compose run @RunFlags wpcli wp @Rest }
    'composer' { Invoke-Compose run @RunFlags tools composer @Rest }
    'phpcs'    { Initialize-Vendor; Invoke-Compose run @RunFlags tools vendor/bin/phpcs @Rest }
    'phpcbf'   { Initialize-Vendor; Invoke-Compose run @RunFlags tools vendor/bin/phpcbf @Rest }
    'test'     { Initialize-Vendor; Invoke-Compose run @RunFlags tools vendor/bin/phpunit @Rest }
    'seed'     { Invoke-Compose run @RunFlags wpcli wp encore seed @Rest }   # command arrives in Phase 1
    'reset'    { Invoke-Compose run @RunFlags wpcli wp encore reset @Rest }  # command arrives in Phase 1
    'shell'    { Invoke-Compose exec wordpress bash }
    'destroy' {
        $answer = Read-Host "This deletes the database and WordPress volumes. Type 'yes' to continue"
        if ($answer -eq 'yes') { Invoke-Compose down -v } else { Write-Host 'Cancelled.' }
    }
    default { Get-Help $PSCommandPath -Examples }
}
