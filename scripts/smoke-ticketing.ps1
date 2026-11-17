<#
.SYNOPSIS
  End-to-end ticketing smoke test over HTTP, using the WooCommerce Store API
  (the same API the Cart and Checkout blocks call).

  Covers: per-order limit, cancelled-show block, invalid/valid attendee phone,
  order item meta, and stock -> sold_out -> refund -> on_sale status sync.

  Needs seeded demo data: .\scripts\encore.ps1 seed --force
  Run from the repo root: .\scripts\smoke-ticketing.ps1
#>
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
$base = 'http://localhost:8080/wp-json/wc/store/v1'
$pass = 0; $fail = 0

function Test-Step([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) { $script:pass++; Write-Host "PASS  $Name" -ForegroundColor Green }
    else { $script:fail++; Write-Host "FAIL  $Name  $Detail" -ForegroundColor Red }
}

function Invoke-Wp([string[]]$WpArgs) {
    (docker compose run --rm -T wpcli wp @WpArgs 2>$null | Select-Object -Last 1).Trim()
}

function Invoke-Store([string]$Method, [string]$Path, $Body = $null) {
    $params = @{ Method = $Method; Uri = "$base$Path"; WebSession = $script:session; Headers = @{ Nonce = $script:nonce }; ContentType = 'application/json'; SkipHttpErrorCheck = $true }
    if ($Body) { $params.Body = ($Body | ConvertTo-Json -Depth 6) }
    $r = Invoke-WebRequest @params
    if ($r.Headers['Nonce']) { $script:nonce = [string]$r.Headers['Nonce'] }
    [pscustomobject]@{ Status = [int]$r.StatusCode; Json = ($r.Content | ConvertFrom-Json) }
}

function New-Cart {
    $script:session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $script:nonce = ''
    Invoke-Store GET '/cart' | Out-Null
}

function Get-Variation([string]$ShowSlug, [string]$Tier) {
    $show = Invoke-Wp @('post', 'list', '--post_type=encore_show', "--name=$ShowSlug", '--field=ID')
    $product = Invoke-Wp @('post', 'meta', 'get', $show, '_encore_product_id')
    $json = docker compose run --rm -T wpcli wp wc product_variation list $product --user=admin --format=json 2>$null | Select-Object -Last 1 | ConvertFrom-Json
    $v = $json | Where-Object { $_.attributes[0].option -eq $Tier }
    [pscustomobject]@{ Show = $show; Product = $product; Id = $v.id; Stock = $v.stock_quantity; All = $json }
}

$billing = @{ first_name = 'Test'; last_name = 'Fan'; email = 'fan@encore.test'; phone = '0501234567'; address_1 = 'Marina Walk'; city = 'Dubai'; country = 'AE'; postcode = '' }

# 1. Per-order limit (default 6 per show).
$layla = Get-Variation 'layla-haddad-brunch-is-a-personality' 'General'
New-Cart
$r = Invoke-Store POST '/cart/add-item' @{ id = $layla.Id; quantity = 4 }
Test-Step 'add 4 General tickets' ($r.Status -eq 201) "status $($r.Status)"
$r = Invoke-Store POST '/cart/add-item' @{ id = $layla.Id; quantity = 3 }
Test-Step 'adding 3 more (7 > 6) is rejected' ($r.Status -ge 400 -and $r.Json.message -match 'up to 6') "status $($r.Status): $($r.Json.message)"

# 2. Cancelled show cannot be bought.
$priya = Get-Variation 'priya-raman-overqualified' 'General'
$r = Invoke-Store POST '/cart/add-item' @{ id = $priya.Id; quantity = 1 }
Test-Step 'cancelled show is rejected' ($r.Status -ge 400 -and $r.Json.message -match 'cancelled') "status $($r.Status): $($r.Json.message)"

# 3. Checkout: invalid phone rejected, valid phone accepted and normalised.
$r = Invoke-Store POST '/checkout' @{ billing_address = $billing; payment_method = 'cod'; additional_fields = @{ 'encore/attendee-phone' = '12345' } }
Test-Step 'invalid attendee phone is rejected' ($r.Status -ge 400) "status $($r.Status): $($r.Json.message)"
$r = Invoke-Store POST '/checkout' @{ billing_address = $billing; payment_method = 'cod'; additional_fields = @{ 'encore/attendee-phone' = '050 765 4321' } }
$order = $r.Json.order_id
Test-Step 'checkout with valid phone creates an order' ($r.Status -eq 200 -and $order) "status $($r.Status): $($r.Json.message)"
$phone = Invoke-Wp @('wc', 'shop_order', 'get', "$order", '--user=admin', '--field=meta_data', '--format=json')
Test-Step 'phone saved to order meta in +971 form' ($phone -match '\+971507654321') $phone
$items = Invoke-Wp @('wc', 'shop_order', 'get', "$order", '--user=admin', '--field=line_items', '--format=json')
Test-Step 'order line has Show date, Venue and Tier' ($items -match 'Show date' -and $items -match 'The Lantern Room' -and $items -match 'General') ''

# 4. Sold-out sync: leave 2 General, 0 Premium, 0 VIP, then buy the last 2.
foreach ($v in $layla.All) {
    $qty = if ($v.id -eq $layla.Id) { 2 } else { 0 }
    docker compose run --rm -T wpcli wp wc product_variation update $layla.Product $v.id --stock_quantity=$qty --user=admin 2>$null | Out-Null
}
Test-Step 'show still on_sale with 2 left' ((Invoke-Wp @('post', 'meta', 'get', $layla.Show, '_encore_status')) -eq 'on_sale')
New-Cart
Invoke-Store POST '/cart/add-item' @{ id = $layla.Id; quantity = 2 } | Out-Null
$r = Invoke-Store POST '/checkout' @{ billing_address = $billing; payment_method = 'cod'; additional_fields = @{ 'encore/attendee-phone' = '0501112222' } }
$last = $r.Json.order_id
Test-Step 'buying the last 2 tickets succeeds' ($r.Status -eq 200) "status $($r.Status): $($r.Json.message)"
Test-Step 'show flips to sold_out' ((Invoke-Wp @('post', 'meta', 'get', $layla.Show, '_encore_status')) -eq 'sold_out')

# 5. Refund with restock flips it back.
$lineId = (Invoke-Wp @('wc', 'shop_order', 'get', "$last", '--user=admin', '--field=line_items', '--format=json') | ConvertFrom-Json)[0].id
# wc_create_refund() is what the admin "Refund" button calls; restock_items puts the ticket back.
$php = "wc_create_refund( array( 'order_id' => $last, 'amount' => 95, 'restock_items' => true, 'line_items' => array( $lineId => array( 'qty' => 1, 'refund_total' => 95 ) ) ) );"
docker compose run --rm -T wpcli wp eval $php 2>$null | Out-Null
Test-Step 'refund of 1 ticket restocks to 1' ((Invoke-Wp @('wc', 'product_variation', 'get', $layla.Product, "$($layla.Id)", '--user=admin', '--field=stock_quantity')) -eq '1')
Test-Step 'show flips back to on_sale' ((Invoke-Wp @('post', 'meta', 'get', $layla.Show, '_encore_status')) -eq 'on_sale')

Write-Host ""
Write-Host "$pass passed, $fail failed"
if ($fail) { exit 1 }
