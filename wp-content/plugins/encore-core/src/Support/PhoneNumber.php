<?php
/**
 * UAE phone number normalisation.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts the ways people actually type UAE numbers and stores one canonical
 * E.164 form (+971501234567). One format makes duplicate detection and SMS
 * integrations reliable.
 *
 *   050 123 4567 | 0501234567 | +971 50 123 4567 | 00971501234567 | 971501234567
 *   04 123 4567 (Dubai landline) | +971 4 123 4567
 *
 * Mobile: 5 + network digit (0,2,4,5,6,8) + 7 digits.
 * Landline: area code 2,3,4,6,7,9 + 7 digits.
 */
final class PhoneNumber {

	/**
	 * Normalise to E.164, or null when it is not a valid UAE number.
	 *
	 * @param mixed $value Raw input.
	 */
	public static function normalize_uae( mixed $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		// Allow common separators only; anything else (letters, extra symbols) is invalid.
		if ( 1 !== preg_match( '/^[\d\s\-\+\(\)\.]{7,22}$/', trim( $value ) ) ) {
			return null;
		}

		$digits = (string) preg_replace( '/\D/', '', $value );

		// Strip the country code (00971 / 971) or the trunk prefix (0).
		if ( str_starts_with( $digits, '00971' ) ) {
			$national = substr( $digits, 5 );
		} elseif ( str_starts_with( $digits, '971' ) ) {
			$national = substr( $digits, 3 );
		} elseif ( str_starts_with( $digits, '0' ) ) {
			$national = substr( $digits, 1 );
		} else {
			return null;
		}

		$is_mobile   = 1 === preg_match( '/^5[024568]\d{7}$/', $national );
		$is_landline = 1 === preg_match( '/^[234679]\d{7}$/', $national );

		return ( $is_mobile || $is_landline ) ? '+971' . $national : null;
	}
}
