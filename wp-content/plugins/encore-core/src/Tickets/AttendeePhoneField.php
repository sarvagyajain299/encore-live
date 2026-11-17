<?php
/**
 * Required "attendee mobile" field at checkout.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Contracts\Service;
use EncoreCore\Support\PhoneNumber;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The checkout page uses the WooCommerce Checkout BLOCK. Block checkout
 * ignores the classic woocommerce_checkout_fields filter, so the field is
 * registered with the Additional Checkout Fields API (WooCommerce 8.9+):
 * WooCommerce renders it, sends it through the Store API, runs our
 * sanitize/validate hooks server-side, and saves it to order meta. It also
 * shows it in the admin order screen and emails.
 */
final class AttendeePhoneField implements Service {

	public const ID = 'encore/attendee-phone';

	/** Where WooCommerce stores "order"-location fields. */
	public const ORDER_META_KEY = '_wc_other/encore/attendee-phone';

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'woocommerce_init', array( $this, 'register_field' ) );
		add_filter( 'woocommerce_sanitize_additional_field', array( $this, 'sanitize' ), 10, 2 );
		add_action( 'woocommerce_validate_additional_field', array( $this, 'validate' ), 10, 3 );
	}

	/**
	 * Register the field with WooCommerce.
	 */
	public function register_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'         => self::ID,
				'label'      => __( 'Attendee mobile number (UAE)', 'encore-core' ),
				'location'   => 'order',
				'type'       => 'text',
				'required'   => true,
				// WooCommerce allows only a short list of attributes here (inputmode is not one of them).
				'attributes' => array(
					'autocomplete' => 'tel',
					'title'        => __( 'For example 050 123 4567', 'encore-core' ),
				),
			)
		);
	}

	/**
	 * Store the canonical +971 form.
	 *
	 * @param mixed  $value Submitted value.
	 * @param string $key   Field ID.
	 * @return mixed
	 */
	public function sanitize( $value, $key ) {
		if ( self::ID !== $key ) {
			return $value;
		}
		return PhoneNumber::normalize_uae( $value ) ?? sanitize_text_field( (string) $value );
	}

	/**
	 * Reject anything that is not a UAE mobile or landline.
	 *
	 * @param WP_Error $errors Errors collector.
	 * @param string   $key    Field ID.
	 * @param mixed    $value  Sanitized value.
	 */
	public function validate( WP_Error $errors, $key, $value ): void {
		if ( self::ID === $key && null === PhoneNumber::normalize_uae( $value ) ) {
			$errors->add( 'encore_invalid_phone', __( 'Please enter a valid UAE phone number, for example 050 123 4567.', 'encore-core' ) );
		}
	}
}
