<#
.SYNOPSIS
  End-to-end waitlist test over HTTP + Mailpit. Needs: .\scripts\encore.ps1 seed --force

  Scenario on a sold-out show (General tier):
    A wants 4, B wants 2, C wants 1  -> join, confirm by email (FIFO = confirm order)
    restock 3 -> skip-and-continue notifies B and C, A keeps first place
    C claims via email link -> buys -> entry converted
    B unsubscribes via email link
    restock to 6 -> A notified; A's claim window forced into the past -> sweep expires it
  Plus: duplicate join, honeypot, validation, rate limit, admin list (masked for editors),
  CSV export, privacy export + erase, audit log.
#>
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$site = 'http://localhost:8080'
$pass = 0; $fail = 0
$run = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()

function Test-Step([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) { $script:pass++; Write-Host "PASS  $Name" -ForegroundColor Green }
    else { $script:fail++; Write-Host "FAIL  $Name  $Detail" -ForegroundColor Red }
}
function Invoke-Wp([string[]]$WpArgs) { ((docker compose run --rm -T wpcli wp @WpArgs 2>$null) | Select-Object -Last 1) }
function Invoke-WpEval([string]$Php) { ((docker compose run --rm -T wpcli wp eval $Php 2>$null) | Select-Object -Last 1) }
function Invoke-Jobs { docker compose run --rm -T wpcli wp action-scheduler run --group=encore-waitlist --batch-size=50 2>$null | Out-Null }
function Get-EntryStatus([string]$Email) { Invoke-WpEval "global `$wpdb; `$t = `$wpdb->prefix . 'encore_waitlist'; echo `$wpdb->get_var( `$wpdb->prepare( ""SELECT status FROM `$t WHERE email = %s"", '$Email' ) );" }
function Clear-RateLimit { Invoke-WpEval "global `$wpdb; `$wpdb->query( `"DELETE FROM {`$wpdb->options} WHERE option_name LIKE '_transient%encore_wl_rl_%'`" );" | Out-Null }

# Mailpit: newest message to an address whose subject matches; returns the plain text.
function Get-Mail([string]$To, [string]$Subject) {
    for ($i = 0; $i -lt 10; $i++) {
        $found = (Invoke-RestMethod "http://localhost:8025/api/v1/search?query=$([uri]::EscapeDataString("to:$To"))").messages | Where-Object { $_.Subject -match $Subject } | Select-Object -First 1
        if ($found) { return (Invoke-RestMethod "http://localhost:8025/api/v1/message/$($found.ID)").Text }
        Start-Sleep -Milliseconds 500
    }
    return ''
}
function Get-Link([string]$Text, [string]$Action) { [regex]::Match($Text, "https?://\S+encore_wl=$Action\S+").Value }

# Show page: nonce + General variation.
$showUrl = "$site/shows/omar-farouk-uncle-knows-best/"
$page = (Invoke-WebRequest $showUrl -UseBasicParsing).Content
$nonce = [regex]::Match($page, 'name="nonce" value="([^"]+)"').Groups[1].Value
$showId = [int][regex]::Match($page, 'name="show_id" value="(\d+)"').Groups[1].Value
$general = [int][regex]::Match($page, '<option value="(\d+)">General').Groups[1].Value
$product = Invoke-Wp @('post', 'meta', 'get', "$showId", '_encore_product_id')
Test-Step 'sold-out show renders the waitlist form' ($nonce -and $general)

function Join([string]$Email, [int]$Qty, [hashtable]$Extra = @{}) {
    $body = @{ show_id = $showId; variation_id = $general; name = 'Fan ' + $Email.Split('@')[0]; email = $Email; qty = $Qty; consent = $true; nonce = $nonce } + $Extra
    $r = Invoke-WebRequest "$site/wp-json/encore/v1/waitlist" -Method Post -ContentType 'application/json' -Body ($body | ConvertTo-Json) -UseBasicParsing -SkipHttpErrorCheck
    [pscustomobject]@{ Status = [int]$r.StatusCode; Json = ($r.Content | ConvertFrom-Json) }
}

$a = "fan-a-$run@encore.test"; $b = "fan-b-$run@encore.test"; $c = "fan-c-$run@encore.test"
Clear-RateLimit

# 1. Join + double opt-in.
foreach ($fan in @(@($a, 4), @($b, 2), @($c, 1))) {
    $r = Join $fan[0] $fan[1]
    Test-Step "join $($fan[0].Split('-')[1]) (qty $($fan[1])) -> pending" ($r.Status -eq 200 -and $r.Json.code -eq 'created' -and (Get-EntryStatus $fan[0]) -eq 'pending_confirmation') "$($r.Status) $($r.Json.code)"
}
foreach ($fan in @($a, $b, $c)) {
    $link = Get-Link (Get-Mail $fan 'Confirm your place') 'confirm'
    Invoke-WebRequest $link -UseBasicParsing | Out-Null
    Test-Step "confirm link -> waiting ($($fan.Split('-')[1]))" ((Get-EntryStatus $fan) -eq 'waiting') $link
}
$joined = Get-Mail $a 'on the list'
Test-Step "'you're on the list' email shows position 1 for A" ($joined -match 'number 1 in line')
$used = Invoke-WebRequest (Get-Link (Get-Mail $a 'Confirm your place') 'confirm') -UseBasicParsing
Test-Step 'confirm link is single-use' ($used.BaseResponse.RequestMessage.RequestUri.Query -match 'waitlist=already_confirmed|waitlist=bad_link')

# 2. Duplicate, honeypot, validation.
$r = Join $a 4
Test-Step 'duplicate join returns existing position, no new row' ($r.Json.code -eq 'already_waiting' -and $r.Json.position -eq 1)
$r = Join "bot-$run@encore.test" 1 @{ website = 'http://spam.example' }
Test-Step 'honeypot: fake success, nothing stored' ($r.Status -eq 200 -and -not (Get-EntryStatus "bot-$run@encore.test"))
$r = Join 'not-an-email' 2
Test-Step 'invalid input -> 400 with field errors' ($r.Status -eq 400 -and $r.Json.errors.email)
$r = Join "q-$run@encore.test" 9
Test-Step 'qty 9 rejected by the REST args schema' ($r.Status -eq 400 -and $r.Json.code -eq 'rest_invalid_param')
$r = Invoke-WebRequest "$site/wp-json/encore/v1/waitlist" -Method Post -ContentType 'application/json' -Body (@{ show_id = $showId; variation_id = $general; nonce = 'forged' } | ConvertTo-Json) -UseBasicParsing -SkipHttpErrorCheck
Test-Step 'forged nonce -> 403' ([int]$r.StatusCode -eq 403)

# 3. Restock 3: B (2) and C (1) notified, A (4) skipped but stays first.
Invoke-Wp @('wc', 'product_variation', 'update', $product, "$general", '--stock_quantity=3', '--user=admin') | Out-Null
Invoke-Jobs
Test-Step 'restock 3 -> B notified' ((Get-EntryStatus $b) -eq 'notified')
Test-Step 'restock 3 -> C notified' ((Get-EntryStatus $c) -eq 'notified')
Test-Step 'restock 3 -> A (wants 4) skipped, still waiting' ((Get-EntryStatus $a) -eq 'waiting')

# 4. C claims and buys.
$claim = Get-Link (Get-Mail $c 'Tickets are back') 'claim'
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
Invoke-WebRequest $claim -WebSession $s -UseBasicParsing | Out-Null
$cartResp = Invoke-WebRequest "$site/wp-json/wc/store/v1/cart" -WebSession $s -UseBasicParsing
$cart = $cartResp.Content | ConvertFrom-Json
Test-Step 'claim link puts 1 General ticket in the cart' ($cart.items_count -eq 1 -and $cart.items[0].id -eq $general) "items=$($cart.items_count)"
$tampered = $claim -replace 'sig=[a-f0-9]+', 'sig=deadbeef'
$t = Invoke-WebRequest $tampered -UseBasicParsing
Test-Step 'tampered claim link is rejected' ($t.BaseResponse.RequestMessage.RequestUri.Query -match 'waitlist=bad_link')
$billing = @{ first_name = 'Fan'; last_name = 'C'; email = $c; address_1 = 'Marina Walk'; city = 'Dubai'; country = 'AE'; postcode = '' }
$co = Invoke-WebRequest "$site/wp-json/wc/store/v1/checkout" -Method Post -WebSession $s -Headers @{ Nonce = [string]$cartResp.Headers['Nonce'] } -ContentType 'application/json' -Body (@{ billing_address = $billing; payment_method = 'cod'; additional_fields = @{ 'encore/attendee-phone' = '0501234567' } } | ConvertTo-Json -Depth 4) -UseBasicParsing -SkipHttpErrorCheck
Test-Step 'C checks out' ([int]$co.StatusCode -eq 200) $co.Content
Test-Step 'C converted after paying' ((Get-EntryStatus $c) -eq 'converted')

# 5. B unsubscribes.
Invoke-WebRequest (Get-Link (Get-Mail $b 'Tickets are back') 'unsubscribe') -UseBasicParsing | Out-Null
Test-Step 'one-click unsubscribe' ((Get-EntryStatus $b) -eq 'unsubscribed')

# 6. Restock to 6 -> A notified; force claim expiry -> sweep -> expired.
Invoke-Wp @('wc', 'product_variation', 'update', $product, "$general", '--stock_quantity=6', '--user=admin') | Out-Null
Invoke-Jobs
Test-Step 'restock 6 -> A notified' ((Get-EntryStatus $a) -eq 'notified')
Invoke-WpEval "global `$wpdb; `$wpdb->query( `$wpdb->prepare( `"UPDATE {`$wpdb->prefix}encore_waitlist SET claim_expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE email = %s`", '$a' ) ); do_action( 'encore_waitlist_sweep' );" | Out-Null
Test-Step 'lapsed claim window -> A expired by sweep' ((Get-EntryStatus $a) -eq 'expired')
$log = Invoke-WpEval "global `$wpdb; echo implode( ',', `$wpdb->get_col( `$wpdb->prepare( `"SELECT l.to_status FROM {`$wpdb->prefix}encore_waitlist_log l JOIN {`$wpdb->prefix}encore_waitlist e ON e.id = l.entry_id WHERE e.email = %s ORDER BY l.id`", '$a' ) ) );"
Test-Step 'audit log records every transition for A' ($log -eq 'pending_confirmation,waiting,notified,expired') $log

# 7. Admin screen + CSV (admin) and masking (editor).
function New-AdminSession([string]$User, [string]$Pass) {
    $sess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    Invoke-WebRequest "$site/wp-login.php" -WebSession $sess -UseBasicParsing | Out-Null
    Invoke-WebRequest "$site/wp-login.php" -Method Post -WebSession $sess -UseBasicParsing -Body @{ log = $User; pwd = $Pass; 'wp-submit' = 'Log In'; testcookie = '1' } | Out-Null
    $sess
}
$adminPass = (Select-String -Path .env -Pattern '^WP_ADMIN_PASSWORD=(.*)$').Matches[0].Groups[1].Value
$admin = New-AdminSession 'admin' $adminPass
$list = (Invoke-WebRequest "$site/wp-admin/admin.php?page=encore-waitlists&show_id=$showId" -WebSession $admin -UseBasicParsing).Content
Test-Step 'admin list shows full emails and per-show summary' ($list -match [regex]::Escape($a) -and $list -match 'By show' -and $list -match 'Conversion rate')
$export = [System.Net.WebUtility]::HtmlDecode([regex]::Match($list, 'href="([^"]+action=encore_waitlist_export[^"]*)"').Groups[1].Value)
$csv = Invoke-WebRequest $export -WebSession $admin -UseBasicParsing
Test-Step 'CSV export streams the filtered rows' ($csv.Headers['Content-Type'] -match 'text/csv' -and ([string]$csv.Content -match [regex]::Escape($c))) ''
$noNonce = Invoke-WebRequest "$site/wp-admin/admin-post.php?action=encore_waitlist_export" -WebSession $admin -UseBasicParsing -SkipHttpErrorCheck
Test-Step 'CSV export without nonce is refused' ([int]$noNonce.StatusCode -ge 400 -or $noNonce.Content -notmatch [regex]::Escape($c))
Invoke-Wp @('user', 'create', "editor$run", "editor$run@encore.test", '--role=editor', "--user_pass=Editor-$run-pass") | Out-Null
$editor = New-AdminSession "editor$run" "Editor-$run-pass"
$elist = (Invoke-WebRequest "$site/wp-admin/admin.php?page=encore-waitlists&show_id=$showId" -WebSession $editor -UseBasicParsing).Content
Test-Step 'editor sees the list with masked emails and no row actions' ($elist -match 'f\*+@e\*+\.test' -and $elist -notmatch [regex]::Escape($a) -and $elist -notmatch 'Notify now')
Invoke-Wp @('user', 'delete', "editor$run", '--yes') | Out-Null

# 8. Privacy exporter + eraser.
$exported = Invoke-WpEval "`$e = apply_filters( 'wp_privacy_personal_data_exporters', array() ); `$r = call_user_func( `$e['encore-waitlist']['callback'], '$b', 1 ); echo count( `$r['data'] );"
Test-Step 'privacy exporter returns B''s entry' ($exported -eq '1') $exported
$erased = Invoke-WpEval "`$e = apply_filters( 'wp_privacy_personal_data_erasers', array() ); `$r = call_user_func( `$e['encore-waitlist']['callback'], '$b', 1 ); echo `$r['items_removed'] ? 'removed' : 'none';"
Test-Step 'privacy eraser deletes B''s entry' ($erased -eq 'removed' -and -not (Get-EntryStatus $b))

# 9. Rate limit: 5 attempts per 10 minutes per IP.
Clear-RateLimit
$codes = 1..6 | ForEach-Object { (Join "rl-$_-$run@encore.test" 1).Status }
Test-Step '6th attempt in the window gets 429' ($codes[5] -eq 429 -and ($codes[0..4] | Where-Object { $_ -eq 429 }).Count -eq 0) ($codes -join ',')
Clear-RateLimit

Write-Host ""
Write-Host "$pass passed, $fail failed"
if ($fail) { exit 1 }
