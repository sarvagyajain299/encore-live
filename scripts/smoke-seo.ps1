<#
.SYNOPSIS
  Phase 6 checks: Event JSON-LD in Yoast's graph, cancelled status, archived 301,
  sitemap exclusion, noindex/canonical rules, archive cron job.
  Needs: .\scripts\encore.ps1 install; .\scripts\encore.ps1 seed --force
#>
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$site = 'http://localhost:8080'
$pass = 0; $fail = 0
function Test-Step([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) { $script:pass++; Write-Host "PASS  $Name" -ForegroundColor Green }
    else { $script:fail++; Write-Host "FAIL  $Name  $Detail" -ForegroundColor Red }
}
# Raw HttpClient: PowerShell throws on 3xx when redirects are disabled, and we need to SEE the 301.
$handler = [System.Net.Http.HttpClientHandler]::new(); $handler.AllowAutoRedirect = $false
$http = [System.Net.Http.HttpClient]::new($handler)
function Get-Page([string]$Path) {
    $r = $http.GetAsync("$site$Path").Result
    [pscustomobject]@{ StatusCode = [int]$r.StatusCode; Location = [string]$r.Headers.Location; Content = $r.Content.ReadAsStringAsync().Result }
}
function Get-Graph([string]$Html) {
    $json = [regex]::Match($Html, '(?s)<script type="application/ld\+json" class="yoast-schema-graph">(.*?)</script>').Groups[1].Value
    if (-not $json) { return $null }
    ($json | ConvertFrom-Json -DateKind String).'@graph'
}

# Local installs are 'discourage search engines' (blog_public=0), which makes core print
# noindex everywhere and Yoast skip its own robots/canonical tags. Test as production would run.
docker compose run --rm -T wpcli wp option update blog_public 1 2>$null | Out-Null
# Option changes don't purge the page cache; drop pages cached while the site was noindex.
docker compose run --rm -T wpcli wp eval 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' 2>$null | Out-Null

# Event in Yoast's single graph.
$html = (Get-Page '/shows/layla-haddad-brunch-is-a-personality/').Content
$graph = Get-Graph $html
$event = $graph | Where-Object { $_.'@type' -in @('ComedyEvent', 'MusicEvent', 'Event') }
Test-Step 'one JSON-LD graph on the page (no duplicate script)' (([regex]::Matches($html, 'application/ld\+json')).Count -eq 1)
Test-Step 'Event node present in Yoast graph as ComedyEvent' ($event -and $event.'@type' -eq 'ComedyEvent')
Test-Step 'required: name, startDate with +04:00, location address' ($event.name -and $event.startDate -match '\+04:00$' -and $event.location.address.addressLocality -eq 'Dubai') "$($event.startDate)"
Test-Step 'recommended: endDate, eventStatus, attendance mode, image, description, performer' ($event.endDate -and $event.eventStatus -match 'EventScheduled' -and $event.eventAttendanceMode -match 'Offline' -and $event.image -and $event.description -and $event.performer)
Test-Step 'offers: 3 tiers in AED with availability and validFrom' ($event.offers.Count -eq 3 -and ($event.offers | Where-Object { $_.priceCurrency -ne 'AED' -or -not $_.availability -or -not $_.validFrom }).Count -eq 0)
$org = $graph | Where-Object { $_.'@type' -eq 'Organization' }
$page = $graph | Where-Object { $_.'@type' -match 'WebPage' }
$site_node = $graph | Where-Object { $_.'@type' -eq 'WebSite' }
Test-Step 'organizer uses Yoast''s Organization @id; mainEntityOfPage links the WebPage node' ($event.organizer.'@id' -eq (($site_node.'@id') -replace '#website', '#organization') -and $event.mainEntityOfPage.'@id' -eq $page.'@id')
Test-Step 'BreadcrumbList in the graph' (($graph | Where-Object { $_.'@type' -eq 'BreadcrumbList' }) -ne $null)
Test-Step 'title template applied' ($html -match '<title>Layla Haddad: Brunch Is a Personality Tickets')
New-Item -ItemType Directory -Force docs/seo | Out-Null
($event | ConvertTo-Json -Depth 8) | Set-Content docs/seo/event-jsonld-sample.json

$cancel = Get-Graph (Get-Page '/shows/priya-raman-overqualified/').Content | Where-Object { $_.'@type' -match 'Event' }
Test-Step 'cancelled show: EventCancelled and no offers' ($cancel.eventStatus -match 'EventCancelled' -and -not $cancel.offers)

# Archived shows: 301 to headliner, out of sitemap.
$r = Get-Page '/shows/saffron-skies-monsoon-sessions/'
Test-Step 'archived show returns 301 to the artist page' ($r.StatusCode -eq 301 -and $r.Location -match '/artists/saffron-skies/') "$($r.StatusCode) $($r.Location)"
$sitemap = (Get-Page '/encore_show-sitemap.xml').Content
Test-Step 'show sitemap lists upcoming shows' ($sitemap -match 'layla-haddad-brunch-is-a-personality')
Test-Step 'show sitemap excludes archived shows' ($sitemap -notmatch 'saffron-skies-monsoon-sessions' -and $sitemap -notmatch 'oud-and-synths')
$index = (Get-Page '/sitemap_index.xml').Content
Test-Step 'sitemap index has shows, artists, venues and no products' ($index -match 'encore_show-sitemap' -and $index -match 'encore_artist-sitemap' -and $index -match 'encore_venue-sitemap' -and $index -notmatch 'product-sitemap')

# Robots and canonicals.
$f = (Get-Page '/shows/?show_city=dubai').Content
Test-Step 'filtered archive: noindex, follow' ($f -match '<meta name=.robots. content=.noindex, follow')
Test-Step 'filtered archive: no conflicting canonical alongside noindex' ($f -notmatch '<link rel="canonical"')
foreach ($p in '/cart/', '/checkout/', '/my-account/') {
    $r = Get-Page $p
    # An empty-cart checkout redirects to the cart, which is also fine.
    Test-Step "$p is noindex" ($r.Content -match '<meta name=.robots. content=.[^"'']*noindex' -or ($p -eq '/checkout/' -and $r.StatusCode -eq 302 -and $r.Location -match '/cart/')) "$($r.StatusCode)"
}
$public = Get-Page '/shows/'
Test-Step 'unfiltered /shows/ is indexable with a self canonical' ($public.Content -notmatch 'noindex' -and $public.Content -match '<link rel="canonical" href="http://localhost:8080/shows/"')

# Archive job: re-date an upcoming show into the past and run the cron hook.
$id = ((docker compose run --rm -T wpcli wp post list --post_type=encore_show --name=marco-bellini-mamma-mia-dubai --field=ID 2>$null) | Select-Object -Last 1).Trim()
docker compose run --rm -T wpcli wp post meta update $id _encore_end '2026-01-01 22:00:00' 2>$null | Out-Null
docker compose run --rm -T wpcli wp cron event run encore_archive_past_shows 2>$null | Out-Null
$status = ((docker compose run --rm -T wpcli wp post meta get $id _encore_status 2>$null) | Select-Object -Last 1).Trim()
Test-Step 'daily archive job archives a show that has ended' ($status -eq 'archived') $status
$next = ((docker compose run --rm -T wpcli wp cron event list --hook=encore_archive_past_shows --field=recurrence 2>$null) | Select-Object -Last 1).Trim()
Test-Step 'archive job is scheduled daily' ($next -match '1 day') $next

docker compose run --rm -T wpcli wp option update blog_public 0 2>$null | Out-Null
docker compose run --rm -T wpcli wp eval 'if ( function_exists( "wp_cache_clear_cache" ) ) { wp_cache_clear_cache(); }' 2>$null | Out-Null
Write-Host ""
Write-Host "$pass passed, $fail failed"
if ($fail) { exit 1 }
