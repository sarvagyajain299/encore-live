<?php
/**
 * Template API: the functions the theme is allowed to call.
 *
 * The theme never reads meta keys or runs show queries itself. It calls these
 * functions (always behind function_exists(), so the theme degrades gracefully
 * if the plugin is off). This file is the contract between plugin and theme.
 *
 * @package EncoreCore
 */

use EncoreCore\Shows\ArchiveFilters;
use EncoreCore\Shows\Show;
use EncoreCore\Shows\ShowRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Shared repository instance (keeps the per-request cache).
 */
function encore_shows(): ShowRepository {
	static $repository = null;
	if ( null === $repository ) {
		$repository = new ShowRepository();
	}
	return $repository;
}

/**
 * Get a show's read model.
 *
 * @param int|WP_Post|null $post Post; defaults to the current post in the loop.
 */
function encore_get_show( int|WP_Post|null $post = null ): ?Show {
	$post = get_post( $post );
	return $post ? encore_shows()->find( $post ) : null;
}

/**
 * Query shows with the shared filter set (see ShowQueryArgs::build()).
 *
 * @param array<string, mixed> $filters Filters.
 */
function encore_query_shows( array $filters = array() ): WP_Query {
	return encore_shows()->query( $filters );
}

/**
 * Current archive filter values.
 *
 * @return array<string, string>
 */
function encore_archive_filters(): array {
	return ArchiveFilters::current();
}

/**
 * Format a price as plain text, e.g. "AED 95" or "AED 99.50".
 *
 * @param float $amount Amount.
 */
function encore_format_price( float $amount ): string {
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'AED';
	$decimals = floor( $amount ) === $amount ? 0 : 2;
	return $currency . ' ' . number_format_i18n( $amount, $decimals );
}

/**
 * Format a show's date for listings, e.g. "Fri 20 Nov 2026 · 8:00 pm".
 *
 * @param Show   $show   Show.
 * @param string $format date() format for the date part.
 */
function encore_format_show_date( Show $show, string $format = 'D j M Y' ): string {
	if ( ! $show->start ) {
		return __( 'Date to be announced', 'encore-core' );
	}
	return wp_date( $format, $show->start->getTimestamp() ) . ' · ' . wp_date( 'g:i a', $show->start->getTimestamp() );
}
