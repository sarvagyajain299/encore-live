<?php
/**
 * Waitlist entry statuses and the allowed transitions between them.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * A small state machine. Every status change goes through
 * can_transition_to(), so impossible moves (e.g. converted -> waiting, or
 * notifying someone who never confirmed) are rejected in one place.
 *
 * Flow:
 *   pending_confirmation ──confirm──▶ waiting ──restock──▶ notified ──buys──▶ converted
 *          │                             │                    │
 *          └─48h──▶ expired ◀────────────┼────24h claim lapses┘
 *                     │                  └──buys without link──▶ converted
 *   any non-final ──unsubscribe──▶ unsubscribed
 *   expired / unsubscribed ──joins again──▶ pending_confirmation (fresh double opt-in)
 */
enum Status: string {

	case PendingConfirmation = 'pending_confirmation';
	case Waiting             = 'waiting';
	case Notified            = 'notified';
	case Converted           = 'converted';
	case Expired             = 'expired';
	case Unsubscribed        = 'unsubscribed';

	/**
	 * Allowed next statuses.
	 *
	 * @return Status[]
	 */
	public function allowed_next(): array {
		// PHPCompatibility does not yet know $this is valid inside enum methods.
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
			self::PendingConfirmation => array( self::Waiting, self::Expired, self::Unsubscribed ),
			self::Waiting             => array( self::Notified, self::Converted, self::Unsubscribed ),
			self::Notified            => array( self::Converted, self::Expired, self::Unsubscribed ),
			self::Expired,
			self::Unsubscribed        => array( self::PendingConfirmation ),
			self::Converted           => array(),
		};
	}

	/**
	 * Is moving to $next allowed?
	 *
	 * @param Status $next Target status.
	 */
	public function can_transition_to( Status $next ): bool {
		return in_array( $next, $this->allowed_next(), true ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}

	/**
	 * Still in the queue (counts towards "waiting" numbers and positions)?
	 */
	public function is_active(): bool {
		return in_array( $this, array( self::PendingConfirmation, self::Waiting, self::Notified ), true ); // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
	}

	/**
	 * Admin label.
	 */
	public function label(): string {
		return match ( $this ) { // phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
			self::PendingConfirmation => __( 'Pending confirmation', 'encore-core' ),
			self::Waiting             => __( 'Waiting', 'encore-core' ),
			self::Notified            => __( 'Notified', 'encore-core' ),
			self::Converted           => __( 'Converted', 'encore-core' ),
			self::Expired             => __( 'Expired', 'encore-core' ),
			self::Unsubscribed        => __( 'Unsubscribed', 'encore-core' ),
		};
	}
}
