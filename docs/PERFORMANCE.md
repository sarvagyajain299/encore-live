# Performance (Core Web Vitals)

Every number below comes from a real run. Reports are committed: [`docs/perf/before/`](perf/before/) and [`docs/perf/after/`](perf/after/), with HTML and JSON per page.

## How it was measured
```powershell
.\scripts\encore.ps1 seed --force
.\scripts\lighthouse.ps1 -Label before -Lighthouse <path-to>\lighthouse.cmd   # baseline
# … changes …
.\scripts\lighthouse.ps1 -Label after  -Lighthouse <path-to>\lighthouse.cmd
```
- **Tool:** Lighthouse 12 CLI, **mobile** form factor, default simulated throttling (slow 4G, 4× CPU slowdown), headless Chrome.
- **Pages:** home, `/shows/`, and the show page `/shows/nova-saleh-neon-mirage-tour/`.
- **Runs:** 3 per page; the report kept is the **median** by performance score. Per-run scores are in each `summary.json`.
- **Conditions, identical for both:**
  - Logged out, after one warm-up request.
  - Site switched to indexable for the run (`blog_public = 1`), so the SEO score isn't capped by the dev site's `noindex`.
- **Environment caveat:** the server is Docker Desktop on a Windows laptop (the before run had PHP files on a slow Windows bind mount). Absolute numbers will differ on the production VPS; the before/after comparison is like-for-like.

## Results

| Page | | Performance | Accessibility | Best Practices | SEO | LCP | CLS | TBT | FCP | TTFB |
|---|---|---|---|---|---|---|---|---|---|---|
| Home | before | 98 | 100 | 100 | 92 | 2.2 s | 0 | 0 ms | 1.7 s | 1,300 ms |
| | **after** | **100** | 100 | 100 | **100** | **1.4 s** | 0 | 0 ms | **1.1 s** | **10 ms** |
| Shows archive | before | 99 | 100 | 100 | 100 | 1.9 s | 0 | 0 ms | 1.7 s | 1,000 ms |
| | **after** | **100** | 100 | 100 | 100 | **1.3 s** | 0 | 40 ms | **1.1 s** | **10 ms** |
| Show page | before | 99 | 100 | 100 | 100 | 1.8 s | 0 | 0 ms | 1.7 s | 830 ms |
| | **after** | **100** | 100 | 100 | 100 | **1.5 s** | 0 | 10 ms | **1.2 s** | **0 ms** |

Per-run performance scores. Before: home 98/98/98, archive 99/98/99, show 99/99/99. After: 100/100/100 on all three pages.

### What was downloaded (median runs)

| Page | | Requests | Transfer | JS | CSS | Images | Fonts | Render-blocking |
|---|---|---|---|---|---|---|---|---|
| Home | before → after | 29 → 19 | 228 → 93 KB | 58 → 8 KB | 42 → 1 KB | 115 → 38 KB | 0 → 28 KB | 7 → 0 |
| Archive | before → after | 27 → 17 | 206 → 86 KB | 58 → 8 KB | 42 → 1 KB | 92 → 31 KB | 0 → 28 KB | 7 → 0 |
| Show | before → after | 22 → 12 | 128 → 62 KB | 60 → 10 KB | 42 → 1 KB | 12 → 4 KB | 0 → 28 KB | 7 → 0 |

(CSS "1 KB" is the deferred file. The critical CSS now arrives inside the HTML document.)

## What moved each number

| Change | Where | Effect |
|---|---|---|
| **Page cache (WP Super Cache)**, configured in code, `WP_CACHE` from `.env` | `scripts/cache-config.php`, `docker-compose.yml` | **TTFB 830–1,300 ms → 0–10 ms.** The biggest single win, and most of the FCP/LCP gain. |
| …**plus** a cache bug fix: `wp_cache_slash_check = 1` | `scripts/cache-config.php` | Enabling from code skipped the trailing-slash setting, so every URL except `/` was regenerated on each request (TTFB stayed ~300 ms "with cache on"). Found by measuring, not assuming. |
| Cache purging on stock and status changes | `src/Performance/PageCachePurge.php` | Keeps cached pages truthful ("Only 3 left", "Sold out") without short TTLs. |
| Drop WooCommerce JS/CSS outside shop pages (`wc-add-to-cart`, `woocommerce`, 3 stylesheets) | `inc/performance.php` | Removed jQuery + jQuery Migrate + blockUI + js-cookie and 18 KB of CSS: **JS 58 → 8 KB**, 5 fewer render-blocking files. |
| Per-block CSS (`should_load_separate_core_block_assets`) | `inc/performance.php` | Full 19 KB block-library CSS → only blocks present. Lighthouse "unused CSS" gone. |
| Emoji script and styles removed | `inc/performance.php` | −1 request, −6 KB, −1 inline script. |
| **Critical CSS inlined**, rest deferred via `rel=preload` + `<noscript>` | `assets/css/critical.css`, `inc/performance.php` | **Render-blocking 7 → 0.** First paint needs no CSS request. CLS stayed 0, so no flash of unstyled content. |
| Self-hosted Archivo 800/900 (`woff2`, latin subset, `font-display: swap`), 900 preloaded | `assets/fonts/`, `inc/performance.php` | **+28 KB** (fonts were system-only before). No third-party font host, no invisible text. Cost accepted for brand typography; it didn't affect LCP. |
| WebP for generated sizes (`image_editor_output_format`), WebP placeholders | `inc/performance.php`, `PlaceholderImage` | Images **115 → 38 KB** on home (with the next row). |
| `encore-card-sm` (360×225) added to card `srcset` | `inc/setup.php` | Phones pick 360 w instead of 720 w; Lighthouse "properly size images" passed. |
| Home meta description | `scripts/seo-config.php` | **SEO 92 → 100** on home. |
| Already in place from earlier phases | | Explicit `width`/`height` from WordPress (CLS 0). `fetchpriority="high"` + eager hero, `loading="lazy"` below the fold. Deferred vanilla JS. `no_found_rows` on non-paginated queries. Transient-cached listings and A–Z menu. |

### Honest notes
- **TBT rose slightly** on the archive (0 → 40 ms) and show page (0 → 10 ms). Lighthouse attributes an 87 ms long task to `main.js` under 4× CPU throttling: once CSS is deferred, script evaluation and layout happen in the same frame. Both values are well within "good" (< 200 ms); still worth profiling.
- **Order-attribution scripts were kept** (`sourcebuster.js`, `order-attribution.js`, ~6 KB). They record the visitor's first landing page and source for WooCommerce reports, a business need that outweighs the bytes.
- **Not done:** a persistent object cache (Redis). Queries already use `WP_Query`, transients and the options cache, so a Redis drop-in would work without code changes. It's on the VPS checklist.

## Production notes (Nginx + Cloudflare)
- **Serve cache files without PHP.** WP Super Cache's files can be served by Nginx before PHP runs (`try_files /wp-content/cache/supercache/$http_host$cache_uri/index.html $uri $uri/ /index.php?$args;`), skipping cookies for logged-in users and carts. Locally it uses the PHP drop-in, which is already ~5 ms.
- **Long-lived caching for static assets.** Assets carry `?ver=<filemtime>`, so they can be cached for a year (`Cache-Control: public, max-age=31536000, immutable`) at Cloudflare and in the browser.
- **Don't edge-cache HTML for carts.** Bypass Cloudflare's HTML cache for `/cart/`, `/checkout/`, `/my-account/`, `/wp-admin/` and any request with `woocommerce_items_in_cart` or `wordpress_logged_in_*` cookies.
