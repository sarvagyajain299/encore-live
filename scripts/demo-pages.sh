#!/bin/sh
# -----------------------------------------------------------------------------
# Creates demo pages that exercise Phase 4 (runs inside the wpcli container):
#   - "Comedy in Dubai": [encore_upcoming_shows] + [encore_artist_shows]
#   - "What's On": built with Elementor, using the Encore Upcoming Shows widget
# Idempotent: existing pages with the same slug are deleted first.
#   .\scripts\encore.ps1 demo    or    scripts/encore.sh demo
# -----------------------------------------------------------------------------
set -eu

for slug in comedy-in-dubai whats-on; do
	id=$(wp post list --post_type=page --name="$slug" --field=ID --format=ids)
	[ -n "$id" ] && wp post delete "$id" --force >/dev/null
done

wp post create --post_type=page --post_status=publish --post_title='Comedy in Dubai' --post_name=comedy-in-dubai \
	--post_content='<!-- wp:paragraph --><p>Stand-up in Dubai this season.</p><!-- /wp:paragraph -->
<!-- wp:shortcode -->[encore_upcoming_shows limit="6" city="dubai" genre="comedy"]<!-- /wp:shortcode -->
<!-- wp:heading --><h2>More from Layla Haddad</h2><!-- /wp:heading -->
<!-- wp:shortcode -->[encore_artist_shows artist="layla-haddad"]<!-- /wp:shortcode -->' --porcelain

# Elementor stores a page as JSON in _elementor_data: sections > columns > widgets.
ELEMENTOR_DATA='[{"id":"a1b2c3d","elType":"section","settings":[],"elements":[{"id":"b2c3d4e","elType":"column","settings":{"_column_size":100},"elements":[{"id":"c3d4e5f","elType":"widget","widgetType":"heading","settings":{"title":"What'"'"'s on in Abu Dhabi"},"elements":[]},{"id":"d4e5f6a","elType":"widget","widgetType":"encore-upcoming-shows","settings":{"limit":3,"city":"abu-dhabi","genre":""},"elements":[]}]}]}]'

page=$(wp post create --post_type=page --post_status=publish --post_title="What's On" --post_name=whats-on --porcelain)
wp post meta update "$page" _wp_page_template template-full-width.php >/dev/null
wp post meta update "$page" _elementor_edit_mode builder >/dev/null
wp post meta update "$page" _elementor_template_type wp-page >/dev/null
wp post meta update "$page" _elementor_data "$ELEMENTOR_DATA" >/dev/null
echo "$page"
