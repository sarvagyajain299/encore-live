# PR: Phase 4, shortcodes, REST API and Elementor widget

**Branch:** `feature/phase-4-shortcodes-rest-elementor` → `main`

## Summary
Show listings can now be placed anywhere through three doors that share one query builder and one cached renderer: two shortcodes, an Elementor widget, and a public read-only REST API (`encore/v1`) for partners. The API contract is documented in `docs/INTEGRATION_CONTRACT.md`.

## Changes
- **`src/Shows/`:**
  - `Shortcodes.php`
  - `ShowListRenderer.php`: transient cache with version-number invalidation.
  - `ListFilters.php` (pure)
- **`src/Rest/`:** `ShowsController.php` (routes, args schemas, pagination headers), `ShowSerializer.php` (public contract).
- **`src/Integrations/Elementor/`:** `ElementorIntegration.php`, `UpcomingShowsWidget.php`.
- **`templates/show-card.php`:** fallback card when the theme doesn't provide one.
- **Scripts:**
  - `scripts/demo-pages.sh` with a new `demo` command: shortcode page plus Elementor page.
  - `scripts/smoke-api.ps1`: 14 HTTP checks.
- **Docs:** `docs/INTEGRATION_CONTRACT.md`.
- **Tests:** `ListFiltersTest` (6), `ShowSerializerTest` (2).

## Verification (2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (88 tests, 157 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| Smoke | `.\scripts\encore.ps1 demo; .\scripts\smoke-api.ps1` | 14 passed, 0 failed |
| Cache bust | save a show, read `encore_list_cache_version` | incremented (52 → 53) |

## Test steps
1. `.\scripts\encore.ps1 seed --force` and `.\scripts\encore.ps1 demo`
2. Open http://localhost:8080/comedy-in-dubai/ (shortcodes) and http://localhost:8080/whats-on/ (Elementor).
3. Edit "What's On" with Elementor and change the widget's City. The preview updates.
4. API: http://localhost:8080/wp-json/encore/v1/shows?city=dubai&per_page=2. Check the `X-WP-Total` header in DevTools.
5. Bad input: `?per_page=100` returns 400 with a per-parameter message.

## Screenshots to take
- The Elementor editor panel with the Encore Upcoming Shows controls
- The shortcode page
- REST response plus headers in DevTools (or Postman)

## Risks
- The API is public and unauthenticated by design. Add rate limiting at Cloudflare before publicising it.
- Cached grids live for up to 12 h if a change bypasses WordPress hooks (e.g. direct SQL). The version bump covers every WordPress write path.

## TODOs for Sarvagya
- [ ] REST: add an `artist` filter param (and a smoke check)
- [ ] Elementor widget: "Show past shows instead" switcher
