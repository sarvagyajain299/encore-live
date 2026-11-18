# Analytics: GA4 via Google Tag Manager

**Nothing is hard-coded.** Paste a container ID (`GTM-XXXXXXX`) in **Encore → Settings → Analytics**. With the field empty, neither GTM nor any dataLayer code is printed. The ID is validated (`^GTM-[A-Z0-9]{4,10}$`).

> Status: no real GTM container or GA4 property is connected; that needs an account. Everything below was verified against the **dataLayer** with a dummy ID (`GTM-TEST123`) and requests to Google blocked, which is exactly what GTM would read.

## 1. Events pushed

GA4 recommended e-commerce events, each preceded by `dataLayer.push({ ecommerce: null })` as Google recommends.

| Event | Where | Pushed by |
|---|---|---|
| `view_item_list` | any page with show cards (archive, home, artist, venue, shortcodes, Elementor) | `analytics.js`, one per `data-list` |
| `select_item` | click on a show card | `analytics.js` |
| `view_item` | single show page, one item per tier | server, `wp_footer` |
| `add_to_cart` | page view **after** adding (cart page) | server: queued in the WooCommerce session on `woocommerce_add_to_cart` |
| `begin_checkout` | checkout page | server |
| `purchase` | order-received page, **once per order** | server; guarded by order meta `_encore_ga_purchase_tracked` and the order key |

### Item shape (`items[]`)
```json
{
  "item_id": "1655",
  "item_name": "Nova Saleh: Neon Mirage Tour",
  "item_brand": "Encore Live",
  "item_category": "Live Music",
  "item_category2": "Abu Dhabi",
  "item_variant": "General",
  "price": 150,
  "quantity": 2,
  "item_list_name": "shows_archive",
  "index": 0
}
```
- `item_id` is the tier variation ID. In listings, where no tier is chosen, it is `show-<id>`.
- `price` and `quantity` are numbers, not strings.
- `ecommerce` also carries `currency: "AED"` and `value` (Σ price × qty). `purchase` adds `transaction_id`, `tax` and `shipping`, and its `value` is the order total.
- List names: `shows_archive`, `home_upcoming`, `artist_upcoming`, `venue_upcoming`, `shortcode_upcoming`, `shortcode_artist`, `elementor_upcoming`.

### Why add_to_cart is server-side and deferred
The tier form posts to the cart, and WooCommerce redirects (Post/Redirect/Get). A push during the POST would never reach a browser, and a JS push on submit can be lost when the page unloads. So the event is stored in the WooCommerce session and printed on the next page view, then cleared, so it can't repeat on refresh.

### Why purchase is guarded twice
- **Order meta flag:** a refresh, back button or revisit of the thank-you page would otherwise count the revenue again.
- **Order key check:** the order-received URL carries `?key=wc_order_…`. Without the check, anyone could change the order ID and read another customer's totals.

## 2. GTM setup to create

**Variables** (Data Layer Variable, version 2):
- `DLV - ecommerce` → `ecommerce`
- **GA4 Measurement ID**: Constant, `G-XXXXXXXXXX`

**Tags:**
1. **GA4 Configuration / Google tag.** Tag ID = Measurement ID. Trigger: *Initialization - All Pages*.
2. **GA4 Event – ecommerce.** Event name `{{Event}}`; check *"Send Ecommerce data"* with data source *Data Layer*. Trigger: **Custom Event** with regex `^(view_item_list|select_item|view_item|add_to_cart|begin_checkout|purchase)$` (*use regex matching*).

**Consent:** before launch, add Consent Mode v2 defaults (e.g. a CMP template) so tags respect `analytics_storage` and `ad_storage` choices.

## 3. How to verify

1. **GTM Preview (Tag Assistant).** Open the site through Preview and walk the journey: `/shows/` → click a card → choose a tier → add to cart → checkout → place a Cash on Delivery order. Each step shows the event in the left column. Open it to see the `ecommerce` object and confirm the GA4 Event tag fired.
2. **GA4 DebugView** (Admin → DebugView). The same events appear within seconds while Preview mode is on. Click `purchase` to check `transaction_id`, `value`, `currency` and the items.
3. **Refresh the thank-you page.** No second `purchase`.
4. **Reports:** GA4 → Reports → Monetisation → E-commerce purchases (after the usual 24–48 h processing delay).

## 4. Automated check

`tests/e2e/analytics-check.mjs` (Puppeteer + local Chrome) drives the real journey and inspects `window.dataLayer`:

```powershell
cd tests\e2e; npm install; cd ..\..        # one-time
.\scripts\encore.ps1 seed --force
.\scripts\encore.ps1 wp eval "update_option('encore_settings', array('max_tickets_per_order'=>6,'gtm_container_id'=>'GTM-TEST123'));"
cd tests\e2e; npm run analytics
```
(`.\scripts\qa.ps1` does all of this and resets the ID afterwards.)
Result on 2026-11-18: **11 passed, 0 failed**.

The checks: view_item_list with 9 items and correct fields, select_item, `ecommerce:null` clearing, view_item with 3 tiers, add_to_cart (qty 2, value 300) and not repeated on refresh, begin_checkout, purchase (value 300) not repeated on refresh, and no purchase with a forged order key.

**Bug this test found:** without Post/Redirect/Get after add-to-cart, refreshing the cart page re-submitted the form and doubled the tickets. Fixed by `woocommerce_cart_redirect_after_add = yes` in `install.sh`.
