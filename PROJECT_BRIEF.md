# Claude Code Prompt — Project 1: Encore Live (WordPress Event Ticketing Portal)

> **How to use:** Create an empty folder `encore-live/`, put this file in it as `PROJECT_BRIEF.md`, open Claude Code there and say:
> *"Read PROJECT_BRIEF.md. Start in plan mode. Do Phase 0 only, then stop for my review."*
> Run one phase at a time. Review, test and merge each phase's PR before starting the next.

---

## 1. Role and goal

You are a senior WordPress engineer at a Dubai digital agency that builds enterprise WordPress sites for government, events and corporate clients. You are pairing with me, a final-year Computer Science student, to build a **production-quality portfolio project** that I must be able to explain line by line in a developer interview.

Build **Encore Live**, a fictional Dubai promoter of stand-up comedy and live music. It is a WordPress site where fans browse shows, artists and venues and buy tickets through WooCommerce in price tiers. When a show sells out, fans join a waitlist and are emailed automatically, in order, when tickets come back. The site is built for speed and search: event rich results, GA4 e-commerce tracking and measured Core Web Vitals.

Encore Live is a fictional brand. Do not use any real company's name, logo, artist names or copy. Use invented artists and venues with placeholder images (generated or royalty-free with credits).

## 2. Non-negotiable engineering rules

1. **Teaching mode.** I need to understand everything. After each phase, append a section to `docs/INTERVIEW_NOTES.md` covering: what was built, why each design decision was made, the WordPress concepts involved (for example the template hierarchy, hooks vs filters, `WP_Query`, nonces, `$wpdb->prepare`), and 3 likely interview questions with model answers. In each phase, leave 1–2 small, well-scoped tasks marked `// TODO(sarvagya):` with a hint, and ask me to implement them before you merge.
2. **Plan before code.** At the start of each phase, give me a short plan (files to touch, approach, risks) and wait for approval.
3. **Git workflow.** The repo is on GitHub. `main` is protected in spirit: never commit directly to it. Work on one branch per phase (`feature/phase-2-content-model`, etc.). Use Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`, `refactor:`), with small commits and messages that explain *why*. At the end of each phase, write a PR description in `docs/prs/phase-N.md` (summary, changes, test steps, screenshots to take, risks) so I can open the PR and self-review it.
4. **Only commit our code.** Track `wp-content/themes/encore-theme/`, `wp-content/plugins/encore-core/`, `docker/`, `scripts/`, `docs/`, `tests/` and the root config. Never commit WordPress core, third-party plugins, uploads, `.env` files or secrets. Write a thorough `.gitignore` first.
5. **Coding standards.** PHP 8.2+, WordPress Coding Standards enforced with PHPCS (`phpcs.xml.dist`). Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize all input, use nonces and capability checks on every admin action, and use `$wpdb->prepare` for every custom query. Never put secrets in code.
6. **OOP plugin architecture.** `encore-core` uses namespaces (`EncoreCore\…`), Composer PSR-4 autoloading, one class per responsibility and a single bootstrap/service-registration class. No giant `functions.php`. Keep the theme for presentation only; business logic lives in the plugin.
7. **Be honest about results.** Never invent metrics. Lighthouse scores, test counts and coverage in the docs must come from runs you actually executed, with the command shown.
8. **Windows host.** I am on Windows. All tooling must run in Docker (Docker Desktop with WSL2). Give PowerShell-friendly commands or `make`-style scripts that also work in WSL.
9. **Stop if blocked.** If something needs a paid licence, an account or a decision from me, stop and ask. Don't work around it silently.

## 3. Tech stack

- **Local environment:** Docker Compose with `wordpress:php8.2-apache` (or fpm + nginx), **MySQL 8.0**, phpMyAdmin, WP-CLI container, Mailpit for email.
- **Plugins:** WooCommerce, Elementor (free), Rank Math SEO (free) **or** Yoast (pick one and justify it), a page cache plugin compatible with Nginx in production (WP Super Cache or W3 Total Cache), Query Monitor (dev only).
- **Our code:** custom theme `encore-theme` (classic PHP theme, built from scratch, not a child theme), custom plugin `encore-core`.
- **Quality:** PHPCS + WPCS, PHPUnit with Brain Monkey for unit tests of pure logic, and a Playwright smoke test of the purchase flow (optional, Phase 8).
- **Currency and locale:** AED, timezone `Asia/Dubai`, English. Make the theme RTL-ready (`rtl.css`, logical CSS properties) for a later Arabic version.

## 4. Information architecture and content model

Register these in `encore-core`, not in the theme.

| Type | Kind | Key fields (native meta boxes, registered with `register_post_meta`, `show_in_rest`) |
|---|---|---|
| `encore_show` | CPT | start datetime, end datetime, doors open time, venue (relation), artists (multi-relation), linked WooCommerce product ID, age restriction, status (`on_sale`, `sold_out`, `cancelled`, `archived`) |
| `encore_artist` | CPT | short bio, genre, social links, headshot (featured image) |
| `encore_venue` | CPT | address, city, latitude/longitude, capacity, map link |
| `encore_genre` | taxonomy (shows, artists) | comedy, live music, etc. |
| `encore_city` | taxonomy (shows, venues) | Dubai, Abu Dhabi, Sharjah |

Build custom meta boxes in OOP classes, not ACF, so I can show I understand meta, nonces and sanitization. Explain in the interview notes how this compares with ACF, which agencies often use.

**Tickets:** each show links to one **variable WooCommerce product** with a `Tier` attribute (General, Premium, VIP). Each variation's price and **stock quantity is that tier's capacity**. When all variations are out of stock, the show status changes to `sold_out` automatically.

## 5. Phases (one branch and PR per phase)

### Phase 0 — Repo, environment and conventions
- `docker-compose.yml`, `.env.example`, `.gitignore`, `.editorconfig`, `phpcs.xml.dist`, `composer.json` (dev tools), a `Makefile` or `scripts/*.ps1` + `scripts/*.sh` (up, down, wp, phpcs, test, seed, reset).
- Script a WordPress install with WP-CLI: site title, admin user from `.env`, permalinks `/%postname%/`, timezone, required plugins installed and activated.
- `README.md` (setup in under 10 commands), `docs/ARCHITECTURE.md` (system diagram in Mermaid: browser → cache → WordPress → plugin services → MySQL, plus the Action Scheduler and email flows), `CLAUDE.md` (project conventions for future sessions).
- **Done when:** `make up && make install` gives a working site at `http://localhost:8080`, and PHPCS runs.

### Phase 1 — Plugin skeleton and content model
- `encore-core` bootstrap, PSR-4 autoload, activation/deactivation hooks, a `Plugin` class that registers services.
- CPTs, taxonomies, meta boxes with nonce + capability checks + sanitization, admin list-table columns (show date, venue, status) with sortable date.
- A WP-CLI command `wp encore seed` that creates about 8 artists, 4 venues and about 12 shows (some past, some upcoming, one sold out, one cancelled) with linked variable products. `wp encore reset` removes them.
- **Tests:** unit tests for the sanitizers and the status calculator.

### Phase 2 — Custom theme (mobile-first)
- Template hierarchy: `front-page.php`, `archive-encore_show.php`, `single-encore_show.php`, `single-encore_artist.php`, `archive-encore_artist.php`, `single-encore_venue.php`, `404.php`, `search.php`, plus `template-parts/` (show card, artist card, ticket CTA, breadcrumbs).
- Dark, image-forward design with bold ticket calls to action and a sticky mobile "Get tickets" bar.
- **Alphabetical "Shows A–Z" mega-menu** generated from artists, plus filters on the shows archive (city, genre, month) using `pre_get_posts` and query vars, with no plugin.
- Accessibility: semantic landmarks, skip link, focus states, alt text, AA colour contrast.
- Elementor compatibility: register a full-width template and Elementor theme locations (header/footer) so marketing pages can be built in Elementor while show pages stay theme-rendered.
- Enqueue assets correctly with versioning and `defer` scripts. Use vanilla JS, not jQuery, in the theme.

### Phase 3 — WooCommerce ticketing
- A tier selector on the show page that adds the chosen variation and quantity to the cart via the WooCommerce Store API or the standard add-to-cart.
- Limit tickets per order (setting, default 6). Block purchases for past, cancelled or archived shows (server-side validation with `woocommerce_add_to_cart_validation`).
- Custom checkout field: attendee phone (required, UAE format validated), saved to order meta.
- Show date, venue and tier on the cart, checkout, order emails and My Account order view.
- Sync the show status to `sold_out` when stock hits zero, and back to `on_sale` if a refund restocks it.
- Use test payments only (Cash on Delivery / "Check payments" sandbox). Document how a real gateway would plug in.

### Phase 4 — Shortcodes, REST API and Elementor widget
- Shortcodes: `[encore_upcoming_shows limit="6" city="dubai" genre="comedy"]` and `[encore_artist_shows artist="slug"]`, with escaped output and transient caching invalidated on `save_post`.
- REST routes under `encore/v1`: `GET /shows` (filters: city, genre, from, to, page, per_page; returns pagination headers) and `GET /shows/{id}`. Use `register_rest_route` with `args` schemas, `permission_callback` and sanitize/validate callbacks.
- A custom **Elementor widget** "Encore Upcoming Shows" with controls for limit, city and genre that reuses the same query service as the shortcode (DRY).
- **Tests:** unit tests for the query-args builder.

### Phase 5 — Sold-out waitlist (custom tables, admin screens, automated restock emails)
When a show or tier sells out, fans can join a waitlist. When tickets come back (refund, cancellation or an admin adding capacity), the plugin automatically emails waiting fans in first-come order with a time-limited priority link.

- **Join form:** when a tier is out of stock, the ticket CTA becomes **"Join the waitlist"**. Fields: name, email, phone (optional, UAE format), tier, number of tickets wanted (1–4), consent checkbox. Submit with vanilla JS `fetch` to a REST route `POST /encore/v1/waitlist` (nonce, `args` validation, `permission_callback`), with an accessible no-JS fallback via `admin-post.php`.
- **Abuse protection:** honeypot field, per-IP rate limit (transient keyed by a hashed IP), and one entry per (show, tier, email). Re-submitting returns the existing position, not a duplicate.
- **Double opt-in:** a confirmation email with a single-use token (store only a **hash** of the token). Entries stay `pending_confirmation` until confirmed. Unconfirmed entries expire after 48 h via a scheduled action.
- **Custom DB tables** created with `dbDelta` on activation, with a stored schema version and an upgrade routine (explain why versioned migrations matter in WordPress):
  - `{prefix}encore_waitlist`: id, show_id, variation_id, tier, name, email, phone, qty, status (`pending_confirmation`, `waiting`, `notified`, `converted`, `expired`, `unsubscribed`), token_hash, claim_expires_at, created_at, confirmed_at, notified_at, converted_order_id. Unique key (show_id, variation_id, email), and indexes that support the queue query (explain each one).
  - `{prefix}encore_waitlist_log`: an audit trail of every status change (entry_id, from, to, actor, context, created_at).
  - Every query uses `$wpdb->prepare`. Wrap multi-step changes in a transaction (`START TRANSACTION` / `COMMIT`) and explain InnoDB's role.
- **Restock detection:** hook WooCommerce stock changes (`woocommerce_variation_set_stock`, `woocommerce_product_set_stock`, and restocks from refunds/cancellations). When a waitlisted tier goes from 0 to more than 0, enqueue an **Action Scheduler** job, so the stock update itself stays fast.
- **Notification logic** in a dedicated `WaitlistNotifier` class: select the next waiting entries **in FIFO order** whose requested quantity fits the available stock, mark them `notified`, set a **24-hour claim window**, and send a priority email with a signed claim link that pre-fills the cart. When a window expires, a scheduled job notifies the next batch. There is no hard stock reservation. Document this trade-off (simplicity vs fairness) and how you'd add real holds, as an interview talking point.
- **Conversion tracking:** when an order completes with a matching email, show and tier, mark the entry `converted` and store the order ID.
- **Emails** as proper WooCommerce email classes (extend `WC_Email`): waitlist confirmation, "you're on the list", and "tickets are back". Each is editable under **WooCommerce → Settings → Emails** and styled with the store template. Every email includes a one-click unsubscribe link.
- **Admin module** under **Encore → Waitlists**, built on `WP_List_Table`: filter by show, tier and status; search by email; columns for position, qty, status and dates; row actions **Notify now**, **Remove**; bulk actions; a per-show summary panel (waiting, notified, converted, conversion rate) driven by one SQL `GROUP BY` query; and a **CSV export** streamed in chunks (nonce + `manage_woocommerce` capability). Mask emails in the list for users without the capability.
- **Privacy:** register the waitlist with WordPress's **personal data exporter and eraser** (`wp_privacy_personal_data_exporters` / `_erasers`) so a fan's data can be exported or erased from **Tools → Privacy**.
- **Tests:** unit tests for FIFO batch selection (including the case where a large request doesn't fit and smaller ones behind it do: decide and document the rule), token hashing and verification, rate-limit logic, and status transitions (invalid transitions are rejected).

### Phase 6 — Technical SEO and analytics
- **Event structured data:** a JSON-LD `Event` (with `MusicEvent` / `ComedyEvent` where they fit) on each show page: name, startDate/endDate with timezone offset, eventStatus (`EventScheduled` / `EventCancelled`), eventAttendanceMode, location (Place + PostalAddress), performer, image, offers (price per tier, priceCurrency AED, availability InStock/SoldOut, url, validFrom), organizer. Integrate it with the SEO plugin's schema graph rather than duplicating it. Validate with Google's Rich Results Test and record the result in `docs/SEO.md`.
- SEO plugin configuration documented in `docs/SEO.md`: title/meta templates per CPT, XML sitemap including the CPTs, canonicals, Open Graph, breadcrumbs (BreadcrumbList schema), and `noindex` on the cart, checkout, account and filtered archives.
- **Archived shows:** a daily WP-Cron job marks past shows `archived`. Their URLs return a **301 redirect** to the main artist's page, and they are removed from the sitemap. Explain the SEO reasoning in the notes.
- **GA4 via Google Tag Manager:** inject the GTM container from a setting (ID from the settings page, nothing hard-coded). Push the GA4 recommended e-commerce events to `dataLayer`: `view_item_list` (archive), `select_item`, `view_item` (show page), `add_to_cart`, `begin_checkout`, `purchase` (on the thank-you page only once per order, guarded with order meta). Use the correct `items[]` shape. Document the GTM tags and triggers to create in `docs/ANALYTICS.md`, and how to verify in GTM Preview and GA4 DebugView.

### Phase 7 — Performance (Core Web Vitals)
- **First record a baseline:** run Lighthouse (mobile) on the home page, shows archive and a single show, and save the JSON/HTML reports to `docs/perf/before/`.
- Then: responsive images with `srcset`/`sizes`, WebP output, explicit width/height to prevent layout shift, lazy-load below the fold, `fetchpriority="high"` on the hero image, self-hosted fonts with `font-display: swap` and preload, critical CSS for above-the-fold content, dequeue unused WooCommerce/Elementor assets on pages that don't need them, page cache configured, object-cache-friendly queries, and `no_found_rows` where pagination isn't needed.
- Re-run the same audits, save them to `docs/perf/after/`, and write `docs/PERFORMANCE.md` with a before/after table of **real scores only** (Performance, Accessibility, Best Practices, SEO, LCP, CLS, TBT) and what moved each number.

### Phase 8 — QA, hardening and release
- Manual QA checklist in `docs/QA.md` (browse → filter → buy until sold out → join waitlist → confirm email → refund one order → restock email to the first in line → claim → purchase marks the entry converted → export and erase personal data) and run it.
- Optional Playwright smoke test of browse → add to cart → checkout.
- Security pass: file editing disabled, user enumeration blocked, XML-RPC disabled, security headers documented, nonce/capability audit of every admin action.
- Final `README.md` with screenshots list, feature list, architecture diagram, setup, tests, and a **"What I'd do next"** section.
- Write `docs/CV_BULLETS.md`: 3–4 truthful, metric-honest CV bullets drawn only from what was actually built and measured.

## 6. Deliverables checklist

- [ ] Working Docker setup and a one-command install with seed data
- [ ] `encore-theme` (custom, responsive, accessible, Elementor-compatible)
- [ ] `encore-core` (OOP plugin: CPTs, meta boxes, shortcodes, REST routes, Elementor widget, waitlist with custom MySQL tables, WooCommerce email classes, admin list screens with CSV export, privacy exporter/eraser, WP-CLI commands)
- [ ] WooCommerce ticket tiers with capacity-based stock and sold-out sync
- [ ] Event schema, SEO configuration, 301 archiving, GA4/GTM e-commerce events
- [ ] Before/after Lighthouse reports with real numbers
- [ ] PHPCS clean, unit tests passing (real counts recorded)
- [ ] Docs: README, ARCHITECTURE, INTEGRATION_CONTRACT, SEO, ANALYTICS, PERFORMANCE, QA, INTERVIEW_NOTES, CV_BULLETS, per-phase PR notes
- [ ] Clean Git history: one branch and PR per phase, Conventional Commits

Deployment to a Linux VPS behind Cloudflare is handled by a separate prompt (`03-deploy-vps-cloudflare.md`). Keep everything config-driven (`.env`, `wp-config` constants) so staging and production differ only by environment.
