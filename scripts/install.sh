#!/bin/sh
# -----------------------------------------------------------------------------
# Encore Live — WordPress install. Runs INSIDE the wpcli container:
#   scripts/encore.sh install     (or .\scripts\encore.ps1 install)
#
# Idempotent: safe to run again. It installs WordPress only if needed and then
# re-applies every setting, so the site always ends up in the same state.
# -----------------------------------------------------------------------------
set -eu

# TODO(sarvagya): Fail fast if a required variable is empty.
#   Required: WP_URL WP_TITLE WP_ADMIN_USER WP_ADMIN_PASSWORD WP_ADMIN_EMAIL
#   Hint: loop over the names and read each value with `eval "val=\${$name:-}"`.
#   If it is empty, print "Missing $name in .env" to stderr (>&2) and `exit 1`.
#   Test it: blank WP_ADMIN_EMAIL in .env, run install, expect the message.

echo "==> Waiting for wp-config.php (written by the wordpress container on first start)"
tries=0
until [ -f /var/www/html/wp-config.php ]; do
	tries=$((tries + 1))
	if [ "$tries" -gt 60 ]; then
		echo "wp-config.php not found after 60s. Run the 'up' command first." >&2
		exit 1
	fi
	sleep 1
done

if wp core is-installed 2>/dev/null; then
	echo "==> WordPress already installed; re-applying settings"
else
	echo "==> Installing WordPress"
	wp core install \
		--url="$WP_URL" \
		--title="$WP_TITLE" \
		--admin_user="$WP_ADMIN_USER" \
		--admin_password="$WP_ADMIN_PASSWORD" \
		--admin_email="$WP_ADMIN_EMAIL" \
		--skip-email
fi

echo "==> Site settings (timezone, formats, permalinks)"
wp option update timezone_string 'Asia/Dubai'
wp option update date_format 'j F Y'
wp option update time_format 'g:i a'
wp option update start_of_week 1
wp rewrite structure '/%postname%/'
wp rewrite flush --hard

# Ask search engines not to index anything that is not production.
if [ "${WP_ENVIRONMENT_TYPE:-local}" = "production" ]; then
	wp option update blog_public 1
else
	wp option update blog_public 0
fi

echo "==> Removing default plugins we don't use"
wp plugin delete hello akismet 2>/dev/null || true

echo "==> Installing and activating third-party plugins"
wp plugin install woocommerce elementor wordpress-seo wp-super-cache --activate

# Query Monitor exposes queries, hooks and errors: dev environments only.
if [ "${WP_ENVIRONMENT_TYPE:-local}" = "local" ] || [ "${WP_ENVIRONMENT_TYPE:-local}" = "development" ]; then
	wp plugin install query-monitor --activate
fi

echo "==> Activating our plugin and theme"
wp plugin activate encore-core
wp theme activate encore-theme

echo "==> Elementor: pages and posts only; show/artist/venue pages stay theme-rendered"
wp option update elementor_cpt_support '["page","post"]' --format=json
# Let the theme's colours and fonts apply inside Elementor instead of Elementor's defaults.
wp option update elementor_disable_color_schemes 'yes'
wp option update elementor_disable_typography_schemes 'yes'

echo "==> WooCommerce store settings (AED, UAE, no onboarding wizard)"
wp option update woocommerce_currency 'AED'
wp option update woocommerce_default_country 'AE'
wp option update woocommerce_store_city 'Dubai'
wp option update woocommerce_price_num_decimals 2
wp option update woocommerce_coming_soon 'no'
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp wc tool run install_pages --user="$WP_ADMIN_USER" >/dev/null

echo ""
echo "Done. Site:  $WP_URL"
echo "      Admin: $WP_URL/wp-admin  (user: $WP_ADMIN_USER)"
