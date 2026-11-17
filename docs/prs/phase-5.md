# PR: Phase 5, sold-out waitlist

**Branch:** `feature/phase-5-waitlist` → `main`

## Summary
When a tier sells out, fans can join a double-opt-in waitlist. When tickets come back (refund, cancellation or added capacity), a background job emails waiting fans in FIFO order with a signed 24 h claim link that pre-fills the cart. Purchases mark entries converted. Admins get Encore → Waitlists (filters, actions, summary, CSV), and the data is wired into WordPress's privacy export and erase tools.

## Changes
- **`src/Waitlist/`:**
  - `Schema` (dbDelta, versioned)
  - `WaitlistRepository` (all SQL, prepared, transactions)
  - Pure logic: `Status` (state machine), `BatchSelector` (FIFO skip-and-continue), `Signer`, `RateLimiter`, `JoinRequest`, `EmailMask`
  - Services: `WaitlistService` (join, confirm, unsubscribe, claim), `WaitlistNotifier`, `Scheduler` (Action Scheduler), `RestockListener`, `ConversionTracker`, `Privacy`, `WaitlistForm`, `Links`, `JoinHandler`
  - `WaitlistModule` is the composition root that wires them together.
  - `Http/`: REST controller, `admin-post` fallback, email link handler.
  - `Emails/`: 3 `WC_Email` classes + registry.
  - `Admin/`: `WaitlistPage` (screen, actions, CSV), `WaitlistListTable`.
- **Other plugin files:**
  - `src/Support/KeyValueStore`, `TransientStore`
  - `templates/emails/waitlist.php` + `plain/`
  - `assets/js/waitlist.js`
- **`docker-compose.yml`:** a `cron` service plus `DISABLE_WP_CRON`.
- **Theme:** waitlist form styles.
- **`scripts/smoke-waitlist.ps1`:** 32-step E2E test.
- **Tests:** 41 new unit tests.

## Verification
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | OK (129 tests, 229 assertions) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| Waitlist E2E | `.\scripts\encore.ps1 seed --force; .\scripts\smoke-waitlist.ps1` | 32 passed, 0 failed |
| Regression | `smoke-ticketing.ps1` / `smoke-api.ps1` | 12/12 and 14/14 |
| No-JS fallback | POST to admin-post.php | redirect with `?waitlist=created` |
| Accessibility | Lighthouse (mobile), sold-out show | 100 |

## Test steps
1. `.\scripts\encore.ps1 up` (starts the new `cron` container), then `.\scripts\encore.ps1 seed --force`.
2. Open "Omar Farouk: Uncle Knows Best" (sold out). Join the General waitlist with two different emails.
3. Mailpit: click each **Confirm my place** link. A "You're on the list" email arrives with your position.
4. wp-admin → Products → the show's ticket product → set General stock to 1.
5. Within a minute (cron container), the first fan gets **Tickets are back**. Click it: the ticket is in the cart. Check out.
6. Encore → Waitlists: the entry is **Converted**, and the summary shows the conversion rate. Export CSV.
7. Tools → Export Personal Data: request it for a fan's email. Their waitlist rows are included.

## Screenshots to take
- Waitlist form on a sold-out show (mobile)
- The three emails in Mailpit
- Encore → Waitlists with the summary panel, and the same screen as an Editor (masked)
- WooCommerce → Settings → Emails, showing the 3 waitlist emails
- Tools → Scheduled Actions filtered to group `encore-waitlist`

## Risks
- **No hard stock holds.** Documented trade-off; a design for real holds is in the interview notes.
- **Email deliverability.** Production needs SMTP with SPF, DKIM and DMARC (Phase deploy). Mailpit catches everything locally.
- **IP rate limiting.** It relies on `REMOTE_ADDR` being the real client IP. Behind Cloudflare, configure real-IP restoration.
- **Link scanners.** Corporate scanners may "click" unsubscribe links. RFC 8058 one-click POST headers are sent; a confirm-page step could be added if this becomes an issue.

## TODOs for Sarvagya
- [ ] `EmailMask::mask_phone()` + tests + a masked phone column for editors
- [ ] `encore_waitlist_rate_limit` filter, documented in the integration contract
