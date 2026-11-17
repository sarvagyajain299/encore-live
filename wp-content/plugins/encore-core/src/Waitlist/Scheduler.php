<?php
/**
 * Background jobs via Action Scheduler.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler (bundled with WooCommerce) is a persistent job queue in
 * MySQL. Compared with plain WP-Cron it gives retries, logs, a UI
 * (Tools → Scheduled Actions), exact-time single actions with arguments, and
 * batch processing, all of which suit "email the next fans in 24 hours".
 *
 * Jobs:
 *  - encore_waitlist_notify( variation_id )  async, after a restock
 *  - encore_waitlist_claim_expired( id )     at claim_expires_at
 *  - encore_waitlist_expire_pending( id )    48 h after sign-up
 *  - encore_waitlist_sweep()                 hourly safety net for anything missed
 */
final class Scheduler implements Service {

	public const GROUP        = 'encore-waitlist';
	public const NOTIFY       = 'encore_waitlist_notify';
	public const CLAIM_EXPIRE = 'encore_waitlist_claim_expired';
	public const PENDING      = 'encore_waitlist_expire_pending';
	public const SWEEP        = 'encore_waitlist_sweep';
	public const PENDING_TTL  = 48; // Hours.

	/**
	 * Wire job hooks to the notifier.
	 *
	 * @param WaitlistNotifier $notifier Notifier.
	 */
	public function __construct( private readonly WaitlistNotifier $notifier ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( self::NOTIFY, array( $this->notifier, 'notify_next' ) );
		add_action( self::CLAIM_EXPIRE, array( $this->notifier, 'expire_claim' ) );
		add_action( self::PENDING, array( $this->notifier, 'expire_pending' ) );
		add_action( self::SWEEP, array( $this->notifier, 'sweep' ) );
		add_action( 'init', array( $this, 'schedule_sweep' ) );
	}

	/**
	 * Ensure the hourly sweep exists (idempotent).
	 */
	public function schedule_sweep(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::SWEEP, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + 300, HOUR_IN_SECONDS, self::SWEEP, array(), self::GROUP );
		}
	}

	/**
	 * Queue "notify next fans" for a tier, unless one is already pending.
	 *
	 * @param int $variation_id Tier variation.
	 */
	public static function enqueue_notify( int $variation_id ): void {
		$args = array( 'variation_id' => $variation_id );
		if ( function_exists( 'as_enqueue_async_action' ) && ! as_has_scheduled_action( self::NOTIFY, $args, self::GROUP ) ) {
			as_enqueue_async_action( self::NOTIFY, $args, self::GROUP );
		}
	}

	/**
	 * Expire a claim at a time.
	 *
	 * @param int $entry_id Entry.
	 * @param int $when     Unix time.
	 */
	public static function schedule_claim_expiry( int $entry_id, int $when ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $when, self::CLAIM_EXPIRE, array( 'entry_id' => $entry_id ), self::GROUP );
		}
	}

	/**
	 * Expire an unconfirmed entry after 48 h.
	 *
	 * @param int $entry_id Entry.
	 */
	public static function schedule_pending_expiry( int $entry_id ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + self::PENDING_TTL * HOUR_IN_SECONDS, self::PENDING, array( 'entry_id' => $entry_id ), self::GROUP );
		}
	}
}
