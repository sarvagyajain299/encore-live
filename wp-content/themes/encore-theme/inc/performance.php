<?php
/**
 * Front-end performance (Phase 7). Each change is measured in docs/PERFORMANCE.md.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is this a WooCommerce page that genuinely needs WooCommerce's front-end assets?
 */
function encore_theme_is_shop_page(): bool {
	return function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );
}

/**
 * 1. Critical CSS inline + 2. self-hosted font faces.
 *
 * Registered as a style with no file (src = false) and the CSS attached with
 * wp_add_inline_style(): WordPress prints it as a <style> block in <head>,
 * other code can still dequeue it by handle, and no stylesheet request
 * blocks the first paint.
 */
function encore_theme_critical_css(): void {
	$dir   = get_template_directory();
	$fonts = get_template_directory_uri() . '/assets/fonts/';

	// font-display: swap shows text immediately in the fallback font, then
	// swaps; text is never invisible while the font downloads.
	$faces = '';
	foreach ( array( 800, 900 ) as $weight ) {
		$faces .= sprintf(
			'@font-face{font-family:"Archivo";font-style:normal;font-weight:%1$d;font-display:swap;src:url(%2$s) format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;}',
			$weight,
			esc_url( $fonts . 'archivo-latin-' . $weight . '-normal.woff2' )
		);
	}

	wp_register_style( 'encore-critical', false, array(), ENCORE_THEME_VERSION );
	wp_enqueue_style( 'encore-critical' );
	// Theme file, not user input. file_get_contents is fine for a local file.
	wp_add_inline_style( 'encore-critical', $faces . (string) file_get_contents( $dir . '/assets/css/critical.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
}
add_action( 'wp_enqueue_scripts', 'encore_theme_critical_css', 5 );

/**
 * 3. Preload the heading font used by the hero/LCP heading, so it arrives with
 * the HTML instead of being discovered only after CSS is parsed.
 */
function encore_theme_preload_font(): void {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_template_directory_uri() . '/assets/fonts/archivo-latin-900-normal.woff2' )
	);
}
add_action( 'wp_head', 'encore_theme_preload_font', 2 );

/**
 * 4. Load the deferred stylesheet without blocking rendering: preload it,
 * switch it to a stylesheet once loaded, with a <noscript> fallback.
 *
 * @param string $tag    The <link> tag.
 * @param string $handle Style handle.
 * @param string $href   URL.
 */
function encore_theme_async_css( string $tag, string $handle, string $href ): string {
	if ( 'encore-main' !== $handle ) {
		return $tag;
	}
	// The style IS enqueued (inc/assets.php); this only rewrites its tag.
	// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
	return sprintf(
		'<link rel="preload" id="encore-main-css" href="%1$s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n" .
		'<noscript><link rel="stylesheet" href="%1$s"></noscript>' . "\n",
		esc_url( $href )
	);
	// phpcs:enable
}
add_filter( 'style_loader_tag', 'encore_theme_async_css', 10, 3 );

/**
 * 5. Drop WooCommerce's front-end CSS and JS outside shop pages.
 *
 * The show pages don't use them: the tier selector is a plain form and the
 * theme is vanilla JS. Removing wc-add-to-cart and woocommerce also removes
 * their only dependency on jQuery (~35 KB render-blocking on every page).
 * WooCommerce's order-attribution scripts are KEPT: they record the landing
 * page and source on the first visit, which is a business requirement.
 */
function encore_theme_trim_woocommerce_assets(): void {
	if ( encore_theme_is_shop_page() ) {
		return;
	}
	foreach ( array( 'wc-add-to-cart', 'woocommerce', 'wc-cart-fragments' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
	wp_dequeue_style( 'woocommerce-inline' );
}
add_action( 'wp_enqueue_scripts', 'encore_theme_trim_woocommerce_assets', 99 );

/**
 * WooCommerce's three classic stylesheets, only where WooCommerce renders markup.
 *
 * @param array<string, array<string, mixed>> $styles Styles.
 * @return array<string, array<string, mixed>>
 */
function encore_theme_woocommerce_styles( $styles ): array {
	return encore_theme_is_shop_page() ? (array) $styles : array();
}
add_filter( 'woocommerce_enqueue_styles', 'encore_theme_woocommerce_styles' );

// TODO(sarvagya): measure whether 'global-styles' and 'classic-theme-styles' can go.
// They are core inline styles for block-theme presets this classic theme barely uses.
// Hint: wp_dequeue_style() both at priority 100 on wp_enqueue_scripts, then run
// .\scripts\lighthouse.ps1 -Label experiment and keep the change ONLY if the numbers
// improve and buttons/blocks inside page content still look right.

/**
 * 6. Only the CSS for blocks actually on the page (instead of the whole 19 KB
 * block library). Classic themes get the full library unless they opt in.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * 7. Remove the emoji polyfill (an inline script + 6 KB request on every page).
 * Every supported browser renders emoji natively.
 */
function encore_theme_disable_emoji(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}
add_action( 'init', 'encore_theme_disable_emoji' );

/**
 * 8. WebP for every generated image size (typically 25–35% smaller than JPEG
 * at the same visual quality). Applies when sizes are generated: new uploads,
 * or `wp media regenerate`.
 *
 * @param array<string, string> $formats Source mime => output mime.
 * @return array<string, string>
 */
function encore_theme_webp_output( $formats ): array {
	$formats['image/jpeg'] = 'image/webp';
	return $formats;
}
add_filter( 'image_editor_output_format', 'encore_theme_webp_output' );
