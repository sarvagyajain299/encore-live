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

---

## Phase 2: Custom theme (mobile-first)

### What was built
- **`encore-theme`.** A classic PHP theme built from scratch.
  - `functions.php` only requires `inc/setup.php`, `inc/assets.php`, `inc/template-tags.php`, `inc/az-menu.php` and `inc/elementor.php`.
  - Templates: `front-page`, `archive-encore_show`, `single-encore_show`, `single-encore_artist`, `archive-encore_artist`, `single-encore_venue`, `404`, `search`, `page`, `index`, plus `template-full-width.php` for Elementor.
  - Template parts: show card, artist card, entry card, ticket CTA, sticky ticket bar, filters, breadcrumbs, A–Z panel.
- **Plugin read API** (the theme stays presentation-only):
  - `Shows\Show` is the read model and `Shows\ShowRepository` loads it.
  - `Shows\ShowQueryArgs` is the single query builder for archive, shortcode, REST and Elementor.
  - `Shows\ArchiveFilters` handles `query_vars` and `pre_get_posts`.
  - `Shows\ArtistIndex` maintains the lookup meta. `Shows\SearchScope` limits search results.
  - `src/template-functions.php` holds the functions the theme may call.
- **Shows A–Z mega-menu.** Built from artists, grouped by letter ("The …" files under the next word), cached in a transient that is dropped when an artist changes.
- **Archive filters.** City, genre, month and upcoming/past, as plain `GET` params (`?show_city=dubai&show_genre=comedy`).
- **Accessibility:**
  - skip link, landmarks, `aria-expanded` disclosures with Escape and outside-click to close
  - visible `:focus-visible`, 44–48 px tap targets, AA contrast tokens
  - `prefers-reduced-motion`, no auto-submitting selects
- **Elementor.** Header and footer theme locations, a full-width template, and Elementor enabled only for pages and posts (`elementor_cpt_support`), so shows stay theme-rendered.
- **Assets.** One CSS file and one deferred vanilla-JS file, versioned by `filemtime`. An inline `no-js`→`js` swap avoids a flash of the open mobile menu.
- **RTL.** All layout uses logical properties (`margin-inline`, `inset-block-end` …). `rtl.css` only fixes direction-dependent visuals.

### Why these decisions
| Decision | Reason |
|---|---|
| Theme calls `encore_get_show()`, never `get_post_meta( '_encore_start' )` | Presentation and storage are decoupled. If shows move to a custom table, no template changes. Every template checks `function_exists`, so the site doesn't fatal if the plugin is off. |
| One `ShowQueryArgs` builder | The archive, artist page, venue page, home page (and in Phase 4 the shortcode, REST and Elementor) all filter identically. It is pure, so it has 14 unit tests. |
| Artist **index meta** (`_encore_artist`, one row per artist) | The artist list is a serialized array (it keeps the headliner order). Querying it would need `LIKE '%i:18;%'`, which is slow and can false-match. The index rows give an exact `=` match. |
| Filters as a GET form, no auto-submit | Works without JS, URLs are shareable and bookmarkable, back button works. Auto-submit on `change` breaks WCAG 3.2.2 for keyboard and screen-reader users. |
| Stretched-link cards | The whole card is clickable but has one link, so screen readers announce each show once, not three times. |
| `fetchpriority="high"` + eager on the hero only, `loading="lazy"` elsewhere | The hero image is the LCP element. Lazy-loading it would delay LCP. Explicit `sizes` lets the browser choose from `srcset` before CSS loads. |
| Map as a link, not an iframe | An embedded map adds hundreds of KB of third-party JS per venue page. |
| Transient for the A–Z data | The menu is on every page. Caching turns a query plus grouping into one option read (or a Redis hit with an object cache). |
| Elementor only for pages/posts | Marketers get a page builder, while show pages keep their fast templates and our structured data. |

### WordPress concepts
- **Template hierarchy.** For `/shows/layla-…/`, WordPress tries `single-encore_show-{slug}.php`, then `single-encore_show.php`, `single.php`, `singular.php`, and finally `index.php`. For `/shows/` it tries `archive-encore_show.php`, then `archive.php`, then `index.php`. `front-page.php` beats everything for the home page.
- **`get_template_part( $slug, $name, $args )`.** Since 5.5 it passes `$args` to the part, so there is no need for globals.
- **`pre_get_posts`.** Changes the main query *before* SQL runs. Guard it with `is_main_query()` and `! is_admin()`, otherwise you change widget queries and admin screens too.
- **`query_vars`.** WordPress ignores unknown URL params. Registering `show_city` makes `get_query_var( 'show_city' )` work and lets WordPress parse it into the main query.
- **Enqueueing.** Use `wp_enqueue_style` and `wp_enqueue_script`, never hard-coded tags, so dependencies, versions and dequeueing work. The `strategy => 'defer'` argument (WP 6.3+) prints `defer`.
- **Transients.** A key/value cache with expiry, stored in options (or the object cache). Invalidate them on the events that make them stale.
- **Theme supports.** `title-tag` lets WordPress and Yoast own the `<title>`. `post-thumbnails`, `html5`, `custom-logo`. `yoast-seo-breadcrumbs` makes Yoast output breadcrumbs plus BreadcrumbList schema.

### Bugs found while testing
1. Show-card headings rendered as literal `< class="show-card__title">`. When no `heading` argument was passed, the guard returned `$args['heading']` (null) instead of the default. Fix: resolve the default first, then validate.
2. Lighthouse flagged contrast 3.74:1 on cancelled cards. `opacity: 0.75` on the whole card faded the text too. Fix: dim only the image and switch the date to the muted colour token.

### Verification (run on 2026-11-17)
| Check | How | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (48 tests, 94 assertions)` (14 new for `ShowQueryArgs`) |
| PHPCS | `.\scripts\encore.ps1 phpcs` | exit 0 |
| Filters | `GET /shows/?show_city=dubai&show_genre=comedy` | 2 shows (expected 2) |
| Artist index | `/artists/nova-saleh/` | 2 upcoming shows, including the double bill where she is second-billed |
| A–Z menu | Puppeteer script: Enter, Escape, outside click | opens; Escape closes and returns focus; outside click closes |
| Mobile overflow | Puppeteer at 390×844 | `scrollWidth` 390 = viewport (no horizontal scroll) |
| Lighthouse a11y (mobile) | `npx lighthouse <url> --only-categories=accessibility --form-factor=mobile` | home 100, archive 100, show 100, artist 100, venue 100 |

SEO scored 58 locally. That is expected: the dev site is deliberately `noindex` (`blog_public=0`), and meta descriptions come in Phase 6.

### Practice questions

**Q1. A request comes in for `/shows/?show_city=dubai`. Walk through what happens.**
A:
1. WordPress parses the URL. The rewrite rule for the `encore_show` archive matches, and `show_city` is kept because we registered it with the `query_vars` filter.
2. `WP_Query` is built and `pre_get_posts` fires. `ArchiveFilters::apply()` sees it is the main query on that archive, calls `ShowQueryArgs::build()`, and sets `meta_query`, `tax_query` and `orderby`.
3. SQL runs, then the template hierarchy picks `archive-encore_show.php`.
4. The loop calls `encore_get_show()` for each post, and the card part renders it with escaped output.

**Q2. Why not just call `new WP_Query` in the archive template with your filters?**
A: Because the main query has already run by the time the template loads. A second query doubles the database work, and pagination, `found_posts`, 404 handling and SEO plugins would all be looking at the wrong (unfiltered) main query. `pre_get_posts` changes the one query WordPress was going to run anyway.

**Q3. How do you make a WordPress theme accessible?**
A:
- Semantic landmarks (`header`, `nav` with labels, `main`, `footer`) and a skip link to `main`.
- Logical heading order, and visible focus styles via `:focus-visible`.
- Disclosure widgets with `aria-expanded` and `aria-controls`, plus Escape to close with focus returned to the button.
- Alt text that is empty for decorative images and descriptive for meaningful ones.
- AA contrast tokens checked with Lighthouse/axe, generous tap targets, `prefers-reduced-motion` respected, and no context changes on input.

Then measure it. We ran Lighthouse on five templates and fixed the one contrast failure it found.

---

## Phase 3: WooCommerce ticketing

### What was built
- **Tier selector** (`Tickets\TicketPanel`). A plain HTML form on the show page, hooked into the theme's `encore_ticket_panel` action.
  - It posts WooCommerce's standard `add-to-cart` + `variation_id` + `quantity` to the cart page.
  - Each tier shows its price, "Only N left" when low, or is disabled when sold out.
  - `assets/js/tickets.js` only limits the quantity list to the chosen tier's remaining stock.
- **Purchase rules** (`Tickets\PurchasePolicy`, pure). Blocks invalid quantities, cancelled shows, ended or started shows, sold-out shows, and more than *N* tickets per show per order (counted across tiers and existing cart contents). `CartValidation` enforces it at four points:
  - `woocommerce_add_to_cart_validation` (classic form **and** Store API)
  - `woocommerce_update_cart_validation` (classic cart quantity edits)
  - `woocommerce_store_api_product_quantity_maximum` (block cart stepper)
  - `woocommerce_check_cart_items` (re-check before checkout)
- **Settings.** Encore → Settings uses the Settings API: max tickets per order (default 6) and a GTM ID field for Phase 6. Stored as one autoloaded option array.
- **Attendee phone** (`Tickets\AttendeePhoneField`). A required checkout field, registered with the **Additional Checkout Fields API** because the site uses the Checkout *block*. It is validated as a UAE number and stored normalised (`+971501234567`) in order meta `_wc_other/encore/attendee-phone`. `Support\PhoneNumber` holds the pure parser.
- **Ticket details everywhere** (`Tickets\OrderDetails`).
  - `woocommerce_get_item_data` adds Show date and Venue to cart and checkout lines (classic and blocks).
  - `woocommerce_checkout_create_order_line_item` snapshots them as order item meta, so they appear in emails, My Account, the thank-you page and admin. The tier appears via the variation attribute.
- **Sold-out sync** (`Tickets\ShowStatusSync` → `Shows\ShowStatusUpdater`).
  - Listens to `woocommerce_variation_set_stock`, `woocommerce_product_set_stock` and `woocommerce_variation_set_stock_status`.
  - Recalculates with the Phase 1 `ShowStatusCalculator`, saves, and fires `encore_show_status_changed`.
- **Test payments.** Cash on Delivery (enabled for virtual products) and Check payments, set up by the install script.
- **Smoke test** `scripts/smoke-ticketing.ps1`. Drives the whole flow over HTTP through the Store API (12 checks).

### Why these decisions
| Decision | Reason |
|---|---|
| Standard add-to-cart form, not a custom AJAX endpoint | It works without JS and reuses WooCommerce's own handler, including stock checks, sessions and notices. The Store API fires the same validation filter, so block-cart and form paths are guarded identically. |
| Validate in four places | A rule checked only on add-to-cart is bypassable: by editing the quantity in the cart, or by a show being cancelled while a ticket sits in a cart. `woocommerce_check_cart_items` is the last gate before payment. |
| Sales close at **start time**, not at "archived" status | The archive cron (Phase 6) runs daily. Relying on status would leave hours where a started show is still buyable. |
| Additional Checkout Fields API | The checkout is the block version, which ignores `woocommerce_checkout_fields`. The new API renders the field, validates server-side, saves to order meta and shows it in admin and emails, without custom React. |
| Normalise phone to E.164 | One canonical format makes duplicate detection, SMS gateways and the Phase 5 waitlist reliable. |
| Snapshot show date and venue onto order items | Orders are legal records. If the show is re-dated, the order must still say what was sold at the time. |
| Status updates through hooks on stock writes | Every path that changes stock (sale, refund restock, cancel, admin edit) goes through WooCommerce's stock functions, so one listener covers them all. |

### How a real payment gateway would plug in
WooCommerce gateways are classes extending `WC_Payment_Gateway`, added via the `woocommerce_payment_gateways` filter. For the Checkout block they also register a JS integration (`registerPaymentMethod`). For a UAE launch you would install the provider's official plugin (for example Stripe, Checkout.com, Telr or Network International). Then:
- **Keys:** API keys go in `wp-config` constants or environment variables, never in the database export or Git.
- **Webhooks:** enable them, because they are what marks orders paid when the customer closes the 3-D Secure tab.
- **Test mode:** test in sandbox mode with test cards.

Nothing in our code changes: the gateway moves the order to `processing`, WooCommerce reduces stock, and our `ShowStatusSync` reacts to the stock change.

### WordPress / WooCommerce concepts
- **Variable products.** The parent holds attributes (`Tier: General | Premium | VIP`). Each variation has its own price, stock and SKU. Stock quantity *is* the capacity.
- **Validation filters return a boolean.** Returning `false` from `woocommerce_add_to_cart_validation` cancels the add. `wc_add_notice( $msg, 'error' )` tells the customer why. The Store API turns those notices into a 400 JSON error.
- **Store API vs REST API.** `/wc/store/v1` is the public, cart-session-based API used by the blocks, protected by a `Nonce` header. `/wc/v3` is the authenticated admin API (keys/OAuth).
- **Order item meta.** A key without an underscore is shown to customers and admins. A key with a leading underscore (`_encore_show_id`) is hidden and used for lookups.
- **Settings API.** `register_setting()` with a `sanitize_callback`, plus `settings_fields()`, which prints the nonce. `options.php` checks the `manage_options` capability. The page renders sections and fields; WordPress saves.

### Verification (run on 2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (80 tests, 130 assertions)` |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| E2E ticketing | `.\scripts\smoke-ticketing.ps1` | **12 passed, 0 failed** |
| No-JS form | POST the show page form to `/cart/` | 2 General tickets in cart, with Show date + Venue item data |
| Order email (Mailpit) | "Your Encore Live order has been received!" | contains "– Tickets - General", Show date, Venue, `+971…` attendee mobile |
| Accessibility | Lighthouse mobile, show page with tier selector | 100 |

What the smoke test covers:
- adding 4 tickets, with 7 rejected ("up to 6")
- a cancelled show rejected
- an invalid phone rejected and a valid phone stored as `+971507654321`
- line items carrying date, venue and tier
- stock set to 2/0/0 → buying the last 2 flips the show to `sold_out`
- refunding 1 with restock → stock 1 → `on_sale`

### Bugs found while testing
- WooCommerce rejected `inputmode` as an additional-field attribute and logged a `_doing_it_wrong` notice. Removed. WooCommerce whitelists the attributes it allows.
- The WP-CLI `shop_order_refund create` command doesn't expose `line_items`/`restock_items`. The smoke test calls `wc_create_refund()`, the same function the admin Refund button uses.

### Practice questions

**Q1. How do you stop someone buying 50 tickets when the limit is 6?**
A: Server-side, at every point the cart can change:
- add-to-cart validation, which counts what is already in the cart for that show across all tiers
- cart-update validation
- a Store API quantity maximum for the block cart's stepper
- a final `woocommerce_check_cart_items` pass before payment

Client-side limits are only UX. The rules live in a pure `PurchasePolicy` class with unit tests. One determined buyer could still place several orders, so for real abuse I'd add a per-customer limit: count by billing email or account across orders, plus rate limiting.

**Q2. The last ticket sells. What updates the show to "Sold out"?**
A:
1. WooCommerce reduces variation stock when the order is placed or paid, which fires `woocommerce_variation_set_stock`.
2. `ShowStatusSync` maps the variation to its parent product, then to the show through the `_encore_show_id` product meta.
3. It calls `ShowStatusUpdater::refresh()`. That reads every tier's stock, asks `ShowStatusCalculator`, saves `sold_out`, and fires `encore_show_status_changed`.

A refund with "restock" calls the same stock functions, so the show returns to `on_sale` automatically. The smoke test checks both directions.

**Q3. Classic checkout vs Checkout block: what changes for a developer?**
A: The block checkout is React, talking to the Store API, so PHP filters that edit checkout HTML or `woocommerce_checkout_fields` don't apply. For fields you use the Additional Checkout Fields API (PHP registration, server-side validate/sanitize hooks). For cart line data, `woocommerce_get_item_data` still works because the Store API reads it. For order creation, `woocommerce_checkout_create_order_line_item` still fires. Payment gateways need a JS registration as well as the PHP class. Always test both the classic path and the Store API path, as our smoke test does.
