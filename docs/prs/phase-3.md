# PR: Phase 3, WooCommerce ticketing

**Branch:** `feature/phase-3-ticketing` → `main`

## Summary
Fans can pick a tier and quantity on the show page and buy through WooCommerce. Purchases are limited per order and blocked for cancelled, ended or sold-out shows. Checkout collects a validated UAE attendee phone. Show date, venue and tier appear on cart, checkout, emails and My Account. Show status follows stock automatically, including after refunds.

## Changes
- **`src/Tickets/`:**
  - `TicketPanel`, `CartValidation`, `PurchasePolicy` (pure), `TicketLookup`
  - `OrderDetails`, `AttendeePhoneField`, `ShowStatusSync`
- **Other plugin code:**
  - `src/Shows/ShowStatusUpdater.php`: shared recalculate-save-announce. Fires `encore_show_status_changed`.
  - `src/Support/PhoneNumber.php` (pure), `src/Support/Settings.php`
  - `src/Admin/SettingsPage.php`: Encore → Settings (Settings API).
  - `assets/js/tickets.js`: quantity list follows the chosen tier.
- **Theme:** tier selector styles.
- **Install:** enables Cash on Delivery (incl. virtual) and Check payments.
- **`scripts/smoke-ticketing.ps1`:** E2E test over the Store API.
- **Tests:** `PhoneNumberTest` (16 cases), `PurchasePolicyTest` (11), `SettingsTest` (5).

## Verification (2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (80 tests, 130 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| E2E | `.\scripts\smoke-ticketing.ps1` | 12 passed, 0 failed |
| No-JS purchase form | POST show-page form → `/cart/` | item added with Show date + Venue |
| Order email | Mailpit | tier, date, venue and `+971…` attendee phone present |
| Accessibility | Lighthouse (mobile), show page | 100 |

## Test steps
1. `.\scripts\encore.ps1 install`, `.\scripts\encore.ps1 seed --force`
2. Open any upcoming show. Choose VIP and 2 tickets, then Add to cart. The cart shows Tier, Show date and Venue.
3. Try a quantity of 6 more for the same show. You get a "You can buy up to 6…" error.
4. Checkout: enter `12345` as the attendee phone (rejected), then `050 123 4567`. Pay with Cash on delivery.
5. Mailpit (http://localhost:8025): the order email shows the ticket details and phone.
6. Run `.\scripts\smoke-ticketing.ps1` (reseed first).

## Screenshots to take
- Tier selector on a show page (with an "Only N left" tier, if stock is low)
- Cart with ticket details
- Checkout with the attendee field and its validation error
- The order email in Mailpit
- Admin order screen showing the attendee phone and item meta

## Risks
- **No seat holds.** Two fans can both reach checkout for the last ticket. WooCommerce's stock check at order creation means only one succeeds, and the other sees "not enough stock". Real holds are a Phase 5 talking point.
- **Per-order limit only.** A buyer can place several orders. A per-customer limit would need order-history counting.
- **Reduced checkout fields not configured.** Address fields are still shown although tickets are virtual. A later improvement is to hide them for virtual-only carts.

## TODOs for Sarvagya
- [ ] `TicketPanel`: make the low-stock threshold filterable (`encore_low_stock_threshold`)
- [ ] `Settings::sanitize()`: show an admin error when the max is out of range
