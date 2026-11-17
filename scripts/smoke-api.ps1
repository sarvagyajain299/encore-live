<#
.SYNOPSIS
  Smoke test for Phase 4: encore/v1 REST API, shortcodes and the Elementor widget.
  Needs: .\scripts\encore.ps1 seed --force   and   .\scripts\encore.ps1 demo
#>
$ErrorActionPreference = 'Stop'
$site = 'http://localhost:8080'
$api = "$site/wp-json/encore/v1"
$pass = 0; $fail = 0

function Test-Step([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) { $script:pass++; Write-Host "PASS  $Name" -ForegroundColor Green }
    else { $script:fail++; Write-Host "FAIL  $Name  $Detail" -ForegroundColor Red }
}

function Get-Api([string]$Path) {
    $r = Invoke-WebRequest "$api$Path" -UseBasicParsing -SkipHttpErrorCheck
    [pscustomobject]@{ Status = [int]$r.StatusCode; Total = [string]$r.Headers['X-WP-Total']; Pages = [string]$r.Headers['X-WP-TotalPages']; Json = ($r.Content | ConvertFrom-Json -NoEnumerate) }
}

$r = Get-Api '/shows'
Test-Step 'GET /shows returns 9 upcoming with total header' ($r.Status -eq 200 -and $r.Total -eq '9' -and $r.Json.Count -eq 9) "status=$($r.Status) total=$($r.Total)"
# Check the raw JSON: ConvertFrom-Json turns ISO strings into DateTime and drops the offset.
$raw = (Invoke-WebRequest "$api/shows?per_page=1" -UseBasicParsing).Content
Test-Step 'start dates carry the +04:00 offset' ($raw -match '"start":"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+04:00"') $raw.Substring(0, [Math]::Min(160, $raw.Length))

$r = Get-Api '/shows?per_page=2&page=5'
Test-Step 'pagination: per_page=2 -> 5 pages, last page has 1' ($r.Pages -eq '5' -and $r.Json.Count -eq 1) "pages=$($r.Pages) items=$($r.Json.Count)"

$r = Get-Api '/shows?city=dubai&genre=comedy'
Test-Step 'filter city+genre' ($r.Total -eq '2') "total=$($r.Total)"

$r = Get-Api '/shows?when=past'
Test-Step 'filter when=past' ($r.Total -eq '3') "total=$($r.Total)"

foreach ($bad in '/shows?per_page=100', '/shows?when=tomorrow', '/shows?from=01-12-2026', '/shows?city=Dubai%3Cx%3E') {
    Test-Step "rejects $bad with 400" ((Get-Api $bad).Status -eq 400)
}

$id = (Get-Api '/shows?per_page=1').Json[0].id
$r = Get-Api "/shows/$id"
Test-Step 'GET /shows/{id} returns the show' ($r.Status -eq 200 -and $r.Json.id -eq $id)
Test-Step 'GET /shows/999999 is 404' ((Get-Api '/shows/999999').Status -eq 404)

$page = (Invoke-WebRequest "$site/comedy-in-dubai/" -UseBasicParsing).Content
Test-Step '[encore_upcoming_shows city=dubai genre=comedy] renders 2 cards' (([regex]::Matches($page, 'data-list="shortcode_upcoming"')).Count -eq 2)
Test-Step '[encore_artist_shows artist=layla-haddad] renders 2 cards' (([regex]::Matches($page, 'data-list="shortcode_artist"')).Count -eq 2)

$page = (Invoke-WebRequest "$site/whats-on/" -UseBasicParsing).Content
Test-Step 'Elementor widget renders inside Elementor markup' ($page -match 'elementor-widget-encore-upcoming-shows' -and ([regex]::Matches($page, 'data-list="elementor_upcoming"')).Count -eq 2)

Write-Host ""
Write-Host "$pass passed, $fail failed"
if ($fail) { exit 1 }
