<#
.SYNOPSIS
  Phase 8 hardening checks over HTTP.
#>
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$site = 'http://localhost:8080'
$pass = 0; $fail = 0
function Test-Step([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) { $script:pass++; Write-Host "PASS  $Name" -ForegroundColor Green }
    else { $script:fail++; Write-Host "FAIL  $Name  $Detail" -ForegroundColor Red }
}
$handler = [System.Net.Http.HttpClientHandler]::new(); $handler.AllowAutoRedirect = $false
$http = [System.Net.Http.HttpClient]::new($handler)
function Get-Raw([string]$Path, [string]$Method = 'GET') {
    $req = [System.Net.Http.HttpRequestMessage]::new([System.Net.Http.HttpMethod]::new($Method), "$site$Path")
    $r = $http.SendAsync($req).Result
    $h = @{}; foreach ($kv in $r.Headers) { $h[$kv.Key] = ($kv.Value -join ',') }; foreach ($kv in $r.Content.Headers) { $h[$kv.Key] = ($kv.Value -join ',') }
    [pscustomobject]@{ Status = [int]$r.StatusCode; Headers = $h; Location = [string]$r.Headers.Location; Body = $r.Content.ReadAsStringAsync().Result }
}

# Headers on a dynamic page and on a page-cache hit (served before WordPress loads).
Get-Raw '/shows/' | Out-Null
foreach ($case in @(@('/shows/', 'cached page'), @('/wp-login.php', 'dynamic page'), @('/wp-content/themes/encore-theme/assets/css/main.css', 'static asset'))) {
    $r = Get-Raw $case[0]
    $ok = $r.Headers['X-Content-Type-Options'] -eq 'nosniff' -and $r.Headers['X-Frame-Options'] -eq 'SAMEORIGIN' -and $r.Headers['Referrer-Policy'] -and $r.Headers['Permissions-Policy']
    Test-Step "security headers on $($case[1])" $ok (($r.Headers.Keys | Sort-Object) -join ',')
}
$r = Get-Raw '/'
Test-Step 'no X-Powered-By (PHP version) header' (-not $r.Headers.ContainsKey('X-Powered-By'))
Test-Step 'Server header does not reveal versions' ($r.Headers['Server'] -notmatch '\d')
Test-Step 'no WordPress generator meta tag' ($r.Body -notmatch 'name="generator" content="WordPress')

Test-Step 'xmlrpc.php refused (403)' ((Get-Raw '/xmlrpc.php' 'POST').Status -eq 403)
$a = Get-Raw '/?author=1'
Test-Step '?author=1 does not reveal a login (redirects home)' ($a.Status -eq 301 -and $a.Location -eq "$site/") "$($a.Status) $($a.Location)"
Test-Step '/wp-json/wp/v2/users refused for visitors' ((Get-Raw '/wp-json/wp/v2/users').Status -eq 401)
Test-Step 'debug.log not downloadable' ((Get-Raw '/wp-content/debug.log').Status -eq 403)
Test-Step 'dotfiles not downloadable' ((Get-Raw '/wp-content/cache/.htaccess').Status -eq 403)

$edit = ((docker compose run --rm -T wpcli wp eval "echo ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ? 'on' : 'off';" 2>$null) | Select-Object -Last 1)
Test-Step 'DISALLOW_FILE_EDIT is on' ($edit -eq 'on') $edit

Write-Host ""
Write-Host "$pass passed, $fail failed"
if ($fail) { exit 1 }
