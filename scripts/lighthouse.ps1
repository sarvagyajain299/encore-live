<#
.SYNOPSIS
  Lighthouse (mobile, default simulated throttling) on the home page, shows
  archive and one show page. 3 runs per page; the MEDIAN run (by performance
  score) is saved as JSON + HTML to docs/perf/<Label>/, and a summary table
  is printed.

.EXAMPLE
  .\scripts\lighthouse.ps1 -Label before -Lighthouse "C:\path\to\node_modules\.bin\lighthouse.cmd"
#>
param(
    [Parameter(Mandatory)] [string]$Label,
    [string]$Lighthouse = 'lighthouse',
    [int]$Runs = 3
)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$out = "docs/perf/$Label"
New-Item -ItemType Directory -Force $out | Out-Null
$tmp = Join-Path ([IO.Path]::GetTempPath()) "encore-lh-$Label"
New-Item -ItemType Directory -Force $tmp | Out-Null

$pages = [ordered]@{
    home    = 'http://localhost:8080/'
    archive = 'http://localhost:8080/shows/'
    show    = 'http://localhost:8080/shows/nova-saleh-neon-mirage-tour/'
}

# Audit as production would serve it: indexable (otherwise SEO is capped by noindex).
docker compose run --rm -T wpcli wp option update blog_public 1 2>$null | Out-Null
# Option changes don't purge the page cache; drop pages cached while the site was noindex.
docker compose run --rm -T wpcli wp eval 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' 2>$null | Out-Null

$rows = @()
foreach ($name in $pages.Keys) {
    # Warm-up request so the first run isn't penalised for cold PHP opcache.
    Invoke-WebRequest $pages[$name] -UseBasicParsing | Out-Null
    $results = @()
    for ($i = 1; $i -le $Runs; $i++) {
        $base = Join-Path $tmp "$name-$i"
        & $Lighthouse $pages[$name] --quiet --form-factor=mobile --screenEmulation.mobile `
            --only-categories=performance,accessibility,best-practices,seo `
            --output=json --output=html --output-path=$base --chrome-flags="--headless=new" 2>$null | Out-Null
        $json = Get-Content "$base.report.json" -Raw | ConvertFrom-Json
        $results += [pscustomobject]@{ Base = $base; Score = $json.categories.performance.score; Json = $json }
    }
    $median = ($results | Sort-Object Score)[[int][Math]::Floor($Runs / 2)]
    Copy-Item "$($median.Base).report.json" "$out/$name.report.json" -Force
    Copy-Item "$($median.Base).report.html" "$out/$name.report.html" -Force
    $j = $median.Json
    $rows += [pscustomobject]@{
        Page          = $name
        Performance   = [int]($j.categories.performance.score * 100)
        Accessibility = [int]($j.categories.accessibility.score * 100)
        BestPractices = [int]($j.categories.'best-practices'.score * 100)
        SEO           = [int]($j.categories.seo.score * 100)
        LCP           = $j.audits.'largest-contentful-paint'.displayValue
        CLS           = $j.audits.'cumulative-layout-shift'.displayValue
        TBT           = $j.audits.'total-blocking-time'.displayValue
        FCP           = $j.audits.'first-contentful-paint'.displayValue
        TTFB          = $j.audits.'server-response-time'.displayValue
        Runs          = ($results.Score | ForEach-Object { [int]($_ * 100) }) -join '/'
    }
}

docker compose run --rm -T wpcli wp option update blog_public 0 2>$null | Out-Null
docker compose run --rm -T wpcli wp eval 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' 2>$null | Out-Null
$rows | Format-Table -AutoSize | Out-String -Width 200
$rows | ConvertTo-Json | Set-Content "$out/summary.json"
