# Security

Verify with `.\scripts\smoke-security.ps1` (12 checks). Result on 2026-11-18: **12 passed, 0 failed**.

## 1. Hardening

| Measure | Where | Why |
|---|---|---|
| File editing in wp-admin disabled (`DISALLOW_FILE_EDIT`) | `docker-compose.yml` → wp-config | A stolen admin session can't become PHP code execution through the theme/plugin editor. |
| XML-RPC disabled | Apache `xmlrpc.php` → 403; `xmlrpc_enabled` → false | Brute-force amplification (`system.multicall`) and pingback abuse. Nothing uses it. |
| User enumeration blocked | `?author=N` → 301 home; `/wp/v2/users` → 401 for visitors (`Security\Hardening`) | Login slugs are half of a brute-force attack. Logged-in users keep the endpoints the block editor needs. |
| Version disclosure removed | `ServerTokens Prod`, `expose_php = Off`, generator meta removed | Makes version fingerprinting for known CVEs harder. |
| Sensitive files refused | Apache: dotfiles and `debug.log` → 403 | `.env`, `.git`, debug logs. |
| Security headers on every response | Apache `mod_headers` (custom image `docker/wordpress`), PHP fallback in `Hardening` | Headers are set **at the server** so they also apply to page-cache hits, which are served before WordPress loads. Apache drops WordPress's own copies first: two `X-Frame-Options` headers can make browsers treat it as invalid (found in testing on `wp-login.php`). |
| Page cache never serves personal pages | WooCommerce `DONOTCACHEPAGE` + rejected cookies (`scripts/cache-config.php`) | No cart or account data leaks between visitors. |
| Waitlist links | HMAC-signed or hashed single-use tokens, `hash_equals` | See Phase 5 notes. |
| Rate limiting | waitlist sign-ups: 5 per 10 min per hashed IP | Mail bombing / list stuffing. |

### Headers set
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)
```
**Production additions** (Nginx/Cloudflare; HTTPS only):
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (add `preload` only once every subdomain is on HTTPS).
- A **Content-Security-Policy**, rolled out in `Report-Only` mode first. GTM and WordPress inline scripts need nonces or hashes (`wp_print_inline_script_tag` supports adding a nonce attribute through the `wp_inline_script_attributes` filter).

Nginx equivalents of the Apache config:
```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(self)" always;
location = /xmlrpc.php { deny all; }
location ~ /\.(?!well-known) { deny all; }
location ~* /debug\.log$ { deny all; }
server_tokens off;
```

## 2. Nonce and capability audit (every state-changing entry point)

| Entry point | Who | Nonce / authenticity | Capability | Input handling |
|---|---|---|---|---|
| Meta box save (`save_post_{type}`, `Admin\MetaBox`) | editors | `wp_verify_nonce( encore_meta_nonce, encore_save_{type} )` | `edit_post` on that post | per-field sanitizers + `register_post_meta` sanitize callbacks |
| REST meta writes (`/wp/v2/encore_*` meta) | editors | REST cookie nonce (`X-WP-Nonce`, core) | `auth_callback` → `edit_post` | `register_post_meta` sanitize + schema |
| Encore → Settings (`options.php`) | admins | `settings_fields()` nonce (core) | `manage_options` (Settings API) | `Settings::sanitize()` whitelist |
| Waitlist row actions Notify / Remove | shop managers | `check_admin_referer( encore_wl_row_{id} )` | `manage_woocommerce` | `absint` IDs |
| Waitlist bulk actions | shop managers | `check_admin_referer( bulk-entries )` | `manage_woocommerce` | `absint` IDs |
| Waitlist CSV export (`admin-post`) | shop managers | `check_admin_referer( encore_waitlist_export )` | `manage_woocommerce` | filters validated; CSV formula-injection guard |
| Waitlist screen view | editors+ | n/a (read) | `edit_others_posts`; emails masked without `manage_woocommerce` | |
| `POST /encore/v1/waitlist` | public | `permission_callback` verifies form nonce `encore_waitlist` | n/a (public form) | REST args schema + `JoinRequest` + honeypot + rate limit |
| No-JS waitlist (`admin-post_nopriv`) | public | `wp_verify_nonce( encore_waitlist )` | n/a | same `JoinHandler` |
| Confirm / claim / unsubscribe links (`template_redirect`) | fan via email | single-use token hash / HMAC signature (WordPress nonces expire too fast for email) | n/a | `absint`, `sanitize_text_field`; tamper → rejected |
| Add to cart / cart update / checkout | public | WooCommerce form handler / Store API `Nonce` header | n/a | `PurchasePolicy` on 4 hooks; attendee phone validated |
| `GET /encore/v1/shows` | public | n/a (read-only) | explicit `public_read()` | args schema with `validate_callback` |
| `wp encore seed/reset` | shell | n/a (CLI) | server access | |

**Output escaping:** every template echo goes through `esc_html`, `esc_attr`, `esc_url`, `esc_textarea` or `wp_kses_post`. PHPCS (`WordPress.Security.EscapeOutput`) enforces it on every run. **SQL:** every custom query uses `$wpdb->prepare()`; ORDER BY columns come from a whitelist.

## 3. Not covered here (server / deploy)
- Strong admin passwords + 2FA, and login rate limiting (Cloudflare WAF rule or Fail2ban on `wp-login.php`).
- Automatic minor core updates, and a weekly plugin update window (staging first).
- Off-site backups (DB + uploads) with a tested restore.
- Least-privilege DB user and file permissions (no `www-data` write access to code).
