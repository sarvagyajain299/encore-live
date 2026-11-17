# PR: Phase 1, plugin skeleton and content model

**Branch:** `feature/phase-1-content-model` → `main`

## Summary
Turns `encore-core` into a namespaced, PSR-4, service-based plugin. Registers shows, artists, venues, genres and cities with typed, sanitized, REST-exposed meta, and native meta boxes. Adds admin list columns and `wp encore seed` / `reset` with linked WooCommerce ticket products.

## Changes
- `encore-core.php` bootstrap. `src/Plugin.php` registers the services. `src/Lifecycle.php` handles activation (rewrite flush).
- `src/Content/PostTypes.php`, `src/Content/Taxonomies.php`
- `src/Meta/{Keys,Fields,MetaRegistry}.php`. `src/Admin/MetaBox.php` provides a generic nonce-, capability- and sanitize-protected meta box.
- `src/Admin/ShowListColumns.php`: Show date (sortable, default order), Venue, Status.
- `src/Domain/{ShowStatus,Tier,ShowStatusCalculator}.php`, `src/Support/{Sanitizer,Dates}.php`
- `src/Tickets/TicketProductFactory.php`: a variable product with a Tier attribute. Stock = capacity.
- `src/Seed/*`, `src/Cli/*`: `wp encore seed [--force]`, `wp encore reset`, generated placeholder art.
- Tooling: plugin `composer.json` (PSR-4). `install` now runs `composer install` for the plugin. The root autoload-dev maps `EncoreCore\` for tests. PHPCS allows PSR-4 file names in `src/`.
- Tests: `SanitizerTest`, `ShowStatusCalculatorTest`, `DatesTest`.

## Verification (2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (34 tests, 60 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 23 files, 0 errors, 0 warnings |
| Seed | `.\scripts\encore.ps1 seed` | 8 artists, 4 venues, 12 shows (8 on_sale, 1 sold_out, 1 cancelled, 3 archived) |
| Ticket stock | `wp wc product_variation list <id>` | the 250-seat venue splits 150/75/25; the sold-out show has 0/0/0 |
| Reset | `.\scripts\encore.ps1 reset` | removed 56 items. Only WooCommerce's own placeholder image remains. |
| REST | `GET /wp-json/wp/v2/encore_show` | `meta` object present and typed (`_encore_artist_ids: [18,16,12]`) |
| Meta box sanitizing | logged-in POST with invalid values | invalid date/time/age/status/venue all coerced to safe defaults |
| Forged nonce | the same POST, `encore_meta_nonce=forged` | meta unchanged |
| URLs | `/shows/`, `/artists/`, `/venues/`, `/genre/comedy/`, `/city/dubai/`, single show | all 200 |

## Test steps
1. `.\scripts\encore.ps1 install` then `.\scripts\encore.ps1 seed`
2. wp-admin → **Shows**: check the Show date / Venue / Status columns. Click "Show date" to sort.
3. Edit a show: fill in the meta box, save, reload. Values persist.
4. **Products** → (hidden from catalogue) open a "– Tickets" product → Variations: 3 tiers with stock.

## Screenshots to take
- The Shows list with custom columns
- The Show details meta box
- A ticket product's Variations tab
- Terminal: `seed` output and `test` output

## Risks
- The front end still uses the default theme (the custom theme is Phase 2).
- Sorting by `meta_key` hides shows that have no start date from the admin list. The meta box makes start the first field, and Phase 3 validation will require it.
- `MetaBox` loads up to 200 artists/venues per picker. That's fine for a promoter. A searchable picker would be needed beyond that.

## TODOs for Sarvagya
- [ ] `tests/Unit/Support/SanitizerTest.php`: add `test_longitude_range()`
- [ ] `src/Admin/ShowListColumns.php`: add a "Doors" column
