<?php
/**
 * Date helpers.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Show times are stored as site-local strings ('2026-12-01 20:00:00'). These
 * helpers turn them into timezone-aware objects, so comparisons and the ISO 8601
 * output used for schema (+04:00 for Dubai) are always correct.
 */
final class Dates {

	/**
	 * Parse a stored meta value. Returns null for empty or invalid values.
	 *
	 * @param string            $value    Stored 'Y-m-d H:i:s' string.
	 * @param DateTimeZone|null $timezone Timezone; defaults to the site's (wp_timezone()).
	 */
	public static function from_meta( string $value, ?DateTimeZone $timezone = null ): ?DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!' . Sanitizer::DATETIME_FORMAT, $value, $timezone ?? wp_timezone() );
		return false === $date ? null : $date;
	}

	/**
	 * The current time in the site's timezone.
	 */
	public static function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}
