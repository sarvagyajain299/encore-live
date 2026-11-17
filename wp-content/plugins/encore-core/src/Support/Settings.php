<?php
/**
 * Plugin settings: one option row, typed getters, a sanitizer.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * All settings live in ONE autoloaded option (an array). WordPress loads every
 * autoloaded option in a single query per request, so one row beats many.
 */
final class Settings {

	public const OPTION = 'encore_settings';

	public const DEFAULTS = array(
		'max_tickets_per_order' => 6,
		'gtm_container_id'      => '',
	);

	/**
	 * Read one setting, falling back to its default.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return $saved[ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}

	/**
	 * Per-order ticket limit for one show.
	 */
	public static function max_tickets_per_order(): int {
		return (int) self::get( 'max_tickets_per_order' );
	}

	/**
	 * Sanitize the whole settings array (register_setting's sanitize_callback).
	 * Unknown keys are dropped; invalid values fall back to defaults.
	 *
	 * @param mixed $input Submitted values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = self::DEFAULTS;

		$max = Sanitizer::id( $input['max_tickets_per_order'] ?? '' );
		if ( $max >= 1 && $max <= 20 ) {
			$clean['max_tickets_per_order'] = $max;
		}
		// TODO(sarvagya): tell the admin when their value was rejected instead of silently resetting it.
		// Hint: in an else branch call add_settings_error( self::OPTION, 'encore_max_tickets',
		// __( 'Max tickets per order must be between 1 and 20.', 'encore-core' ) ). WordPress shows
		// it above the form automatically on options pages. Guard with function_exists() so the
		// unit tests (no WordPress loaded) keep passing, or stub it with Brain Monkey.

		$gtm = strtoupper( trim( is_string( $input['gtm_container_id'] ?? null ) ? $input['gtm_container_id'] : '' ) );
		if ( 1 === preg_match( '/^GTM-[A-Z0-9]{4,10}$/', $gtm ) ) {
			$clean['gtm_container_id'] = $gtm;
		}

		return $clean;
	}
}
