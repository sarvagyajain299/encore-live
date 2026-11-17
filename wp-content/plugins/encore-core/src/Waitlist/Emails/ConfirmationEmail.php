<?php
/**
 * "Confirm your place" (double opt-in).
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Sent right after sign-up. The entry stays pending until the link is
 * clicked, which proves the address belongs to the person who typed it.
 */
final class ConfirmationEmail extends WaitlistEmail {

	public const ID = EmailRegistry::CONFIRMATION;

	/**
	 * Identity shown in WooCommerce → Settings → Emails.
	 */
	public function __construct() {
		$this->id          = self::ID;
		$this->title       = __( 'Waitlist: confirm your place', 'encore-core' );
		$this->description = __( 'Double opt-in email sent when a fan joins a waitlist. The fan is only queued after clicking the link.', 'encore-core' );
		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return __( 'Confirm your place on the {show_title} waitlist', 'encore-core' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return __( 'One click to join the waitlist', 'encore-core' );
	}

	/**
	 * Body.
	 *
	 * @param array<string, mixed> $vars Send data.
	 * @return array{intro: string[], button_url: string, button_label: string}
	 */
	protected function body( array $vars ): array {
		return array(
			'intro'        => array(
				/* translators: %s: fan name. */
				sprintf( __( 'Hi %s,', 'encore-core' ), $vars['name'] ?? '' ),
				__( 'Please confirm your email to take your place in the queue. Places are given in the order they are confirmed.', 'encore-core' ),
				__( 'This link works once and expires in 48 hours. If you didn’t ask to join, you can ignore this email.', 'encore-core' ),
			),
			'button_url'   => (string) ( $vars['confirm_url'] ?? '' ),
			'button_label' => __( 'Confirm my place', 'encore-core' ),
		);
	}
}
