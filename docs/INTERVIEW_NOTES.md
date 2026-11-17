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

---

## Phase 1: Plugin skeleton and content model

### What was built
- **Bootstrap.** `encore-core.php` only bootstraps. It loads Composer's PSR-4 autoloader, registers the activation and deactivation hooks, and on `plugins_loaded` calls `Plugin::instance()->boot()`.
- **`Plugin`.** The single service-registration class. `services()` lists every service, and admin-only and CLI-only services are skipped on the front end. Each service implements `Contracts\Service::register()`.
- **Content model:**
  - `PostTypes`: `encore_show`, `encore_artist`, `encore_venue` (archives at `/shows/`, `/artists/`, `/venues/`)
  - `Taxonomies`: `encore_genre` (shows + artists), `encore_city` (shows + venues)
- **Meta.**
  - `Meta\Fields` is the single source of truth. Each field has a type, REST schema, sanitizer and input control.
  - `MetaRegistry` registers the fields with `register_post_meta`.
  - One generic `Admin\MetaBox` renders and saves the form for all three post types.
- **Domain:**
  - `ShowStatus` and `Tier` are PHP 8.1 backed enums.
  - `ShowStatusCalculator` holds the status rules as pure logic.
  - `Support\Sanitizer` and `Support\Dates` hold pure helpers.
- **Admin list.** Show date, Venue and Status columns. Show date is sortable and is the default order.
- **WP-CLI.**
  - `wp encore seed [--force]` creates 8 artists, 4 venues, 12 shows (9 upcoming, 3 past; one sold out, one cancelled). Each show gets a variable product with a General/Premium/VIP `Tier` attribute, and capacity is split 60/30/10 of the venue.
  - Placeholder artwork is generated with GD, so no stock-photo licences are needed.
  - `wp encore reset` deletes only what seed created (tagged `_encore_seeded`).

### Why these decisions
| Decision | Reason |
|---|---|
| Field definitions in one array (`Fields`) | The meta box and `register_post_meta` both read it, so they can't drift apart. Adding a field is one entry. |
| `register_post_meta` *and* sanitizing in the meta box | Registered `sanitize_callback`s run on **every** write path (REST, WP-CLI, `update_post_meta`). The meta box sanitizing too is defence in depth, and PHPCS can see it. |
| Leading underscore on meta keys (`_encore_start`) | "Protected" meta is hidden from the generic Custom Fields box. Only our UI (or REST with `auth_callback`) can change it. |
| Datetimes stored as `Y-m-d H:i:s` site-local strings | String order equals time order, so `orderby=meta_value` sorts correctly. `Dates::from_meta()` attaches the `Asia/Dubai` timezone when we need real times. |
| Backed enums for status and tier | They make invalid states unrepresentable in code. `tryFrom()` validates untrusted input in one call. |
| Pure `ShowStatusCalculator` (time injected as `$now`) | The rules are testable in milliseconds, with no database and no clock. The seeder uses the same class, so demo data follows real rules. |
| WooCommerce CRUD classes (`WC_Product_Variable`), never raw `update_post_meta` on products | WooCommerce keeps lookup tables, caches and price ranges in sync only through its CRUD layer. |
| Separate `CliService` and `EncoreCommand` | WP-CLI turns **every public method** into a subcommand. `register()` would have become `wp encore register`. |
| `'custom-fields'` support on the post types | Found while testing: without it, the REST API leaves out the `meta` object entirely, even for registered meta. |

### Custom meta boxes vs ACF (agencies use ACF a lot)
| | Our native meta boxes | Advanced Custom Fields |
|---|---|---|
| Build speed | Slower: you write rendering, saving and sanitizing | Very fast: fields are clicked together in the UI |
| Dependencies | None | A plugin (Pro is paid) that the site depends on forever |
| Version control | Fields live in code | Fields live in the DB unless you use Local JSON/PHP export |
| Security | You must do nonces, caps and sanitizing yourself (shown here) | ACF handles it |
| Performance | One meta row per field | Extra `_fieldname` reference rows per field |
| When to choose | Core business data, public plugins, things that must survive a plugin removal | Marketing/content fields edited by non-developers, tight deadlines |

### WordPress concepts
- **Custom post types and taxonomies.** `register_post_type` and `register_taxonomy` run on `init`. Rewrite rules are cached in the `rewrite_rules` option, so we `flush_rewrite_rules()` **only on activation**. Flushing on every request is a classic performance bug.
- **Activation vs deactivation vs uninstall.** Activate sets up (flush rules, version option). Deactivate cleans transient state but keeps data. Deleting data belongs only in uninstall.
- **Nonces.** `wp_nonce_field()` and `wp_verify_nonce()` give CSRF protection: they prove that *this user's session* submitted *this form* recently. A nonce is not authorisation, so we still call `current_user_can( 'edit_post', $id )`.
- **Save flow.** On `save_post_{type}`: nonce, then skip autosave/revision, then capability, then sanitize, then `update_post_meta`. Use `wp_unslash()` first, because WordPress adds slashes to `$_POST`.
- **Escaping on output.** `esc_html`, `esc_attr`, `esc_url`, `esc_textarea` at the point of echo, matched to the context (HTML body vs attribute vs URL).
- **Hooks used.**
  - Filters: `manage_{type}_posts_columns` and `manage_edit-{type}_sortable_columns` change a value and return it.
  - Actions: `manage_{type}_posts_custom_column` and `pre_get_posts` do something.
  - `pre_get_posts` changes the main admin query by reference before SQL runs.
- **WP-CLI commands.** Docblocks become `wp help` text. `## OPTIONS` defines the flags.

### Verification (run on 2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (34 tests, 60 assertions)` |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 23 files, exit 0 |
| Seed | `.\scripts\encore.ps1 seed` | 8 artists, 4 venues, 12 shows: 8 on_sale, 1 sold_out, 1 cancelled, 3 archived |
| Reset | `.\scripts\encore.ps1 reset` | 56 items removed (24 posts + 12 products + 20 images) |
| Meta box | logged-in POST to `post.php` with bad values | `2027-02-31`→`''`, `7pm`→`''`, age `17`→`0`, status `hacked`→`on_sale` |
| CSRF | the same POST with a forged nonce | meta unchanged |

### Practice questions

**Q1. Walk me through what happens when an editor clicks "Update" on a show.**
A: WordPress posts the form to `post.php` and verifies its own `_wpnonce`, then fires `save_post_encore_show`. Our `MetaBox::save()` then runs these checks in order:
1. It checks our own nonce, so the request is proven to come from our form (CSRF protection).
2. It skips autosaves and revisions, because they don't carry our fields.
3. It checks `current_user_can( 'edit_post', $id )`, because a nonce proves who sent the request, not what they may do.
4. It unslashes each field and runs it through that field's sanitizer, then calls `update_post_meta`.
5. That call triggers the sanitizer registered with `register_post_meta` again. Sanitizers are idempotent, so the second pass is harmless and protects the REST and CLI paths too.

**Q2. Why is `ShowStatusCalculator` a separate class with `$now` as a parameter?**
A: Business rules shouldn't depend on WordPress or the clock. With time injected I can test edge cases deterministically, such as a show ending exactly now, a cancelled show in the past, or a sold-out show that gets restocked. Sold-out sync (Phase 3) and archiving (Phase 6) will call the same class, so the rules live in one place.

**Q3. How would this content model change for 100,000 shows?**
A: Meta queries (`meta_key` sort, `meta_query` filters) on `wp_postmeta` get slow, because `meta_value` is a non-indexed `LONGTEXT`. I would:
1. Keep taxonomies for filtering, because term relationships are indexed.
2. Move hot sortable fields such as the start time into a custom table with proper indexes, synced on `save_post`.
3. Add an object cache (Redis).
4. Use `no_found_rows` where we don't paginate.
Phase 5 shows the custom-table approach for the waitlist.
