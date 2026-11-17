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

---

## Phase 4: Shortcodes, REST API and Elementor widget

### What was built
- **Shortcodes** (`Shows\Shortcodes`): `[encore_upcoming_shows limit city genre]` and `[encore_artist_shows artist]`.
- **Shared renderer** (`Shows\ShowListRenderer`). Turns filters into cached HTML.
  - Cache invalidation uses a **version number**, bumped on `save_post_{type}`, `deleted_post`, `trashed_post` and `encore_show_status_changed`.
  - Card markup comes from the theme's `template-parts/show-card.php` when it exists; otherwise from the plugin's `templates/show-card.php`.
- **REST API** (`Rest\ShowsController`, `Rest\ShowSerializer`): `GET /encore/v1/shows` and `GET /encore/v1/shows/{id}`.
  - `args` schemas cover type, enum, min/max and pattern.
  - An explicit `permission_callback` and `X-WP-Total` / `X-WP-TotalPages` headers.
  - The serializer defines the public contract (`docs/INTEGRATION_CONTRACT.md`).
- **Elementor widget** "Encore Upcoming Shows" (`Integrations\Elementor\UpcomingShowsWidget`). Controls for number, city (from terms) and genre. It reuses `ListFilters` and `ShowListRenderer`.
- **Input normalisation** (`Shows\ListFilters`, pure). `"Abu Dhabi"` becomes `abu-dhabi`, limits are capped, and cache keys are order-independent.
- **`demo` command.** Creates a block page with both shortcodes and an Elementor-built page using the widget.
- **`scripts/smoke-api.ps1`.** 14 HTTP checks for the API, shortcodes and widget.

### Why these decisions
| Decision | Reason |
|---|---|
| One query builder, one renderer, three front doors | The shortcode, widget and archive can never disagree about which shows appear. A bug fixed once is fixed everywhere. The brief's DRY requirement. |
| Version-number cache invalidation | You can't efficiently delete "all transients starting with `encore_list_`", and it's impossible with Redis. Bumping one number makes every old key unreachable instantly; old entries expire by TTL. Same idea as cache-busting `?ver=` on assets. |
| Invalidate on `encore_show_status_changed` too | Selling out changes what a card shows (badge, price), but no post is saved. |
| Theme card first, plugin fallback | Shortcodes look native in our theme but still work if the theme is swapped. This is the WooCommerce template-override pattern. |
| A serializer class for API output | The response shape is the public contract. Internal names (`_encore_product_id`, `map_url`) never leak, and a unit test fails if someone accidentally exposes one. |
| `permission_callback => true`, written as a method with a docblock | Public data is a deliberate decision. Since WP 5.5, omitting the callback triggers a notice. Making it explicit is what reviewers look for. |
| `null` instead of `""` for missing values | API clients can distinguish "unknown" from "empty". |

### Bug found while testing (good interview story)
`?from=01-12-2026` was **accepted** although the arg had `'pattern' => '^\d{4}-\d{2}-\d{2}$'`.
- **Cause:** when an arg defines its own `sanitize_callback`, WordPress does not apply the default schema validation unless `validate_callback` is set. Fix: `'validate_callback' => 'rest_validate_request_arg'`.
- **Follow-up:** the `''` default then failed its own pattern and broke every request, so the default was removed for `from` and `to`.
- **Lesson:** test the API with bad input, not only good input.

### WordPress concepts
- **Shortcodes.** `add_shortcode( $tag, $callback )`. The callback gets `$atts` (an array, or `''` when none are given) and must **return** a string. `shortcode_atts()` merges defaults and drops unknown attributes. It also fires `shortcode_atts_{$tag}`, so others can filter the defaults.
- **REST routes.** `register_rest_route( $namespace, $route, [ methods, callback, permission_callback, args ] )` on `rest_api_init`.
  - Route regex: `(?P<id>\d+)`.
  - In `args`: `type`, `default`, `enum`, `minimum`/`maximum`, `pattern`, `validate_callback`, `sanitize_callback`.
  - `WP_REST_Response` sets headers. Return `WP_Error` with `['status' => 404]` for errors.
- **Transients.** `get_transient()` / `set_transient( $key, $value, $ttl )`. Stored in `wp_options`, or in the object cache when one is installed. Keys are at most 172 characters.
- **Elementor widgets.**
  - Extend `\Elementor\Widget_Base`, implementing `get_name`, `get_title`, `get_icon`, `get_categories`, `register_controls` and `render`.
  - Register on `elementor/widgets/register`. Controls come from `Controls_Manager`.
  - Read values with `get_settings_for_display()`.
  - Elementor stores page layouts as JSON in the `_elementor_data` post meta.

### Verification (run on 2026-11-17)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (88 tests, 157 assertions)` |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| API + shortcodes + widget | `.\scripts\encore.ps1 demo; .\scripts\smoke-api.ps1` | **14 passed, 0 failed** |
| Cache invalidation | save a show with `wp post update`, read `encore_list_cache_version` | version 52 → 53; 2 cached lists before |

### Practice questions

**Q1. How do you secure a WordPress REST endpoint?**
A:
- **`permission_callback`.** Always define one. For write routes check `current_user_can()`, and for cookie-authenticated requests the `X-WP-Nonce` header. For public reads return `true` deliberately.
- **`args`.** Every parameter gets a schema (type, enum, ranges, pattern), so invalid input gets a 400 before your code runs. Use explicit `validate_callback` + `sanitize_callback`; there's a gotcha when only sanitize is set.
- **Output.** Return only a whitelisted shape through a serializer, so no internal or private fields leak.
- **Abuse.** Cap `per_page`, use caching, and add rate limiting at the edge (Cloudflare) for abusive clients.

**Q2. How do you cache something in WordPress and keep it fresh?**
A: Pick the layer: page cache for whole pages, the object cache or transients for computed fragments like our show grids, and HTTP caching for assets.
- **Key:** build it from everything that changes the output. We sort the filters, so attribute order doesn't matter.
- **Invalidation:** tie it to the events that make the data stale: post saves, deletes, trashes and status changes. We bump a version number instead of deleting keys, so it works with any object cache backend.
- **TTL:** keep a TTL as a safety net.

**Q3. Why build an Elementor widget instead of telling editors to use the shortcode widget?**
A: A native widget has a real UI. Editors pick city and genre from dropdowns filled from the actual terms, with a live preview, instead of typing attribute syntax and slugs. It's still DRY: the widget's `render()` calls the same filter normaliser and renderer as the shortcode, so there is one query and one cache. The class is only loaded inside Elementor's registration hook, so the plugin works without Elementor installed.

---

## Phase 5: Sold-out waitlist

### What was built
- **Join form.** When a tier is sold out, the ticket panel shows a "Join the waitlist" form. Fields: tier, name, email, optional UAE mobile, 1–4 tickets, consent.
  - It is a plain POST form to `admin-post.php`, enhanced by `waitlist.js` into a `fetch()` call to `POST /encore/v1/waitlist`.
  - Both paths run the same `JoinHandler`.
- **Abuse protection:**
  - A honeypot field: bots get a fake "success" and nothing is stored.
  - A per-IP rate limit of 5 attempts per 10 minutes, keyed by an **HMAC of the IP**, so no raw IPs are stored.
  - A form nonce, checked in the REST `permission_callback`.
  - A `UNIQUE(show_id, variation_id, email)` key: re-submitting returns your current position.
- **Double opt-in.** A 64-character random token is emailed. Only its **HMAC hash** is stored, and it is cleared on use, so links are single-use. Unconfirmed entries expire after 48 h through a scheduled action, plus an hourly sweep.
- **Custom tables**, created with `dbDelta` from `Waitlist\Schema` and versioned with `encore_waitlist_db_version`. `maybe_upgrade()` runs on every load.
  - `wp_encore_waitlist` has 6 purpose-built indexes.
  - `wp_encore_waitlist_log` is the audit trail.
- **State machine** (`Waitlist\Status`): pending → waiting → notified → converted, plus expired and unsubscribed. Re-joining is allowed from expired or unsubscribed. Every change goes through `WaitlistRepository::transition()`: `START TRANSACTION` → `SELECT … FOR UPDATE` → validate → `UPDATE` + audit `INSERT` → `COMMIT`.
- **Restock detection** (`RestockListener`). On `woocommerce_variation_set_stock`, if stock > 0 and someone is waiting (one indexed COUNT), it enqueues an **Action Scheduler** async job. The stock update itself stays fast.
- **`WaitlistNotifier`.**
  - Available tickets = stock − tickets held by open claims.
  - The FIFO **skip-and-continue** batch selects who to notify. Each selected entry moves to `notified` with a 24 h claim window and gets a signed claim link that pre-fills the cart, and a claim-expiry job is scheduled.
  - A MySQL `GET_LOCK` per tier serialises concurrent jobs.
- **Conversion.** On `woocommerce_order_status_processing` / `completed`, any waiting or notified entry with the same billing email and variation becomes `converted`, with the order ID stored.
- **Emails.** Three `WC_Email` subclasses (confirmation, "you're on the list" with position, "tickets are back"). They are editable under WooCommerce → Settings → Emails, share one theme-overridable template (HTML and plain text), and carry an unsubscribe link plus RFC 8058 `List-Unsubscribe` headers.
- **Admin: Encore → Waitlists** (`WP_List_Table`).
  - Filters by show, tier and status, plus email search. Columns include position, qty, status and dates.
  - Row actions **Notify now** and **Remove**, and the same as bulk actions (nonce + `manage_woocommerce`).
  - A per-show summary from **one GROUP BY**.
  - **CSV export** streamed in keyset-paginated chunks of 500, with formula-injection protection.
  - Editors (`edit_others_posts`) see the screen with **masked emails** and no actions.
- **Privacy.** Registered personal-data exporter and eraser (Tools → Export/Erase Personal Data), both paged.
- **`cron` container.** Runs `wp cron event run --due-now` and `wp action-scheduler run` every minute, with `DISABLE_WP_CRON`, the way production should.

### Key decisions and trade-offs (interview talking points)
| Topic | Decision | Why / trade-off |
|---|---|---|
| Queue order | FIFO by **confirmation time**, then id | You're "in line" once you've proven the email is yours. Signing up first but confirming a day later shouldn't jump people who confirmed in minutes. |
| Large request doesn't fit | **Skip and continue**: notify the smaller requests behind; the skipped entry keeps first place | Strict FIFO would leave tickets unsold. Skipping costs the big group nothing now, and they're first for the next restock. Risk: a big group can be passed over repeatedly when restocks are small. Fix if needed: after N skips, stop offering to others until their quantity accumulates. |
| Stock holds | **None.** Open claims are only subtracted from what we *offer* | Simple, and no ghost reservations blocking sales. Unfair edge: a non-waitlist buyer can take tickets during someone's claim window. **Real holds:** decrement stock into a "held" bucket (a custom table, or reduce stock and restore on expiry), release on claim expiry by a scheduled job, and convert the hold at checkout. Costs: more states, and stock that looks sold while held. |
| Custom tables vs CPT + meta | Custom tables | The queue needs `WHERE variation_id = ? AND status = 'waiting' ORDER BY confirmed_at` on a composite index, plus a DB-level UNIQUE constraint. Post meta can provide neither efficiently. |
| Versioned migrations | Stored schema version + `maybe_upgrade()` on load | Activation hooks don't run on plugin *updates* (deploy via git or copying files). Comparing versions upgrades every site exactly once, and `dbDelta` adds columns and indexes without dropping data. |
| Transactions | `START TRANSACTION` / `FOR UPDATE` / `COMMIT` on InnoDB | The status change and its audit row are atomic, and the row lock stops a "Notify now" click and a background job from both notifying the same fan. MyISAM ignores transactions, which is one reason WordPress and MySQL 8 default to InnoDB. |
| Tokens | Store an HMAC **hash**, clear it after use | A DB dump (backup leak, SQL injection elsewhere) can't be turned into working confirm links. HMAC uses a server secret from `wp_salt()`. Comparisons use `hash_equals()`, which runs in constant time. |
| Claim / unsubscribe links | Stateless HMAC signatures (`claim\|id\|expiry`) | Nothing to store. Changing the id or expiry breaks the signature. Purpose-prefixing stops a claim signature being reused as an unsubscribe one. |
| Background work | Action Scheduler, not inline | The refund or stock-edit request returns immediately. Mail sending gets retries, logs and a UI (Tools → Scheduled Actions). |
| Times | UTC in DB (`gmdate`, `UTC_TIMESTAMP()`), local only for display | No daylight-saving or timezone-change bugs in comparisons. |
| No-JS fallback | `admin-post.php` + Post/Redirect/Get, result *code* in URL | Works without JS, refresh can't resubmit, and no PII lands in URLs or analytics. |

### Index cheat sheet (`wp_encore_waitlist`)
| Index | Serves |
|---|---|
| `UNIQUE show_variation_email (show_id, variation_id, email)` | "already on the list?" lookup and duplicate prevention under concurrency (`INSERT IGNORE`) |
| `queue (variation_id, status, confirmed_at, id)` | FIFO queue read with no filesort, and "position in line" counts |
| `status_claim (status, claim_expires_at)` | open-claim sum (soft reservation) and lapsed-claim sweep |
| `status_created (status, created_at)` | 48 h unconfirmed expiry sweep |
| `show_status (show_id, status)` | admin filter by show and the GROUP BY summary |
| `email (email)` | privacy export/erase and order-to-entry conversion match |

`email` is `varchar(190)` because 190 × 4 bytes (utf8mb4) = 760 bytes, which fits InnoDB's 767-byte index prefix limit on older row formats. WordPress core uses 191 for the same reason.

### Bugs found while testing
1. **Fatal `Class "WC_Email" not found`.** Reading `JoinedEmail::ID` autoloaded the email class (which `extends WC_Email`) on a front-end request where WooCommerce hadn't loaded its email base class yet. Fix: the email IDs moved to `EmailRegistry` constants (a class that doesn't extend anything), and the registry `require`s WooCommerce's base class before instantiating ours. Lesson: in WordPress, touching a class constant can trigger an autoload chain that crosses plugin boundaries.
2. **REST + logged-in users.** Without an `X-WP-Nonce` header, WordPress treats a cookie request as anonymous, so a form nonce made for a logged-in user wouldn't verify. `waitlist.js` sends both.
3. **`wp db query` printed nothing** in the CLI container: its MariaDB client fails TLS against MySQL 8. Database checks run through `$wpdb` in `wp eval` instead.

### WordPress concepts
- **`dbDelta()`.** It diffs a `CREATE TABLE` against the live table. It requires two spaces after `PRIMARY KEY`, `KEY` not `INDEX`, and one column per line.
- **`$wpdb->prepare()`.** `%d`, `%s` and `%f` placeholders, with `esc_like()` for LIKE searches. Table names and ORDER BY columns can't be placeholders, so they come from code constants or whitelists.
- **Action Scheduler.** `as_enqueue_async_action`, `as_schedule_single_action`, `as_schedule_recurring_action`, `as_has_scheduled_action` (dedupe), and groups.
- **`WC_Email`.** Registered through `woocommerce_email_classes`. `trigger()` → `send()`. Templates are loaded with `wc_get_template_html()` and can be overridden by themes.
- **`WP_List_Table`.** `get_columns`, `get_sortable_columns`, `get_bulk_actions`, `prepare_items`, `column_{name}` and `row_actions`. The bulk nonce is `bulk-{plural}`. Actions are processed on `load-{page_hook}` before output, then redirected.
- **Privacy API.** `wp_privacy_personal_data_exporters` / `_erasers`. Callbacks are paged, return `done`, and erasers return `items_removed` / `items_retained`.
- **Capabilities.** `edit_others_posts` lets editors view; `manage_woocommerce` lets shop managers act. Check both a nonce *and* a capability on every action.

### Verification (run on 2026-11-17 and 2026-11-18)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (129 tests, 229 assertions)`. 41 new: BatchSelector 8, Status 18, Signer 6, RateLimiter 4, JoinRequest + mask 5 |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| Waitlist E2E | `.\scripts\encore.ps1 seed --force; .\scripts\smoke-waitlist.ps1` | **32 passed, 0 failed** |
| Regression | `smoke-ticketing.ps1`, `smoke-api.ps1` | 12/12, 14/14 |
| No-JS fallback | POST to `admin-post.php` | redirected to `/shows/…/?waitlist=created` with the "check your email" message |
| Tables | `information_schema` via `wp eval` | both InnoDB, all 7 + 2 indexes present |
| Accessibility | Lighthouse mobile on the sold-out show page | 100 |

### Practice questions

**Q1. Walk me through what happens when a refund puts 3 tickets back.**
A:
1. WooCommerce restocks the variation, which fires `woocommerce_variation_set_stock`.
2. `RestockListener` sees stock > 0, runs one indexed COUNT of waiting entries, and enqueues an async Action Scheduler job. The refund request is done.
3. The cron runner picks up the job. `WaitlistNotifier` takes a MySQL named lock for that tier and computes available = 3 − tickets in open claims.
4. It reads the FIFO queue from the `queue` index and applies skip-and-continue.
5. For each chosen fan, it moves the entry `waiting → notified` in a transaction with a row lock and an audit row, sets a 24 h window, emails a signed claim link, and schedules the expiry job.
6. If a claim lapses, the expiry job marks it `expired` and runs the notifier again for the next fans.

**Q2. Why store a hash of the confirmation token instead of the token?**
A: Same reason we hash passwords. Anyone who reads the database (a leaked backup, a SQL injection bug elsewhere, a curious admin) shouldn't be able to confirm or hijack entries. We store `HMAC(secret, token)`. The link carries the token, and we hash it again and compare with `hash_equals()` in constant time, so response timing reveals nothing. After use we clear the hash, so the link is single-use. Random 256-bit tokens don't need slow hashing like bcrypt; that's for low-entropy passwords.

**Q3. How do you make a multi-step database change safe under concurrency in WordPress?**
A:
- Use InnoDB and wrap the steps in `START TRANSACTION` … `COMMIT`.
- Lock the row you're deciding on with `SELECT … FOR UPDATE`. Re-check the state *inside* the lock: never trust a value read earlier.
- Write the change and its audit record, commit, and `ROLLBACK` on any failure.
- For uniqueness, rely on a `UNIQUE` index with `INSERT IGNORE`, not a check-then-insert race.
- For "only one worker per resource", use `GET_LOCK()` / `RELEASE_LOCK()`.
- Do slow side effects such as emails **after** commit, so a failed send doesn't hold locks and a rolled-back change never emails anyone.

---

## Phase 6: Technical SEO and analytics

### What was built
- **Event JSON-LD inside Yoast's graph.** `Seo\EventSchemaPiece` extends Yoast's `Abstract_Schema_Piece` and is added via `wpseo_schema_graph_pieces`. The pure `Seo\EventSchemaBuilder` builds:
  - `ComedyEvent`/`MusicEvent`, with dates carrying the +04:00 offset
  - `eventStatus` Scheduled/Cancelled and the attendance mode
  - a `Place` with `PostalAddress` and geo, plus performers
  - one `Offer` per tier with AED price, InStock/SoldOut and `validFrom`
  - the organizer and `mainEntityOfPage` linked by `@id`
- **Yoast configuration as code** (`scripts/seo-config.php`). Title and meta templates per content type, `noindex` for ticket products, breadcrumbs and organisation.
- **Robots and sitemap** (`Seo\YoastIntegration`). Filtered archives get `noindex, follow`, and archived shows are excluded from the sitemap. WooCommerce already sets `noindex` on cart, checkout and account.
- **Archiving** (`Shows\ArchiveJob`). A daily WP-Cron event (03:00 Dubai) archives ended shows through the same `ShowStatusUpdater`. Archived show URLs return a **301** to the headliner's artist page.
- **GTM + GA4 dataLayer** (`Analytics\DataLayer`, `Analytics\EcommerceItems`, `assets/js/analytics.js`). The container ID comes from settings. The events are `view_item_list`, `select_item`, `view_item`, `add_to_cart` (session-queued), `begin_checkout` and `purchase` (once per order, order-key checked).
- **Verification tooling:**
  - `scripts/smoke-seo.ps1`: 21 checks.
  - `tests/e2e/analytics-check.mjs`: 11 checks in real Chrome.
  - `docs/SEO.md` and `docs/ANALYTICS.md`.

### Why these decisions
| Decision | Reason |
|---|---|
| Event as a Yoast graph piece, not our own `<script>` | One graph per page, nodes linked by `@id`, no duplicate WebPage or Organization data. Search engines get a consistent entity model. |
| Pure `EventSchemaBuilder` | The Rich Results requirements are encoded as unit tests (required + recommended properties, cancelled behaviour). |
| Dates with explicit offset | `2026-11-29T20:00:00+04:00` is unambiguous. Without the offset, Google guesses the timezone and the show time can be wrong in search. |
| Organizer inlined with Yoast's `@id` | Found in testing: Yoast omits its Organization node until a logo is set, which left a dangling reference. |
| `noindex, follow` on filtered archives, no canonical | Hundreds of parameter combinations would be thin duplicates. `follow` keeps show links crawlable. Yoast correctly drops the canonical on noindex pages, because the two are conflicting signals (my first attempt added both; the test exposed it). |
| 301 archived shows to the artist | Preserves link equity and lands the visitor on upcoming dates. Remove from the sitemap so crawlers stop hitting redirects. |
| GTM ID from settings | Staging and production use different containers, and there's no deploy to change tracking. Empty means nothing is printed at all (privacy by default). |
| Server-side `add_to_cart` via the session | The add happens on a POST that redirects. JS on submit can be lost on unload, and a push during the POST never reaches a browser. |
| Purchase guarded by order meta + order key | No double revenue on refresh, and no leaking another customer's order totals. |

### Bugs found while testing
1. **Refresh doubled the tickets.** The tier form posts to `/cart/`. WooCommerce rendered the cart in the POST response, so F5 re-submitted the form: 2 tickets became 4 and the purchase value doubled to AED 600. The analytics test caught it. Fix: `woocommerce_cart_redirect_after_add = yes`, i.e. Post/Redirect/Get.
2. **Dangling organizer reference.** Fixed as above.
3. **Local `noindex` hid SEO output.** `blog_public = 0` makes core print `noindex, nofollow` everywhere, and Yoast then skips its robots and canonical tags. The SEO smoke test switches the site to public for its run, as production would be.

### WordPress concepts
- **Yoast Schema API.** The `wpseo_schema_graph_pieces` filter adds an `Abstract_Schema_Piece` subclass with `is_needed()` and `generate()`. Yoast injects `$context` (canonical, `main_schema_id`, `site_url`) and `$helpers`. Other filters used: `wpseo_robots` and `wpseo_exclude_from_sitemap_by_post_ids`.
- **WP-Cron.** `wp_schedule_event( $timestamp, 'daily', $hook )` when `! wp_next_scheduled()`, cleared on deactivation with `wp_clear_scheduled_hook`. It's pseudo-cron, triggered by page views, so production should disable it and run it from system cron. We run it from the `cron` container.
- **Redirects.** `wp_safe_redirect( $url, 301 )` then `exit`. "Safe" means only allowed hosts can be redirect targets (open-redirect protection).
- **`wp_print_inline_script_tag()`** prints inline scripts so CSP nonce/attribute filters can apply. `wp_json_encode( …, JSON_HEX_TAG )` stops `</script>` breakouts from data.

### Verification (run on 2026-11-18)
| Check | Command | Result |
|---|---|---|
| Unit tests | `.\scripts\encore.ps1 test` | `OK (138 tests, 262 assertions)` |
| PHPCS | `.\scripts\encore.ps1 phpcs` | 0 errors, 0 warnings |
| SEO | `.\scripts\smoke-seo.ps1` | **21 passed, 0 failed** |
| GA4 dataLayer | `node tests/e2e/analytics-check.mjs` (dummy `GTM-TEST123`) | **11 passed, 0 failed** |
| Regression | waitlist / ticketing / API smoke tests | 32/32, 12/12, 14/14 |
| Google Rich Results Test | needs a public URL or a manual paste | **not run**: steps in `docs/SEO.md` |

### Practice questions

**Q1. How do you add Event rich results to a WordPress site that already uses Yoast?**
A: Don't print a second JSON-LD block. Add a graph piece through `wpseo_schema_graph_pieces`: a class extending `Abstract_Schema_Piece` whose `generate()` returns the Event node. Link it to Yoast's nodes by `@id`: `mainEntityOfPage` points at the WebPage and `organizer` at the Organization.
- **Google requires** `name`, `startDate` with a timezone offset, and `location` with an address.
- **Google recommends** `endDate`, `eventStatus`, `eventAttendanceMode`, `image`, `description`, `performer`, `organizer`, and `offers` (price, currency, availability, url, validFrom).
- **Cancelled events** keep the page and set `EventCancelled`.

Then validate with the Rich Results Test and monitor Search Console's Events report.

**Q2. A show has ended. What should happen to its URL, and why?**
A: A daily job marks it archived, the URL 301s to the headliner's artist page, and it leaves the sitemap. The 301 passes most link equity to a page that's still useful (upcoming dates), and expired events lose rich results anyway. A 404 wastes the backlinks. A 302 tells Google to keep the dead URL. Cancelled shows are different: keep them live with `EventCancelled`, because people search for "is X cancelled".

**Q3. How do you implement GA4 e-commerce tracking in WooCommerce without double-counting?**
A: Push the recommended events to the `dataLayer` with the exact `items[]` field names, clearing `ecommerce` between pushes. Configure one GA4 event tag in GTM that forwards them. For `purchase`, push only on the order-received page, and only after checking the order key and an order meta flag. Set the flag when you push, so a refresh or revisit can't resend revenue. Server-side events that happen during a redirecting POST (`add_to_cart`) get queued in the session and printed on the next page. Verify with GTM Preview and GA4 DebugView, and automate a dataLayer check like `analytics-check.mjs`.

---

## Phase 7: Performance (Core Web Vitals)

### What was built
- **Measurement first.** `scripts/lighthouse.ps1` runs Lighthouse mobile 3× per page, keeps the median report (JSON + HTML) and prints a summary. The baseline was saved before any change: `docs/perf/before/`.
- **Page cache.** WP Super Cache, configured in `scripts/cache-config.php` and switched by `WP_CACHE` in `.env`. Cart, checkout and account pages are excluded, as are users with cart or login cookies.
- **Cache purging.** `PageCachePurge` purges a show's page on stock changes, and also the home and archive pages when a show's status changes.
- **Asset diet** (`inc/performance.php`):
  - WooCommerce JS/CSS only on shop pages (which drops jQuery entirely elsewhere)
  - per-block CSS, and no emoji script
- **Critical CSS.** `critical.css` is inlined via `wp_add_inline_style`; the rest of the CSS loads non-blocking with `rel=preload` and a `<noscript>` fallback.
- **Fonts.** Self-hosted Archivo (OFL) as `woff2`, latin subset, `font-display: swap`, with the hero weight preloaded.
- **Images.** WebP output for generated sizes and a 360 w card size in `srcset`.
- **Report.** `docs/PERFORMANCE.md` has the before/after table and what moved each number.

### Results (real runs, median of 3, mobile)
| Page | Perf | SEO | LCP | FCP | TTFB |
|---|---|---|---|---|---|
| Home | 98 → **100** | 92 → **100** | 2.2 → **1.4 s** | 1.7 → **1.1 s** | 1,300 → **10 ms** |
| Archive | 99 → **100** | 100 | 1.9 → **1.3 s** | 1.7 → **1.1 s** | 1,000 → **10 ms** |
| Show | 99 → **100** | 100 | 1.8 → **1.5 s** | 1.7 → **1.2 s** | 830 → **0 ms** |

Accessibility and Best Practices were 100 before and after, and CLS stayed 0. TBT rose 0 → 40 ms on the archive (still "good"). Transfer size fell by 52–59%; render-blocking resources went from 7 to 0.

### Bug found while measuring (strong interview story)
With the cache "on", TTFB on every URL except `/` stayed around 300 ms. The cache files existed, yet the debug log said "No wp-cache file exists. Must generate a new one" on every request. Reading WP Super Cache's `wp_cache_serve_cache_file()` showed a trailing-slash check: cached files are served only when `$wp_cache_slash_check` matches the permalink style. The settings screen sets it automatically; enabling the cache from code didn't. One line fixed it (`wp_cache_slash_check = 1`), and TTFB went to about 5 ms. **Lesson:** "cache enabled" isn't "cache working". Measure hit rates and TTFB, and read the plugin's debug log.

### Why these decisions
| Decision | Reason |
|---|---|
| Measure before changing | Without a baseline you can't prove a change helped, and can't spot one that hurt (TBT here). The brief also forbids invented numbers. |
| Page cache first | TTFB sits at the start of every other metric. Nothing on the client can recover 1.3 s of server time. |
| Purge on stock/status instead of short TTLs | Long cache lifetimes stay safe because we invalidate on the exact events that change the page. |
| Dequeue WooCommerce assets per page, not globally | Cart, checkout and account still get everything they need. Show pages don't run WooCommerce's JS at all (the tier form is plain HTML). |
| Critical CSS split by section | Hand-split (sections 1–10 cover anything that can be above the fold on any template) rather than generated per URL. It's simple to maintain, and CLS 0 proves nothing visible is left unstyled. |
| Self-hosted font despite +28 KB | Brand typography with no third-party DNS/TLS/request (privacy and speed), `swap` so text never hides, and preload so it arrives with the HTML. It's a measured trade-off, not a free win. |
| WebP via `image_editor_output_format` | Built into WordPress (5.8+), with no plugin or CDN dependency. Applies on upload and regeneration. |

### WordPress concepts
- **Asset loading:**
  - `wp_dequeue_script/style` at a late priority on `wp_enqueue_scripts`
  - the `woocommerce_enqueue_styles` filter
  - `should_load_separate_core_block_assets`
  - `style_loader_tag` to rewrite a `<link>`
  - `wp_register_style( $handle, false )` + `wp_add_inline_style` for inline CSS that can still be dequeued by handle
- **Images:**
  - `add_image_size` plus same-ratio sizes enter `srcset` automatically
  - `wp_get_attachment_image` adds `width`/`height`, `srcset`, `loading` and `decoding`
  - `image_editor_output_format` for WebP
  - `wp media regenerate` for existing files
- **Page caching:**
  - the `advanced-cache.php` drop-in plus `WP_CACHE` run before WordPress loads
  - `DONOTCACHEPAGE` (set by WooCommerce on cart/checkout)
  - cookie-based bypass
  - purge APIs (`wpsc_delete_post_cache`, `wpsc_delete_url_cache`)

### Verification (run on 2026-11-18)
| Check | Command | Result |
|---|---|---|
| Lighthouse before | `.\scripts\lighthouse.ps1 -Label before` | table above |
| Lighthouse after | `.\scripts\lighthouse.ps1 -Label after` | table above; per-run 100/100/100 on all pages |
| TTFB with cache | `curl -w %{time_starttransfer}` × 5 | 4–5 ms on `/`, `/shows/`, show page (was 830–1,300 ms) |
| Regression with cache on | ticketing / waitlist / SEO / API smoke tests | 12/12, 32/32, 21/21, 14/14 |
| Unit tests + PHPCS | `test`, `phpcs` | OK (138 tests, 262 assertions); 0 errors |

### Practice questions

**Q1. A WordPress site has a slow LCP. How do you approach it?**
A: Measure first: Lighthouse or WebPageTest for lab data, Search Console / CrUX for field data. Then split LCP into its parts:
- **TTFB:** add a page cache, then an object cache; fix slow queries (Query Monitor).
- **Resource load delay:** make the LCP image discoverable in the HTML with `fetchpriority="high"`, and never lazy-load it.
- **Resource load time:** right-size it with `srcset`/`sizes`, use WebP/AVIF, serve it from a CDN.
- **Render delay:** remove render-blocking CSS/JS (critical CSS, `defer`, dequeue unused plugin assets), and preload the fonts used above the fold.

Re-measure after each change. Here TTFB was the dominant part (1.3 s → 10 ms).

**Q2. How do you keep a page cache from showing stale stock on an e-commerce site?**
A:
- **Never cache personal pages:** cart, checkout and account set `DONOTCACHEPAGE`, and cart/login cookies bypass the cache.
- **Purge on the events that change public pages:** stock changes purge that show's page; status changes also purge the listings.
- **Safety net:** keep a moderate TTL (30 min).
- **Validate server-side at checkout anyway:** a cached "in stock" label is UX, while WooCommerce's stock check and our purchase policy are the truth.

**Q3. What is critical CSS and what are the risks?**
A: It's the CSS needed to render above-the-fold content, inlined in `<head>` so the first paint doesn't wait for a stylesheet; the rest loads asynchronously.
- **Flash of unstyled content and layout shift:** if something visible isn't in the critical CSS, it shows unstyled and then jumps. Check CLS.
- **Repeat views:** inlined CSS isn't cached separately, so every HTML response carries it. Keep it small; ours is ~24 KB raw, ~6 KB gzipped.
- **Maintenance drift:** the critical set must stay in sync with templates. That's why ours is split by named sections, not generated per URL.
