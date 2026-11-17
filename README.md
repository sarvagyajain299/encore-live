# Encore Live

A WordPress + WooCommerce ticketing portal for **Encore Live**, a fictional Dubai promoter of stand-up comedy and live music. Fans browse shows, artists and venues, buy tiered tickets, and join a first-come waitlist when a show sells out.

> Portfolio project. Encore Live, its artists and its venues are invented.

**Status:** Phase 0 (environment and conventions). See [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md) for the full roadmap.

## Stack

| Layer | Choice |
|---|---|
| Runtime | Docker Compose: `wordpress:php8.2-apache`, MySQL 8.0, phpMyAdmin, Mailpit, WP-CLI |
| Commerce | WooCommerce (variable products = ticket tiers) |
| Page builder | Elementor (free), marketing pages only |
| SEO | Yoast SEO (free) |
| Page cache | WP Super Cache |
| Dev only | Query Monitor |
| Our code | `encore-core` plugin (business logic) + `encore-theme` (presentation) |
| Quality | PHPCS + WordPress Coding Standards, PHPUnit + Brain Monkey |

## Setup (Windows, PowerShell)

Needs: Docker Desktop (WSL2 backend) and Git.

```powershell
git clone https://github.com/sarvagyajain299/encore-live.git
cd encore-live
.\scripts\encore.ps1 up          # creates .env from .env.example, starts containers
.\scripts\encore.ps1 install     # installs WordPress, plugins and store settings
```

WSL / Linux / macOS: same commands with `make up && make install` (or `./scripts/encore.sh up`).

| URL | What |
|---|---|
| http://localhost:8080 | The site |
| http://localhost:8080/wp-admin | Admin (user/password from `.env`) |
| http://localhost:8081 | phpMyAdmin |
| http://localhost:8025 | Mailpit (every email the site sends lands here) |

> Change the passwords in `.env` before exposing anything. `.env` is never committed.

## Commands

Same names in `scripts/encore.ps1`, `scripts/encore.sh` and the `Makefile`.

| Command | Does |
|---|---|
| `up` / `down` | Start / stop the stack |
| `install` | Install WordPress + plugins (safe to re-run) |
| `wp <args>` | Any WP-CLI command, e.g. `wp plugin list` |
| `composer <args>` | Composer in the PHP 8.2 tools container |
| `phpcs` / `phpcbf` | Lint / auto-fix our code against WordPress Coding Standards |
| `test` | PHPUnit unit tests |
| `seed` / `reset` | Create / remove demo shows (from Phase 1) |
| `shell` | Bash inside the WordPress container |
| `destroy` | Delete all containers **and data volumes** (asks first) |

## Repository layout

```
docker/                     container config (PHP ini, tools image, local mail mu-plugin)
scripts/                    task runners + WP-CLI install script
wp-content/plugins/encore-core/   our plugin: all business logic
wp-content/themes/encore-theme/   our theme: presentation only
tests/                      PHPUnit unit tests
docs/                       architecture, interview notes, PR notes
```

WordPress core and third-party plugins live in a Docker volume, not in Git.

## Docs

- [Architecture](docs/ARCHITECTURE.md)
- [Interview notes](docs/INTERVIEW_NOTES.md)
- [PR notes per phase](docs/prs/)
