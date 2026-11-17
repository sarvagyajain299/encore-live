<?php
/**
 * Normalises listing inputs from shortcodes and widgets.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Support\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode attributes and Elementor settings arrive as loose strings typed
 * by editors ("Dubai", " 6 ", "Live Music"). This turns them into the filter
 * array ShowQueryArgs expects, and builds a stable cache key from the result.
 */
final class ListFilters {

	public const MAX_LISTING = 24;

	/**
	 * From [encore_upcoming_shows] / widget settings.
	 *
	 * @param array<string, mixed> $atts Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function from_atts( array $atts ): array {
		$limit = Sanitizer::id( is_string( $atts['limit'] ?? null ) ? trim( $atts['limit'] ) : ( $atts['limit'] ?? 6 ) );

		$filters = array(
			'limit' => $limit > 0 ? min( $limit, self::MAX_LISTING ) : 6,
			'city'  => self::slug( $atts['city'] ?? '' ),
			'genre' => self::slug( $atts['genre'] ?? '' ),
		);

		$artist = Sanitizer::id( $atts['artist_id'] ?? 0 );
		if ( $artist ) {
			$filters['artist'] = $artist;
		}

		return array_filter( $filters, static fn( $value ): bool => '' !== $value && 0 !== $value );
	}

	/**
	 * Cache key for a filter set. Sorting makes [city=x genre=y] and
	 * [genre=y city=x] share one cache entry; the version lets us invalidate
	 * every entry at once by bumping a number.
	 *
	 * @param array<string, mixed> $filters Normalised filters.
	 * @param int                  $version Cache version.
	 */
	public static function cache_key( array $filters, int $version ): string {
		ksort( $filters );
		return 'encore_list_' . $version . '_' . md5( (string) wp_json_encode( $filters ) );
	}

	/**
	 * "Live Music" or "live-music" both become "live-music".
	 *
	 * @param mixed $value Raw.
	 */
	private static function slug( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$slug = strtolower( trim( $value ) );
		$slug = (string) preg_replace( '/[\s_]+/', '-', $slug );
		return (string) preg_replace( '/[^a-z0-9-]/', '', $slug );
	}
}
