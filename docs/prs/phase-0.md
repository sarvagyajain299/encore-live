# PR: Phase 0, environment and conventions

**Branch:** `feature/phase-0-environment` → `main`

## Summary
Adds a reproducible local environment (Docker Compose), a scripted WordPress install, quality tooling (PHPCS/WPCS, PHPUnit + Brain Monkey) and the project docs. After this PR, two commands give a working WooCommerce site at http://localhost:8080.

## Changes
- `docker-compose.yml`: services `wordpress` (php8.2-apache), `db` (MySQL 8.0 with a healthcheck), `phpmyadmin`, `mailpit`, plus on-demand `wpcli` and `tools` (PHP 8.2 + Composer).
- `scripts/install.sh`: an idempotent WP-CLI install. It sets the admin user from `.env`, `/%postname%/` permalinks and the `Asia/Dubai` timezone. It installs WooCommerce, Elementor, Yoast, WP Super Cache and Query Monitor (dev only), and configures the store for AED/UAE.
- `scripts/encore.ps1`, `scripts/encore.sh`, `Makefile`: the same commands on Windows and WSL.
- `docker/wordpress/mu-plugins/encore-local-mail.php`: sends all mail to Mailpit locally and fixes the invalid `wordpress@localhost` sender.
- `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, `tests/`: tooling and a smoke test.
- `encore-core` plugin stub (header only), `encore-theme` placeholder.
- `.gitignore`, `.gitattributes`, `.editorconfig`, `.env.example`.
- Docs: `README.md`, `CLAUDE.md`, `docs/ARCHITECTURE.md`, `docs/INTERVIEW_NOTES.md`.

## Verification (run on 2026-11-17, Windows 11 + Docker Desktop 28.4.0)
| Check | Command | Result |
|---|---|---|
| Stack starts healthy | `.\scripts\encore.ps1 up` | all 4 services healthy |
| Install | `.\scripts\encore.ps1 install` | exit 0. A second run is also exit 0 (idempotent). |
| Pages respond | `Invoke-WebRequest` on `/`, `/shop/`, `/wp-login.php`, `:8081`, `:8025` | all 200 |
| Settings | `wp option get timezone_string` / `permalink_structure` | `Asia/Dubai` / `/%postname%/` |
| Mail | `wp eval "var_dump(wp_mail(...))"` | `bool(true)`, message visible in Mailpit |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 2 files, 0 errors, 0 warnings |
| Unit tests | `.\scripts\encore.ps1 test` | `OK (3 tests, 3 assertions)`, PHPUnit 11.5.58, PHP 8.2.35 |
| Bash runner | `bash scripts/encore.sh phpcs` | exit 0 |

## Test steps for the reviewer
1. `.\scripts\encore.ps1 up` then `.\scripts\encore.ps1 install`
2. Open http://localhost:8080/wp-admin. Log in with the credentials from `.env`. Check **WooCommerce → Settings**: currency is AED.
3. **Users → Lost password** (or any action that sends email): the message appears at http://localhost:8025.
4. `.\scripts\encore.ps1 phpcs` and `.\scripts\encore.ps1 test` both exit 0.

## Screenshots to take
- Docker Desktop with the 4 running containers
- The WordPress dashboard showing the active plugins
- Mailpit inbox with the test email
- Terminal output of `phpcs` + `test`

## Risks / notes
- **Windows bind mounts are slow.** `vendor/` is in a Docker volume (`tools_vendor`) because unzipping through the bind mount timed Composer out after 300 s. Side effect: your IDE can't see `vendor/` for autocompletion. If needed, a later fix is to run `composer install` on the host too.
- Third-party plugin versions are whatever wordpress.org serves at install time. Pin them (`wp plugin install x --version=`) before production.
- In local mode, WP-CLI prints a WooCommerce `_load_textdomain_just_in_time` notice because `WP_DEBUG` is on. It is harmless, and it comes from WooCommerce, not our code.
- `seed` and `reset` exist as commands but fail until Phase 1 adds `wp encore seed`.

## Before merge: TODOs for Sarvagya
- [ ] `scripts/install.sh`: fail fast on empty required `.env` variables
- [ ] `scripts/encore.sh` (+ `.ps1`, `Makefile`): add a `logs [service]` command
