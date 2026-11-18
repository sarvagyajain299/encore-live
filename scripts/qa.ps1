<#
.SYNOPSIS
  Full QA run: static checks, unit tests, every HTTP smoke suite and the browser tests.
  Each suite gets freshly seeded data. Prints one summary table; exits 1 on any failure.

  Prerequisites: stack up and installed (.\scripts\encore.ps1 up; .\scripts\encore.ps1 install),
  and browser test deps once: cd tests\e2e; npm install
#>
$ErrorActionPreference = 'Continue'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $root
$results = @()

function Add-Result([string]$Suite, [string]$Command, [bool]$Ok, [string]$Detail) {
    $script:results += [pscustomobject]@{ Suite = $Suite; Result = $(if ($Ok) { 'PASS' } else { 'FAIL' }); Detail = $Detail; Command = $Command }
}
# Absolute path: browser suites run from tests/e2e, where a relative path would not resolve.
# encore.ps1 changes the working directory, so restore it afterwards.
function Reset-Data {
    $here = Get-Location
    & (Join-Path $root 'scripts/encore.ps1') seed --force *> $null
    docker compose --project-directory $root run --rm -T wpcli wp eval 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' *> $null
    Set-Location $here
}
function Invoke-Smoke([string]$Name, [scriptblock]$Before = {}) {
    Reset-Data; & $Before
    $out = & ".\scripts\$Name.ps1" *>&1 | Out-String
    $summary = [regex]::Match($out, '(\d+) passed, (\d+) failed')
    Add-Result $Name ".\scripts\$Name.ps1" ($summary.Success -and $summary.Groups[2].Value -eq '0') $summary.Value
}

$out = .\scripts\encore.ps1 phpcs *>&1 | Out-String
$files = ([regex]::Matches($out, '(\d+) / (\d+) \(100%\)') | Select-Object -Last 1).Groups[2].Value
Add-Result 'PHPCS (WordPress Coding Standards)' '.\scripts\encore.ps1 phpcs' ($LASTEXITCODE -eq 0) ("$files files, 0 errors")

$out = .\scripts\encore.ps1 test *>&1 | Out-String
Add-Result 'PHPUnit + Brain Monkey' '.\scripts\encore.ps1 test' ($out -match 'OK \(') ([regex]::Match($out, 'OK \([^)]+\)|Tests: [^\r\n]+').Value)

Invoke-Smoke 'smoke-ticketing'
Invoke-Smoke 'smoke-waitlist'
Invoke-Smoke 'smoke-seo'
Invoke-Smoke 'smoke-api' { .\scripts\encore.ps1 demo *> $null }
Invoke-Smoke 'smoke-security'

Push-Location tests/e2e
Reset-Data
$out = node node_modules/@playwright/test/cli.js test *>&1 | Out-String
Add-Result 'Playwright purchase journey' 'cd tests/e2e; npx playwright test' ($out -match '\d+ passed' -and $out -notmatch 'failed') ([regex]::Match($out, '\d+ passed[^\r\n]*').Value)

Reset-Data
docker compose --project-directory $root run --rm -T wpcli wp eval "update_option( 'encore_settings', array( 'max_tickets_per_order' => 6, 'gtm_container_id' => 'GTM-TEST123' ) );" *> $null
$out = node analytics-check.mjs *>&1 | Out-String
docker compose --project-directory $root run --rm -T wpcli wp eval "update_option( 'encore_settings', array( 'max_tickets_per_order' => 6, 'gtm_container_id' => '' ) );" *> $null
$summary = [regex]::Match($out, '(\d+) passed, (\d+) failed')
Add-Result 'GA4 dataLayer journey' 'cd tests/e2e; npm run analytics' ($summary.Success -and $summary.Groups[2].Value -eq '0') $summary.Value
Pop-Location

Reset-Data
$results | Format-Table Suite, Result, Detail -AutoSize | Out-String -Width 200
$results | ConvertTo-Json | Set-Content docs/qa-last-run.json
if ($results | Where-Object Result -eq 'FAIL') { exit 1 }
