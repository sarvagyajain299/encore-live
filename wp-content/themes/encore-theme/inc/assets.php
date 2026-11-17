<?php
/**
 * Styles and scripts.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue front-end assets.
 *
 * Version = file modification time: browsers cache aggressively, and the
 * URL changes (?ver=...) the moment the file does, so no stale CSS after a deploy.
 */
function encore_theme_assets(): void {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	wp_enqueue_style( 'encore-main', $uri . '/assets/css/main.css', array(), (string) filemtime( $dir . '/assets/css/main.css' ) );

	if ( is_rtl() ) {
		wp_enqueue_style( 'encore-rtl', $uri . '/rtl.css', array( 'encore-main' ), (string) filemtime( $dir . '/rtl.css' ) );
	}

	// Vanilla JS, deferred: downloads in parallel, runs after HTML parsing, never blocks rendering.
	wp_enqueue_script(
		'encore-main',
		$uri . '/assets/js/main.js',
		array(),
		(string) filemtime( $dir . '/assets/js/main.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}
add_action( 'wp_enqueue_scripts', 'encore_theme_assets' );

/**
 * Swap no-js for js before first paint. Waiting for the deferred main.js
 * would flash the open mobile menu, so this one line runs inline in <head>.
 */
function encore_theme_js_class(): void {
	wp_print_inline_script_tag( "document.documentElement.classList.replace('no-js','js');" );
}
add_action( 'wp_head', 'encore_theme_js_class', 1 );
