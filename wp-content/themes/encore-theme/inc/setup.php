<?php
/**
 * Theme supports, menus and image sizes.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare what the theme supports. Runs on after_setup_theme, the hook
 * WordPress provides for exactly this.
 */
function encore_theme_setup(): void {
	load_theme_textdomain( 'encore-theme', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );             // WordPress (and Yoast) print <title>.
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'woocommerce' );
	add_theme_support( 'yoast-seo-breadcrumbs' ); // Yoast outputs breadcrumbs + BreadcrumbList schema.
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 48,
			'width'       => 200,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'encore-theme' ),
			'footer'  => __( 'Footer navigation', 'encore-theme' ),
		)
	);

	// 16:10 card and 16:9 hero. Hard crop keeps every card the same shape (no layout shift).
	add_image_size( 'encore-card', 720, 450, true );
	// Same 16:10 ratio, so WordPress lists it in the card's srcset: small screens and
	// 2-column grids download ~4x fewer pixels (Lighthouse "properly size images").
	add_image_size( 'encore-card-sm', 360, 225, true );
	// TODO(sarvagya): stop generating sizes the theme never displays ('1536x1536', '2048x2048').
	// Hint: filter 'intermediate_image_sizes_advanced', unset those keys, return the array.
	// Saves disk space and upload time; check with `wp media regenerate --yes` and count files.
	add_image_size( 'encore-hero', 1600, 900, true );
}
add_action( 'after_setup_theme', 'encore_theme_setup' );

/**
 * Max width for embeds and full-size images inside content.
 */
function encore_theme_content_width(): void {
	$GLOBALS['content_width'] = 760; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the documented way to set content width.
}
add_action( 'after_setup_theme', 'encore_theme_content_width', 0 );
