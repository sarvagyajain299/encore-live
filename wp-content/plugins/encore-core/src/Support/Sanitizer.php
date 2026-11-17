<?php
/**
 * Input sanitizers for meta fields.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

use DateTimeImmutable;
use EncoreCore\Domain\ShowStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Every value a user can type passes through one of these before it is saved.
 *
 * Sanitizing means "coerce to a safe, expected shape". It is different from
 * validating ("reject if wrong") and from escaping ("make safe for output").
 * Each method accepts mixed input, because $_POST and REST payloads can hold
 * anything, and always returns the declared type.
 */
final class Sanitizer {

	/** Format used to store show datetimes: sortable as a string, site-local time. */
	public const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/** Allowed age restrictions (0 = all ages). */
	public const AGES = array( 0, 16, 18, 21 );

	/** Social networks an artist may link to. */
	public const SOCIAL_NETWORKS = array( 'website', 'instagram', 'tiktok', 'youtube', 'spotify' );

	/**
	 * Normalise a datetime to 'Y-m-d H:i:s', or '' when invalid.
	 * Accepts the HTML datetime-local format ('2026-12-01T20:00') as well.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function datetime( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( str_replace( 'T', ' ', $value ) );

		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $value );
			// Round-trip check rejects overflow such as 2026-02-31 (PHP would roll it to March).
			if ( false !== $date && $date->format( $format ) === $value ) {
				return $date->format( self::DATETIME_FORMAT );
			}
		}

		return '';
	}

	/**
	 * Normalise a time of day to 'H:i', or '' when invalid.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function time( mixed $value ): string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', trim( $value ) ) ) {
			return '';
		}
		return trim( $value );
	}

	/**
	 * A post ID: positive integer, otherwise 0.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function id( mixed $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}
		if ( is_string( $value ) && ctype_digit( trim( $value ) ) ) {
			return (int) trim( $value );
		}
		return 0;
	}

	/**
	 * A list of post IDs from an array or comma-separated string.
	 * Invalid entries and duplicates are dropped; order is kept.
	 *
	 * @param mixed $value Raw input.
	 * @return int[]
	 */
	public static function id_list( mixed $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array_filter( array_map( array( self::class, 'id' ), $value ) );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * A show status value; anything unknown falls back to on_sale.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function status( mixed $value ): string {
		$status = is_string( $value ) ? ShowStatus::tryFrom( $value ) : null;
		return ( $status ?? ShowStatus::OnSale )->value;
	}

	/**
	 * An age restriction from the allowed list, otherwise 0 (all ages).
	 *
	 * @param mixed $value Raw input.
	 */
	public static function age( mixed $value ): int {
		$age = self::id( $value );
		return in_array( $age, self::AGES, true ) ? $age : 0;
	}

	/**
	 * Venue capacity: 0 to 100,000 seats.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function capacity( mixed $value ): int {
		return min( 100000, self::id( $value ) );
	}

	/**
	 * Latitude in [-90, 90], rounded to 6 decimals (about 0.1 m). Invalid = 0.0.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function latitude( mixed $value ): float {
		return self::coordinate( $value, 90.0 );
	}

	/**
	 * Longitude in [-180, 180], rounded to 6 decimals. Invalid = 0.0.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function longitude( mixed $value ): float {
		return self::coordinate( $value, 180.0 );
	}

	/**
	 * Single-line text: strips tags, line breaks and extra whitespace.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function text( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Multi-line plain text: strips tags but keeps line breaks.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function textarea( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	/**
	 * An http(s) URL, otherwise ''. Blocks javascript: and data: URLs.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function url( mixed $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}
		return esc_url_raw( trim( $value ), array( 'http', 'https' ) );
	}

	/**
	 * Artist social links: only known networks, each a valid URL, empties dropped.
	 *
	 * @param mixed $value Raw input.
	 * @return array<string, string>
	 */
	public static function social_links( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$links = array();
		foreach ( self::SOCIAL_NETWORKS as $network ) {
			$url = self::url( $value[ $network ] ?? '' );
			if ( '' !== $url ) {
				$links[ $network ] = $url;
			}
		}
		return $links;
	}

	/**
	 * Shared coordinate logic.
	 *
	 * @param mixed $value Raw input.
	 * @param float $limit Absolute maximum.
	 */
	private static function coordinate( mixed $value, float $limit ): float {
		if ( ! is_numeric( $value ) ) {
			return 0.0;
		}
		$number = (float) $value;
		if ( $number < -$limit || $number > $limit ) {
			return 0.0;
		}
		return round( $number, 6 );
	}
}
