# Integration contract

How other code talks to Encore Live: the public REST API, the theme ↔ plugin template API, shortcodes, the Elementor widget, and the hooks other plugins can use. Anything listed here is a promise. Changing it is a breaking change and needs a new version (`encore/v2`).

## 1. REST API: `encore/v1`

Base URL: `https://<site>/wp-json/encore/v1`. Read-only. No authentication: published shows are public information. Responses are JSON. Errors use WordPress's standard shape: `{ "code", "message", "data": { "status", "params" } }`.

### `GET /shows`

| Param | Type | Default | Rules |
|---|---|---|---|
| `city` | string | (none) | city slug, `^[a-z0-9-]*$` (e.g. `dubai`, `abu-dhabi`) |
| `genre` | string | (none) | genre slug (e.g. `comedy`, `live-music`) |
| `from` | string | (none) | `YYYY-MM-DD`, inclusive, site timezone (Asia/Dubai) |
| `to` | string | (none) | `YYYY-MM-DD`, inclusive |
| `when` | string | `upcoming` | `upcoming` (soonest first), `past` (latest first), `all` |
| `page` | integer | `1` | ≥ 1 |
| `per_page` | integer | `10` | 1–50 |

Response headers: `X-WP-Total` (matching shows), `X-WP-TotalPages`.
Invalid params return **400** `rest_invalid_param`, with a message per parameter.

### `GET /shows/{id}`
Returns one published show, or **404** `encore_show_not_found`.

### Show object

```json
{
  "id": 591,
  "title": "Comedy Night: Expats Anonymous",
  "url": "http://localhost:8080/shows/comedy-night-expats-anonymous/",
  "status": "on_sale",
  "start": "2026-11-22T20:00:00+04:00",
  "end": "2026-11-22T22:30:00+04:00",
  "doors": "19:00",
  "age_restriction": 18,
  "genres": ["Comedy"],
  "venue": {
    "id": 587, "name": "The Lantern Room", "url": "…/venues/lantern-room/",
    "address": "Warehouse 14, Al Quoz Industrial Area 1, Dubai", "city": "Dubai",
    "geo": { "lat": 25.1372, "lng": 55.2295 }
  },
  "artists": [ { "id": 577, "name": "Marco Bellini", "url": "…" } ],
  "price": { "currency": "AED", "min": 95, "max": 295 },
  "image": "…/comedy-night-expats-anonymous-720x450.jpg"
}
```

- `status` is one of `on_sale`, `sold_out`, `cancelled`, `archived`.
- Times are ISO 8601 with offset. `artists[0]` is the headliner.
- Missing data is `null`, never an empty string: `start`, `end`, `doors`, `venue`, `price`, `image`.

## 2. Theme ↔ plugin template API

Defined in `wp-content/plugins/encore-core/src/template-functions.php`. The theme calls only these functions, always behind `function_exists()`.

| Function | Returns |
|---|---|
| `encore_get_show( $post = null )` | `EncoreCore\Shows\Show` or `null` |
| `encore_query_shows( array $filters )` | `WP_Query` (filters as in `ShowQueryArgs::build()`) |
| `encore_archive_filters()` | current `city`/`genre`/`month`/`when` filter values |
| `encore_format_price( float )` | `"AED 95"` |
| `encore_format_show_date( Show, $format )` | `"Fri 20 Nov 2026 · 8:00 pm"` |

The theme gives the plugin one extension point: the action `encore_ticket_panel( Show $show )`, fired inside the Tickets panel on the show page.

## 3. Shortcodes

```
[encore_upcoming_shows limit="6" city="dubai" genre="comedy"]
[encore_artist_shows artist="layla-haddad" limit="6"]
```
- Output is cached in transients for 12 h, and invalidated as soon as any show, artist or venue is saved, deleted or trashed, or a show changes status.
- `limit` is capped at 24.
- An unknown artist renders nothing for visitors and a hint for editors.

## 4. Elementor widget
**Encore Upcoming Shows** (`encore-upcoming-shows`, General category). Controls: number of shows, city, genre. It uses the same filters, renderer and cache as `[encore_upcoming_shows]`.

## 5. Hooks for other plugins

| Hook | Type | When |
|---|---|---|
| `encore_core_services` | filter | Change the list of services the plugin boots |
| `encore_show_status_changed( $show_id, ShowStatus $new, ShowStatus $old )` | action | After a show's status is recalculated and changed (e.g. sold out, back on sale) |
| `encore_ticket_panel( Show $show )` | action | Print purchase UI on the show page |

## 6. WooCommerce data

| Where | Key | Meaning |
|---|---|---|
| Product meta | `_encore_show_id` | Show this variable product sells tickets for |
| Variation attribute | `Tier` (`tier`) | `General` / `Premium` / `VIP` |
| Order item meta | `_encore_show_id` (hidden), `Show date`, `Venue` | Snapshot at checkout |
| Order meta | `_wc_other/encore/attendee-phone` | Attendee mobile, E.164 (`+9715…`) |
