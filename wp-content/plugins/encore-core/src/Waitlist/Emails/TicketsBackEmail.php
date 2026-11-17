<?php
/**
 * "Tickets are back".
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * The priority email: a signed link that puts the tickets in the cart,
 * valid for the claim window.
 */
final class TicketsBackEmail extends WaitlistEmail {

	public const ID = EmailRegistry::TICKETS_BACK;

	/**
	 * Identity.
	 */
	public function __construct() {
		$this->id          = self::ID;
		$this->title       = __( 'Waitlist: tickets are back', 'encore-core' );
		$this->description = __( 'Priority email sent to the next fans in line when tickets return. Contains a time-limited claim link.', 'encore-core' );
		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return __( 'Tickets are back for {show_title}', 'encore-core' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return __( 'Your tickets are waiting', 'encore-core' );
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
				sprintf( __( 'Good news %s: tickets you asked for are available again.', 'encore-core' ), $vars['name'] ?? '' ),
				/* translators: %s: date and time the claim expires. */
				sprintf( __( 'Use the button below to put them in your cart. Your priority link works until %s.', 'encore-core' ), $vars['claim_expires'] ?? '' ),
				__( 'Tickets are not held: if you wait, others may buy them first.', 'encore-core' ),
			),
			'button_url'   => (string) ( $vars['claim_url'] ?? '' ),
			'button_label' => __( 'Claim my tickets', 'encore-core' ),
		);
	}
}
