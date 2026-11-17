<?php
/**
 * Elementor compatibility.
 *
 * Marketing pages are built in Elementor; show, artist and venue pages stay
 * theme-rendered (fast, consistent, structured data under our control).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register header and footer as Elementor theme locations.
 *
 * With Elementor Pro's Theme Builder, a designer can replace the header or
 * footer; without it, header.php/footer.php render as normal.
 *
 * @param object $manager Elementor\ThemeManager\Locations_Manager.
 */
function encore_theme_elementor_locations( $manager ): void {
	$manager->register_location( 'header' );
	$manager->register_location( 'footer' );
}
add_action( 'elementor/theme/register_locations', 'encore_theme_elementor_locations' );

/**
 * Render an Elementor location if one is assigned.
 *
 * @param string $location 'header' or 'footer'.
 * @return bool True when Elementor rendered it (so the theme should not).
 */
function encore_theme_elementor_location( string $location ): bool {
	return function_exists( 'elementor_theme_do_location' ) && elementor_theme_do_location( $location );
}
