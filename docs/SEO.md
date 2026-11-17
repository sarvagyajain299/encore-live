# Technical SEO

Plugin: **Yoast SEO** (free). All settings live in code: `scripts/seo-config.php`, run by `install`. Verify with `.\scripts\smoke-seo.ps1` (21 checks).

## 1. Event structured data

Each show page outputs **one** JSON-LD graph: Yoast's. Our `EventSchemaPiece` adds the Event node to it through `wpseo_schema_graph_pieces`, so there is no second, disconnected `<script>`.

| Property | Source |
|---|---|
| `@type` | `ComedyEvent` (genre Comedy), `MusicEvent` (Live Music), else `Event` |
| `@id` | `<canonical>#event` |
| `name`, `url`, `description` | show title, permalink, excerpt |
| `startDate`, `endDate` | ISO 8601 **with +04:00** (`2026-11-29T20:00:00+04:00`) |
| `eventStatus` | `EventScheduled`, or `EventCancelled` when the show is cancelled |
| `eventAttendanceMode` | `OfflineEventAttendanceMode` |
| `location` | `Place` + `PostalAddress` (street, locality, `AE`) + `GeoCoordinates` |
| `performer` | each artist; `Person` for comedy, `PerformingGroup` for music (simplification) |
| `image` | featured image (`encore-hero`, 1600×900) |
| `offers` | one `Offer` per tier: price, `priceCurrency: AED`, `InStock`/`SoldOut`, `url`, `validFrom` (publish date). Omitted for cancelled shows. |
| `organizer` | `Organization` using Yoast's `#organization` @id (full node inline, see below) |
| `mainEntityOfPage` | Yoast's WebPage `@id` |
| `typicalAgeRange` | `18-` for 18+ shows |

**Organizer detail:** Yoast only prints its Organization node once a company **logo** is set (SEO → Settings → Site representation). Until then, a bare `@id` reference would point at nothing. So we output the full Organization with Yoast's `@id`; consumers merge nodes that share an `@id`.

A sample from a real page is in [`docs/seo/event-jsonld-sample.json`](seo/event-jsonld-sample.json), written by `smoke-seo.ps1`.

### Google Rich Results Test: not yet run
The test needs a public URL, or a manual paste into https://search.google.com/test/rich-results ("Code" tab). This dev site is on `localhost`, so it hasn't been run. I won't record a result that wasn't produced. Steps:
1. Open a show page and View Source. Copy the whole `<script type="application/ld+json" class="yoast-schema-graph">…</script>` block.
2. Paste it into the Rich Results Test "Code" tab and run it.
3. Record the result here (detected items, any warnings) with the date.

What *was* verified locally (in `smoke-seo.ps1`): every Google-required Event property (name, startDate, location.address) and every recommended one listed above is present on a real page, plus `EventCancelled` on the cancelled show.

## 2. Yoast configuration (`scripts/seo-config.php`)

| Setting | Value | Why |
|---|---|---|
| Show title | `%%title%% Tickets %%sep%% %%sitename%%` | "tickets" is the search intent |
| Artist title | `%%title%%: Tour Dates & Tickets %%sep%% %%sitename%%` | |
| Venue title / meta | `%%title%%: What's On …` | |
| Shows archive title / meta | "Comedy & Live Music in Dubai, Abu Dhabi and Sharjah …" | targets city queries |
| City / genre taxonomy titles | "Shows in Dubai", "Comedy Shows" | |
| `noindex-product` | true | ticket products are hidden; the show page is the canonical page |
| Breadcrumbs | enabled (+ theme support) | visible trail plus `BreadcrumbList` in the graph |
| Organisation | company "Encore Live" | organizer identity |

**XML sitemap:** `/sitemap_index.xml` includes shows, artists and venues, and excludes products. **Archived shows are excluded** via `wpseo_exclude_from_sitemap_by_post_ids`.

**Canonicals and Open Graph:** Yoast defaults. Self-referencing canonicals, plus OG and Twitter tags from title, meta and featured image.

## 3. noindex rules

| URL | Directive | Set by |
|---|---|---|
| `/cart/`, `/checkout/`, `/my-account/` | `noindex` | WooCommerce (`wp_robots`) |
| Filtered archives (`/shows/?show_city=…`, `?show_month=…`, `?show_when=past`) | `noindex, follow` | `YoastIntegration::robots()` |
| Ticket products | `noindex` | Yoast setting |
| Whole site in local/staging | `noindex, nofollow` | `blog_public = 0` from `install.sh` (non-production) |

**Filtered archives have no canonical.** Yoast drops the canonical on `noindex` pages, and that's right: "don't index this" plus "the real version is over there" are conflicting signals. `follow` keeps the links to shows crawlable.

## 4. Archived shows → 301

- **Daily job.** WP-Cron event `encore_archive_past_shows` runs at 03:00 Dubai time. Every published show whose end time has passed goes through `ShowStatusUpdater`, the same calculator as everywhere else, and becomes `archived`.
- **Redirect.** `ArchiveJob::redirect_archived()` sends a **301** to the headliner's artist page (or `/shows/` when there is no artist).
- **SEO reasoning:**
  - A past event page answers nothing a searcher wants, and Google drops expired events from event rich results.
  - A **301** says "moved permanently". Most of the old URL's link equity passes to the artist page, which stays relevant because it lists upcoming dates.
  - A **404/410** would discard that equity.
  - A **302** would tell Google to keep the dead URL indexed.
  - Removing the URLs from the sitemap stops crawlers from being sent to redirects.
- **Cancelled shows are not redirected.** Their page keeps explaining the cancellation, with `EventCancelled` in schema, which Google shows in results.

## 5. Verification

```powershell
.\scripts\encore.ps1 seed --force
.\scripts\smoke-seo.ps1
```
Result on 2026-11-18: **21 passed, 0 failed**. The script switches `blog_public` to 1 for the run (as in production) and restores it afterwards.
