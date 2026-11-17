<?php
/**
 * Validated waitlist sign-up input.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Support\PhoneNumber;

defined( 'ABSPATH' ) || exit;

/**
 * Both entry points (REST for JS, admin-post for no-JS) build one of these,
 * so validation rules exist exactly once.
 */
final class JoinRequest {

	public const MAX_QTY = 4;

	/**
	 * Use JoinRequest::from_array().
	 *
	 * @param int    $show_id      Show ID.
	 * @param int    $variation_id Tier variation ID.
	 * @param string $name         Name.
	 * @param string $email        Lower-cased email.
	 * @param string $phone        E.164 phone or ''.
	 * @param int    $qty          Tickets wanted (1–4).
	 */
	private function __construct(
		public readonly int $show_id,
		public readonly int $variation_id,
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly int $qty
	) {}

	/**
	 * Validate raw input.
	 *
	 * @param array<string, mixed> $input Raw fields.
	 * @return JoinRequest|array<string, string> The request, or field => error message.
	 */
	public static function from_array( array $input ): JoinRequest|array {
		$errors = array();

		$show_id      = self::int( $input['show_id'] ?? 0 );
		$variation_id = self::int( $input['variation_id'] ?? 0 );
		if ( $show_id < 1 || $variation_id < 1 ) {
			$errors['variation_id'] = __( 'Please choose a ticket tier.', 'encore-core' );
		}

		$name = is_string( $input['name'] ?? null ) ? trim(
			preg_replace(
				'/\s+/',
				' ',
				strip_tags( $input['name'] ) // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure class, runs without WordPress in unit tests.
			)
		) : '';
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors['name'] = __( 'Please enter your name (up to 100 characters).', 'encore-core' );
		}

		$email = is_string( $input['email'] ?? null ) ? strtolower( trim( $input['email'] ) ) : '';
		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) || strlen( $email ) > 190 ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'encore-core' );
		}

		$phone = '';
		$raw   = is_string( $input['phone'] ?? null ) ? trim( $input['phone'] ) : '';
		if ( '' !== $raw ) {
			$phone = (string) PhoneNumber::normalize_uae( $raw );
			if ( '' === $phone ) {
				$errors['phone'] = __( 'Please enter a valid UAE phone number, or leave it empty.', 'encore-core' );
			}
		}

		$qty = self::int( $input['qty'] ?? 0 );
		if ( $qty < 1 || $qty > self::MAX_QTY ) {
			$errors['qty'] = __( 'You can ask for 1 to 4 tickets.', 'encore-core' );
		}

		if ( true !== filter_var( $input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
			$errors['consent'] = __( 'Please agree to be emailed about this show.', 'encore-core' );
		}

		return $errors ? $errors : new self( $show_id, $variation_id, $name, $email, $phone, $qty );
	}

	/**
	 * Strict positive integer.
	 *
	 * @param mixed $value Raw.
	 */
	private static function int( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0;
	}
}
