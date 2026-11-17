<?php
/**
 * WP Super Cache configuration as code. Run by install.sh:
 *   wp eval-file /scripts/cache-config.php
 *
 * Locally (Apache, in Docker) WP Super Cache serves cached HTML through its
 * PHP advanced-cache.php drop-in: WordPress never fully boots for a cache
 * hit. In production behind Nginx, the same cache files are served by Nginx
 * directly with try_files, so PHP doesn't run at all (see docs/PERFORMANCE.md).
 *
 * @package EncoreLive
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wp_cache_enable' ) ) {
	echo "WP Super Cache is not active; skipping.\n";
	return;
}

if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
	wp_cache_disable();
	echo "WP_CACHE is off in .env: page cache disabled.\n";
	return;
}

wp_cache_verify_config_file();
wp_cache_enable();
wp_super_cache_enable();
wp_cache_create_advanced_cache();

// Permalinks end in "/" (/%postname%/). WP Super Cache only serves a cached file
// when this matches; the settings screen sets it automatically, enabling from
// code does not. Without it every URL except "/" was regenerated on each request
// (found while measuring Phase 7: TTFB stayed ~300 ms with the cache "on").
wp_cache_setting( 'wp_cache_slash_check', 1 );
// Debug logging off.
wp_cache_setting( 'wp_super_cache_debug', 0 );
wp_cache_setting( 'wp_cache_debug_to_file', 0 );

// 30 minutes: short enough that anything we fail to purge self-corrects quickly.
wp_cache_setting( 'cache_max_time', 1800 );
// Simple (PHP) mode locally; Nginx rewrites are configured on the server instead.
wp_cache_setting( 'wp_cache_mod_rewrite', 0 );
// Never serve cached pages to logged-in users or commenters.
wp_cache_setting( 'wp_cache_not_logged_in', 2 );
// Cart, checkout and account set DONOTCACHEPAGE in WooCommerce; also refuse to
// cache anyone with items in their cart, so mini-cart counts are never shared.
wp_cache_setting( 'wpsc_rejected_cookies', array( 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_' ) );

echo 'WP Super Cache enabled (WP_CACHE=' . ( defined( 'WP_CACHE' ) && WP_CACHE ? 'true' : 'false' ) . ").\n";
