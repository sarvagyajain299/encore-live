# Interview notes

One section per phase. Each section covers what was built, why, the WordPress concepts involved, and practice questions.

---

## Phase 0: Environment and conventions

### What was built
- A Docker Compose stack: WordPress (PHP 8.2, Apache), MySQL 8.0, phpMyAdmin, Mailpit, an on-demand WP-CLI container, and a PHP 8.2 "tools" container for Composer, PHPCS and PHPUnit.
- One command set in three wrappers: `scripts/encore.ps1` (Windows), `scripts/encore.sh` (WSL/Git Bash) and a `Makefile`.
- `scripts/install.sh`: an **idempotent** WP-CLI install. It installs WordPress if needed, then always re-applies the timezone, permalinks, plugins and WooCommerce store settings.
- Quality gates: `phpcs.xml.dist` (WordPress Coding Standards + PHP 8.2 compatibility) and PHPUnit + Brain Monkey with a smoke test.
- A must-use plugin that routes all mail to Mailpit in local/dev.

### Why these decisions
| Decision | Reason |
|---|---|
| WordPress core in a Docker **volume**, our code in **bind mounts** | Core and third-party plugins can never be committed by accident. Git holds only the code we wrote. |
| `wp-config.php` built from environment variables | No secrets in files. Staging and production differ only by `.env`. |
| Idempotent install script | Running it twice gives the same result, so it is safe in CI and onboarding. "Click-ops" setup can't be reproduced. |
| WP-CLI runs as uid 33 | The CLI image (Alpine) and the Apache image (Debian) use different uids for `www-data`. A mismatch causes "permission denied" on plugin installs. |
| Separate PHP 8.2 tools container | Lint and test on the **same PHP version** as production. The developer needs no PHP install. |
| `.gitattributes` forces LF on `.sh` | Windows Git can convert to CRLF. `#!/bin/sh\r` then fails inside Linux containers with a confusing "not found". |
| Mailpit + mu-plugin | Dev machines must never email real people. Mailpit also lets us *see* the emails (needed for the Phase 5 waitlist). |
| Yoast over Rank Math | Yoast's Schema API lets us register our Event as a *graph piece* (a PHP class) inside its JSON-LD graph, instead of printing a second, disconnected `<script type="application/ld+json">`. |
| WP Super Cache | It writes plain HTML files that Nginx can serve without starting PHP. That is the fastest path and simple to explain. |

### WordPress concepts
- **Must-use plugins (`wp-content/mu-plugins`)**: they load automatically before normal plugins and can't be deactivated in the admin. They suit environment plumbing such as mail routing.
- **`wp_get_environment_type()`**: returns `local`, `development`, `staging` or `production` from the `WP_ENVIRONMENT_TYPE` constant. We use it to switch dev-only behaviour on or off (Mailpit, Query Monitor, `blog_public`).
- **Hooks used**: `phpmailer_init` is an *action* (we change the PHPMailer object WordPress passes in). `wp_mail_from` is a *filter* (we return a changed value). An action *does* something; a filter *returns* a value.
- **Permalinks and rewrite rules**: `/%postname%/` is stored in the `permalink_structure` option. `wp rewrite flush --hard` regenerates the rules and writes `.htaccess`.
- **WP-CLI**: a scriptable admin. Everything you can click in wp-admin can be repeated in a script.
- **WordPress Coding Standards (WPCS)**: PHPCS rules covering escaping, nonce checks, prepared SQL, i18n text domains and global prefixes. It catches security mistakes, not just style.

### Bug found during setup (good story for an interview)
After install, `wp_mail()` returned `false`. Hooking `wp_mail_failed` showed `Invalid address: (From): wordpress@localhost`. WordPress builds the sender from the site's host, and `localhost` has no TLD, so PHPMailer's validator rejects it. Fix: a `wp_mail_from` filter that swaps in `wordpress@encore.test`, in local/dev only. Lesson: `wp_mail()` fails silently, so always hook `wp_mail_failed` while debugging.

### Practice questions

**Q1. Why not commit WordPress core and plugins to Git?**
A: They are third-party dependencies with their own release cycle, like `node_modules`. Committing them makes diffs noisy and the repo huge, and it hides which code is actually ours. They also get edited by accident ("hot-fixed core"), and updates become hard to track. We install them reproducibly with WP-CLI (or Composer/WPackagist on bigger projects) and commit only our theme, our plugin and the config.

**Q2. What is the difference between an action and a filter?**
A: Both are hooks. Code registers callbacks with `add_action` or `add_filter`, and WordPress calls them at that point. An **action** (`do_action`) is a notification: "this happened, do something". The callback's return value is ignored. A **filter** (`apply_filters`) passes a value through every callback, and each one must **return** the value, changed or not. In this phase, `phpmailer_init` is an action (we configure the mailer object) and `wp_mail_from` is a filter (we return a new address).

**Q3. How do you keep local, staging and production consistent but different?**
A: Same code and the same container images. Everything that differs lives in environment variables: DB credentials, URLs, `WP_ENVIRONMENT_TYPE`, SMTP host. Code branches on `wp_get_environment_type()` rather than on hostnames. Secrets stay out of Git (`.env` is ignored and `.env.example` documents the keys). The install script is idempotent, so any environment can be rebuilt the same way.
