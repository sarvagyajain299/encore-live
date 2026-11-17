# PR: Phase 2, custom theme (mobile-first)

**Branch:** `feature/phase-2-theme` → `main`

## Summary
Adds `encore-theme`, a classic PHP theme with a dark, image-forward design. It has a Shows A–Z mega-menu, archive filters, a sticky mobile ticket bar, Elementor compatibility and an RTL-ready stylesheet. The plugin gains a read API (`Show`, `ShowRepository`, `ShowQueryArgs`), so the theme never touches meta keys.

## Changes
- **Plugin.**
  - `src/Shows/{Show,ShowRepository,ShowQueryArgs,ArchiveFilters,ArtistIndex,SearchScope}.php` and `src/template-functions.php` (Composer `files` autoload).
  - New `_encore_artist` index meta. The artist archive is sorted alphabetically.
- **Theme.**
  - Templates: `front-page`, `archive-encore_show`, `single-encore_show`, `single-encore_artist`, `archive-encore_artist`, `single-encore_venue`, `404`, `search`, `page`, `index`, `template-full-width`.
  - `template-parts/*`, `inc/*`, `assets/css/main.css`, `assets/js/main.js`, `rtl.css`.
- **Install.** Activates the theme and limits Elementor to pages and posts.
- **Tooling.** PHPCS allows `single-encore_show.php`-style file names, which the template hierarchy requires.
- **Tests.** `tests/Unit/Shows/ShowQueryArgsTest.php` (14 tests).

## Verification (2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (48 tests, 94 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| Pages | HTTP GET on 10 URLs | all 200; unknown URL 404 |
| Filters | `/shows/?show_city=dubai&show_genre=comedy` | 2 of 2 expected shows |
| A–Z menu | Puppeteer keyboard test | open, Escape and focus return, outside-click close all pass |
| Mobile layout | Puppeteer 390×844 | no horizontal overflow on home, archive, show |
| Lighthouse accessibility (mobile) | `npx lighthouse … --only-categories=accessibility` | 100 on home, archive, show, artist and venue pages |

## Test steps
1. `.\scripts\encore.ps1 install` then `.\scripts\encore.ps1 seed --force`
2. Open http://localhost:8080 and resize to phone width. The ☰ button opens the nav.
3. Click **Shows A–Z**, then press Escape. Focus returns to the button.
4. On `/shows/`, filter by City = Dubai and Genre = Comedy. You should see 2 shows, and the URL is shareable.
5. Open a show on a phone-width window. The sticky "Get tickets" bar disappears when the Tickets panel scrolls into view.
6. Keyboard only: Tab from page load. The first stop is "Skip to content".

## Screenshots to take
- Home page, desktop and mobile
- The A–Z panel open
- Shows archive with filters applied
- Single show on mobile with the sticky bar
- Lighthouse accessibility report

## Risks
- System fonts for now. Self-hosted brand fonts land in Phase 7 with the performance work.
- The ticket panel shows prices only. The tier selector arrives in Phase 3 via the `encore_ticket_panel` action.
- Elementor header/footer locations only take effect with Elementor Pro (Theme Builder). With the free version, the theme's header and footer render as normal.

## TODOs for Sarvagya
- [ ] `assets/js/main.js`: close the mobile menu when the viewport passes 900px
- [ ] `footer.php`: add a "Back to top" link to `#content`
