# QA

## Run everything
```powershell
.\scripts\encore.ps1 up; .\scripts\encore.ps1 install
cd tests\e2e; npm install; cd ..\..    # once
.\scripts\qa.ps1                       # ~20 minutes; reseeds data before every suite
```

### Last full run: 2026-11-18 (`docs/qa-last-run.json`)

| Suite | Command | Result |
|---|---|---|
| PHPCS (WordPress Coding Standards + PHPCompatibility 8.2) | `.\scripts\encore.ps1 phpcs` | PASS: 117 files, 0 errors |
| PHPUnit + Brain Monkey | `.\scripts\encore.ps1 test` | PASS: OK (138 tests, 262 assertions) |
| Ticketing E2E (Store API) | `.\scripts\smoke-ticketing.ps1` | PASS: 12 passed, 0 failed |
| Waitlist E2E (REST + Mailpit + admin + privacy) | `.\scripts\smoke-waitlist.ps1` | PASS: 33 passed, 0 failed (step 33 added after this run, passed on its own) |
| SEO | `.\scripts\smoke-seo.ps1` | PASS: 21 passed, 0 failed |
| REST API, shortcodes, Elementor | `.\scripts\smoke-api.ps1` | PASS: 14 passed, 0 failed |
| Security | `.\scripts\smoke-security.ps1` | PASS: 12 passed, 0 failed |
| Playwright purchase journey (Pixel 7, Chrome) | `cd tests\e2e; npx playwright test` | PASS: 1 passed (16.0 s) |
| GA4 dataLayer journey (Puppeteer) | `cd tests\e2e; npm run analytics` | PASS: 11 passed, 0 failed |

Total automated checks: 138 unit tests + 104 smoke checks + 12 browser checks.

## The brief's manual checklist → evidence

Each step of the end-to-end story from the brief, and which check exercises it.

| # | Step | Verified by | How |
|---|---|---|---|
| 1 | Browse shows | Playwright | `/shows/` renders "All shows" |
| 2 | Filter (city, genre) | Playwright, `smoke-api` | GET form → `?show_city=dubai&show_genre=comedy` → "2 shows" |
| 3 | Buy tickets | Playwright | tier + qty → cart (date/venue shown) → block checkout (attendee phone, COD) → order received |
| 4 | Buy until sold out | `smoke-ticketing` | stock 2/0/0 → buy last 2 → show status `sold_out` |
| 5 | Join the waitlist | `smoke-waitlist` | 3 fans join a sold-out tier → `pending_confirmation` |
| 6 | Confirm email | `smoke-waitlist` | confirm link from Mailpit → `waiting`; position email "number 1"; link single-use |
| 7 | Refund one order (restock) | `smoke-ticketing` (refund → stock + status), `smoke-waitlist` (restock → notify) | `wc_create_refund` restocks; stock to 3 queues the Action Scheduler job |
| 8 | Restock email to the first in line | `smoke-waitlist` | FIFO skip-and-continue: B (2) and C (1) notified, A (4) keeps first place |
| 9 | Claim | `smoke-waitlist` | signed claim link puts 1 General ticket in the cart; tampered link rejected |
| 10 | Purchase marks the entry converted | `smoke-waitlist` | checkout → entry `converted` with order ID |
| 11 | Export and erase personal data | `smoke-waitlist` | privacy exporter returns the entry; eraser deletes it |
| + | Unsubscribe, claim expiry, audit log, admin masking, CSV, rate limit | `smoke-waitlist` | 32 checks in total |

### Still manual (needs a person or an account)
- [ ] Google **Rich Results Test** on a show page (Code tab), recorded in `docs/SEO.md`
- [ ] **GTM Preview + GA4 DebugView** with a real container and property (`docs/ANALYTICS.md`)
- [ ] Visual review on a real phone (iOS Safari, Android Chrome) and with a screen reader (NVDA or VoiceOver)
- [ ] Keyboard-only pass: Tab through home → show → checkout. Focus is always visible and no traps.
- [x] README screenshots: `docs/screenshots/`, captured by `cd tests\e2e; npm run screenshots`

## Bugs found by QA across the project
Every one was found by a test or a measurement, not by reading code:

| Phase | Bug | Found by |
|---|---|---|
| 0 | `wordpress@localhost` sender rejected, so `wp_mail` silently failed | Mailpit check |
| 0 | Composer timed out on the Windows bind mount | first `composer install` |
| 1 | REST `meta` missing without `custom-fields` support | REST check |
| 2 | Card headings printed `< class=…>` | screenshot |
| 2 | Cancelled cards failed contrast (opacity) | Lighthouse a11y |
| 3 | `inputmode` rejected by the Additional Checkout Fields API | WP-CLI notice |
| 4 | REST `pattern` skipped when only `sanitize_callback` set | bad-input test |
| 5 | Fatal: `WC_Email` autoloaded too early | waitlist E2E |
| 6 | Refresh after add-to-cart doubled the tickets | GA4 browser test |
| 6 | Dangling organizer `@id` | SEO smoke |
| 7 | Page cache served only `/` (slash-check setting) | TTFB measurement |
| 8 | Duplicate `X-Frame-Options` on `wp-login.php` | security smoke |
| 8 | Duplicate "Tickets" landmark names | Playwright locator |
| Post | Waitlist rows outlived their deleted show, so the admin listed blank show names | screenshot |
| Post | Checkout field labels near-invisible (white WooCommerce inputs, light theme text) | screenshot |
| Post | Cheque gateway showed WooCommerce's "Store Name, Store Street…" placeholder | screenshot |
