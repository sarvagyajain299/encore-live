# CLAUDE.md: Encore Live conventions

Requirements live in `PROJECT_BRIEF.md`. Read it before starting any phase.

## Workflow (non-negotiable)
- **One phase at a time.** Before coding, give a short plan (files, approach, risks) and wait for approval.
- **Never commit to `main`.** Branch per phase: `feature/phase-N-<slug>`. Conventional Commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`, `refactor:`), small commits, messages explain *why*.
- **End of each phase:** append to `docs/INTERVIEW_NOTES.md` (what, why, WP concepts, 3 Q&As). Write `docs/prs/phase-N.md`. Leave 1–2 `TODO(sarvagya):` tasks with hints, and wait for the user to do them before merge.
- **Never invent numbers.** Test counts, PHPCS results and Lighthouse scores come from real runs, with the command shown.
- **Stop and ask** for anything that needs an account, a paid licence or a product decision.

## Environment
- Host is Windows. The repo is at `C:\dev\encore-live` (deliberately outside OneDrive). Everything runs in Docker.
- Commands: `.\scripts\encore.ps1 <cmd>` (PowerShell) = `scripts/encore.sh <cmd>` = `make <cmd>`.
  `up | down | install | wp <args> | composer <args> | phpcs | phpcbf | test | seed | reset | shell | destroy`
- WordPress core and third-party plugins are in the `wp_data` Docker volume. Only `encore-core`, `encore-theme` and `docker/wordpress/mu-plugins` are bind-mounted.
- Local mail goes to Mailpit (http://localhost:8025) via `docker/wordpress/mu-plugins/encore-local-mail.php`.
- When running `docker compose run` from a non-TTY shell, pass `-T`. The scripts handle this.
- `wordpress` is a thin custom image (`docker/wordpress/Dockerfile`: `mod_headers` + security headers). The `cron` service runs WP-Cron + Action Scheduler every minute (`DISABLE_WP_CRON`). `WP_CACHE` in `.env` toggles WP Super Cache.
- `wp db query` doesn't work in the CLI image (its MariaDB client fails TLS to MySQL 8). Use `wp eval` with `$wpdb`.
- With the page cache on, clear it after changing options that affect HTML: `wp eval 'wp_cache_clear_cache();'`.

## Testing
- `.\scripts\qa.ps1` runs everything: PHPCS, PHPUnit, smoke-ticketing / waitlist / api / seo / security, Playwright, the GA4 dataLayer test. Each step gets freshly seeded data.
- Browser tests live in `tests/e2e` (`npm install` once; Playwright ≥ 1.65 is needed for Node 25, since 1.48 hangs).

## What gets committed
Only: `wp-content/themes/encore-theme/`, `wp-content/plugins/encore-core/`, `docker/`, `scripts/`, `docs/`, `tests/`, and root config. Never: WP core, third-party plugins, uploads, `.env`, `vendor/`.

## Code rules
- PHP 8.2+, WordPress Coding Standards (`phpcs.xml.dist`). PHPCS must be clean before a PR.
- Escape on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`). Sanitize on input. Nonce + capability check on every state-changing action. `$wpdb->prepare` for every custom query.
- Text domains: `encore-core` (plugin) and `encore-theme` (theme). Prefix globals with `encore_` / `ENCORE_`, and use the namespace `EncoreCore\`.
- `encore-core`: OOP, namespaced, PSR-4 via Composer, one class per responsibility, a single `Plugin` bootstrap that registers services. Business logic lives here.
- `encore-theme`: classic PHP theme, presentation only. Vanilla JS (no jQuery). RTL-ready (logical CSS properties).
- Unit tests: PHPUnit 11 + Brain Monkey in `tests/Unit/`, extending `EncoreCore\Tests\Unit\TestCase`. They test pure logic and never boot WordPress.
- Locale: AED currency, `Asia/Dubai` timezone, English.

## Decisions log
- SEO plugin: **Yoast**. Its Schema API lets us add a custom Event "graph piece" class to Yoast's JSON-LD graph (Phase 6).
- Page cache: **WP Super Cache**. Simple, with documented Nginx rules for the VPS.
- Local mail: Mailpit. The mu-plugin rewrites the `wordpress@localhost` sender (invalid for PHPMailer) to `wordpress@encore.test`.
- Waitlist: FIFO by confirmation time, skip-and-continue batching, no hard holds (see Phase 5 notes).
- Yoast and WP Super Cache are configured in code (`scripts/seo-config.php`, `scripts/cache-config.php`). Never rely on clicked settings.
- Status: all phases 0–8 are merged. Open work is listed in `docs/TODOS.md` (user exercises) and the README's "What I'd do next".
