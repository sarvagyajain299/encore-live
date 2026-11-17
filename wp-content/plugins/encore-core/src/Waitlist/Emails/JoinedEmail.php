<?php
/**
 * "You're on the list".
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Sent after confirmation, with the fan's position in line.
 */
final class JoinedEmail extends WaitlistEmail {

	public const ID = EmailRegistry::JOINED;

	/**
	 * Identity.
	 */
	public function __construct() {
		$this->id          = self::ID;
		$this->title       = __( 'Waitlist: you’re on the list', 'encore-core' );
		$this->description = __( 'Sent when a fan confirms their waitlist place. Includes their position.', 'encore-core' );
		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject() {
		return __( 'You’re on the list for {show_title}', 'encore-core' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading() {
		return __( 'You’re on the list', 'encore-core' );
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
				sprintf( __( 'Thanks %s, your place is confirmed.', 'encore-core' ), $vars['name'] ?? '' ),
				/* translators: %d: position in queue. */
				sprintf( __( 'You are number %d in line for this tier.', 'encore-core' ), (int) ( $vars['position'] ?? 0 ) ),
				__( 'If tickets come back, we’ll email fans in order. You’ll then have 24 hours to buy before the offer passes to the next person.', 'encore-core' ),
			),
			'button_url'   => (string) ( $vars['show_url'] ?? '' ),
			'button_label' => __( 'View the show', 'encore-core' ),
		);
	}
}
