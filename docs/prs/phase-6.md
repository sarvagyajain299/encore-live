# PR: Phase 6, technical SEO and analytics

**Branch:** `feature/phase-6-seo-analytics` → `main`

## Summary
Show pages output Event structured data inside Yoast's schema graph. The Yoast setup (titles, meta, sitemap, noindex rules, breadcrumbs) is configured in code. Past shows are archived daily and 301 to their artist. GA4 e-commerce events go to the dataLayer through a GTM container set in Encore → Settings.

## Changes
- **`src/Seo/`:** `EventSchemaBuilder` (pure), `EventSchemaPiece` (Yoast piece), `YoastIntegration` (robots, sitemap exclusion).
- **`src/Shows/ArchiveJob.php`:** daily archive cron and the 301 redirect.
- **`src/Analytics/`:** `DataLayer` (GTM + server events), `EcommerceItems` (pure). Plus `assets/js/analytics.js`.
- **Plugin changes:**
  - `ShowRepository::loaded()`.
  - The `ShowListRenderer` cache stores show IDs.
  - `Lifecycle` clears the cron hook on deactivate.
- **Scripts:**
  - `scripts/seo-config.php`, run by `install.sh`.
  - `install.sh` also enables PRG after add-to-cart.
  - `scripts/smoke-seo.ps1`.
- **Tests:** `tests/e2e/analytics-check.mjs`, `EventSchemaBuilderTest`, `EcommerceItemsTest`.
- **Docs:** `docs/SEO.md`, `docs/ANALYTICS.md`, `docs/seo/event-jsonld-sample.json`.

## Verification (2026-11-18)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (138 tests, 262 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| SEO | `.\scripts\smoke-seo.ps1` | 21 passed, 0 failed |
| GA4 dataLayer | `node tests/e2e/analytics-check.mjs` | 11 passed, 0 failed |
| Regression | ticketing / waitlist / API smoke | all passing |
| Rich Results Test | needs a public URL | **not run**; manual steps in `docs/SEO.md` |

## Test steps
1. `.\scripts\encore.ps1 install` and `.\scripts\encore.ps1 seed --force`.
2. View Source on a show page. There is one `yoast-schema-graph` script containing a `ComedyEvent` or `MusicEvent`.
3. Open `/shows/saffron-skies-monsoon-sessions/`. It redirects (301) to `/artists/saffron-skies/`.
4. Open `/sitemap_index.xml` → the shows sitemap. Past shows are absent.
5. Put any `GTM-ABC1234` in Encore → Settings. Then in the DevTools console, run `dataLayer` on `/shows/`, a show page, the cart and checkout.

## Screenshots to take
- Rich Results Test result (after running it manually)
- DevTools showing `dataLayer` with `view_item_list` / `purchase`
- The `/shows/` sitemap

## Risks
- No real GTM or GA4 account is connected. Event names and shapes are verified at the dataLayer level only.
- Consent Mode is not implemented. A CMP is needed before running tags in production.
- Person vs PerformingGroup comes from genre, which is a simplification. A per-artist "type" field would be exact.

## TODOs for Sarvagya
- [ ] Run the Google Rich Results Test (Code tab) on a show page and record the result in `docs/SEO.md`
- [ ] `EventSchemaBuilder`: add `doorTime` (schema.org Event property) from `$show->doors`, with a unit test
