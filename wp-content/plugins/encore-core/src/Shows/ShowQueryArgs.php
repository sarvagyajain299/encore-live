<?php
/**
 * Builds WP_Query arguments for show listings.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use DateTimeImmutable;
use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * One builder behind every show listing: the archive page, shortcodes, the
 * REST API and the Elementor widget. Filters behave identically everywhere.
 *
 * Pure: takes plain filters and the current time, returns an array. No
 * database access, so it is fully unit-testable.
 */
final class ShowQueryArgs {

	public const DEFAULT_LIMIT = 12;
	public const MAX_LIMIT     = 50;

	/**
	 * Build WP_Query args.
	 *
	 * Supported filters (all optional, all untrusted):
	 *  - city   string  encore_city term slug
	 *  - genre  string  encore_genre term slug
	 *  - month  string  'YYYY-MM'
	 *  - from   string  'YYYY-MM-DD' inclusive
	 *  - to     string  'YYYY-MM-DD' inclusive
	 *  - artist int     artist post ID
	 *  - venue  int     venue post ID
	 *  - when   string  'upcoming' (default) | 'past' | 'all'
	 *  - limit  int     1..50 (default 12)
	 *  - page   int     >= 1
	 *  - paginate bool  false = skip the COUNT(*) query (no_found_rows)
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param DateTimeImmutable    $now     Current time in the site timezone.
	 * @return array<string, mixed>
	 */
	public function build( array $filters, DateTimeImmutable $now ): array {
		$when  = in_array( $filters['when'] ?? '', array( 'past', 'all' ), true ) ? $filters['when'] : 'upcoming';
		$limit = Sanitizer::id( $filters['limit'] ?? self::DEFAULT_LIMIT );
		$limit = $limit > 0 ? min( $limit, self::MAX_LIMIT ) : self::DEFAULT_LIMIT;
		$page  = max( 1, Sanitizer::id( $filters['page'] ?? 1 ) );

		$args = array(
			'post_type'           => PostTypes::SHOW,
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => empty( $filters['paginate'] ),
			// Sort by start time. Stored as 'Y-m-d H:i:s', so string order = time order.
			'meta_key'            => Keys::SHOW_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- required for chronological order.
			'orderby'             => 'meta_value',
			'order'               => 'past' === $when ? 'DESC' : 'ASC',
		);

		$meta_query = array();
		$now_string = $now->format( Sanitizer::DATETIME_FORMAT );
		if ( 'upcoming' === $when ) {
			$meta_query[] = $this->compare( Keys::SHOW_START, $now_string, '>=' );
		} elseif ( 'past' === $when ) {
			$meta_query[] = $this->compare( Keys::SHOW_START, $now_string, '<' );
		}

		$month = is_string( $filters['month'] ?? null ) ? $filters['month'] : '';
		if ( 1 === preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $month ) ) {
			$first        = DateTimeImmutable::createFromFormat( '!Y-m', $month, $now->getTimezone() );
			$meta_query[] = $this->between( Keys::SHOW_START, $first, $first->modify( 'last day of this month' ) );
		}

		$from = $this->date( $filters['from'] ?? '', $now );
		if ( $from ) {
			$meta_query[] = $this->compare( Keys::SHOW_START, $from->format( 'Y-m-d 00:00:00' ), '>=' );
		}
		$to = $this->date( $filters['to'] ?? '', $now );
		if ( $to ) {
			$meta_query[] = $this->compare( Keys::SHOW_START, $to->format( 'Y-m-d 23:59:59' ), '<=' );
		}

		$artist = Sanitizer::id( $filters['artist'] ?? 0 );
		if ( $artist ) {
			$meta_query[] = $this->compare( Keys::SHOW_ARTIST_INDEX, (string) $artist, '=' );
		}
		$venue = Sanitizer::id( $filters['venue'] ?? 0 );
		if ( $venue ) {
			$meta_query[] = $this->compare( Keys::SHOW_VENUE, (string) $venue, '=' );
		}

		if ( $meta_query ) {
			$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- core feature; see interview notes on scaling.
		}

		$tax_query = array();
		foreach ( array(
			'city'  => Taxonomies::CITY,
			'genre' => Taxonomies::GENRE,
		) as $filter => $taxonomy ) {
			$slug = is_string( $filters[ $filter ] ?? null ) ? $this->slug( $filters[ $filter ] ) : '';
			if ( '' !== $slug ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $slug,
				);
			}
		}
		if ( $tax_query ) {
			$args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- taxonomy filters are indexed.
		}

		return $args;
	}

	/**
	 * A meta comparison clause.
	 *
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 * @param string $compare Operator.
	 * @return array<string, string>
	 */
	private function compare( string $key, string $value, string $compare ): array {
		return array(
			'key'     => $key,
			'value'   => $value,
			'compare' => $compare,
		);
	}

	/**
	 * A BETWEEN clause covering whole days.
	 *
	 * @param string            $key   Meta key.
	 * @param DateTimeImmutable $first First day.
	 * @param DateTimeImmutable $last  Last day.
	 * @return array<string, mixed>
	 */
	private function between( string $key, DateTimeImmutable $first, DateTimeImmutable $last ): array {
		return array(
			'key'     => $key,
			'value'   => array( $first->format( 'Y-m-d 00:00:00' ), $last->format( 'Y-m-d 23:59:59' ) ),
			'compare' => 'BETWEEN',
		);
	}

	/**
	 * Parse a strict 'YYYY-MM-DD' date.
	 *
	 * @param mixed             $value Raw value.
	 * @param DateTimeImmutable $now   For the timezone.
	 */
	private function date( mixed $value, DateTimeImmutable $now ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $now->getTimezone() );
		return ( false !== $date && $date->format( 'Y-m-d' ) === $value ) ? $date : null;
	}

	/**
	 * Lowercase slug characters only (a-z, 0-9, hyphen).
	 *
	 * @param string $value Raw slug.
	 */
	private function slug( string $value ): string {
		return (string) preg_replace( '/[^a-z0-9-]/', '', strtolower( trim( $value ) ) );
	}
}
