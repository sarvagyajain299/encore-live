<?php
/**
 * Decides who to email when tickets come back, and sends it.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Waitlist\Emails\EmailRegistry;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Flow for one tier:
 *   available = stock − tickets in OPEN claim windows (soft reservation)
 *   ids       = BatchSelector( FIFO waiting queue, available )
 *   for each: waiting → notified (transaction), claim window = now + 24h,
 *             email a signed claim link, schedule the expiry job.
 *
 * There is NO hard stock hold. A notified fan races everyone else (and
 * earlier-notified fans) at checkout. Open claims are subtracted from
 * "available" so we don't email more people than there are tickets, but
 * nothing stops a non-waitlisted buyer taking them first. See the interview
 * notes for the trade-off and how to add real holds.
 *
 * Concurrency: a MySQL named lock (GET_LOCK) per tier makes two simultaneous
 * jobs for the same tier run one after the other, so both can't compute
 * the same "available" and double-notify.
 */
final class WaitlistNotifier {

	public const CLAIM_HOURS = 24;

	/**
	 * Create with collaborators.
	 *
	 * @param WaitlistRepository $repo     Data.
	 * @param BatchSelector      $selector FIFO rule.
	 * @param Links              $links    Signed links.
	 */
	public function __construct(
		private readonly WaitlistRepository $repo,
		private readonly BatchSelector $selector,
		private readonly Links $links
	) {}

	/**
	 * Notify the next fans for a tier. Action Scheduler job.
	 *
	 * @param int    $variation_id Tier variation.
	 * @param string $actor        Who triggered it.
	 * @return int Fans notified.
	 */
	public function notify_next( $variation_id, string $actor = 'system' ): int {
		global $wpdb;
		$variation_id = (int) $variation_id;
		$lock         = 'encore_wl_' . $variation_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- named locks have no WordPress API.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return 0;
		}

		try {
			$available = $this->available( $variation_id );
			if ( $available <= 0 ) {
				return 0;
			}

			$notified = 0;
			foreach ( $this->selector->select( $this->repo->queue( $variation_id ), $available ) as $entry_id ) {
				if ( $this->notify( $entry_id, $actor, 'restock' ) ) {
					++$notified;
				}
			}
			return $notified;
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Notify one entry (also used by the admin "Notify now" action).
	 *
	 * @param int    $entry_id Entry.
	 * @param string $actor    Who.
	 * @param string $context  Why.
	 */
	public function notify( int $entry_id, string $actor, string $context ): bool {
		$expires = time() + (int) apply_filters( 'encore_waitlist_claim_hours', self::CLAIM_HOURS ) * HOUR_IN_SECONDS;

		$ok = $this->repo->transition(
			$entry_id,
			Status::Notified,
			$actor,
			$context,
			array(
				'notified_at'      => gmdate( 'Y-m-d H:i:s' ),
				'claim_expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
			),
			Status::Waiting
		);
		if ( ! $ok ) {
			return false;
		}

		$entry = (array) $this->repo->find( $entry_id );
		EmailRegistry::send(
			EmailRegistry::TICKETS_BACK,
			(string) $entry['email'],
			self::email_vars( $entry, $this->links ) + array(
				'claim_url'     => $this->links->claim_url( $entry_id, $expires ),
				'claim_expires' => wp_date( 'l j F, g:i a', $expires ),
			)
		);
		Scheduler::schedule_claim_expiry( $entry_id, $expires );

		return true;
	}

	/**
	 * Claim window lapsed: expire it and offer the tickets to the next fans.
	 *
	 * @param int $entry_id Entry.
	 */
	public function expire_claim( $entry_id ): void {
		$entry = $this->repo->find( (int) $entry_id );
		if ( ! $entry ) {
			return;
		}
		if ( $this->repo->transition( (int) $entry_id, Status::Expired, 'system', 'claim window lapsed', array(), Status::Notified ) ) {
			$this->notify_next( (int) $entry['variation_id'] );
		}
	}

	/**
	 * Unconfirmed after 48 h: expire.
	 *
	 * @param int $entry_id Entry.
	 */
	public function expire_pending( $entry_id ): void {
		$this->repo->transition( (int) $entry_id, Status::Expired, 'system', 'not confirmed within 48 hours', array( 'token_hash' => '' ), Status::PendingConfirmation );
	}

	/**
	 * Hourly safety net: catch anything a single job missed (e.g. a failed run).
	 */
	public function sweep(): void {
		foreach ( $this->repo->lapsed_claims() as $row ) {
			$this->expire_claim( (int) $row['id'] );
		}
		foreach ( $this->repo->stale_pending( Scheduler::PENDING_TTL ) as $id ) {
			$this->expire_pending( $id );
		}
	}

	/**
	 * Tickets that can be offered right now.
	 *
	 * @param int $variation_id Tier variation.
	 */
	public function available( int $variation_id ): int {
		$variation = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ) : null;
		if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_in_stock() ) {
			return 0;
		}
		// Unmanaged stock = effectively unlimited; offer to a generous batch.
		$stock = $variation->managing_stock() ? (int) $variation->get_stock_quantity() : 50;
		return $stock - $this->repo->open_claims_qty( $variation_id );
	}

	/**
	 * Common email variables for an entry.
	 *
	 * @param array<string, mixed> $entry Entry row.
	 * @param Links                $links Links.
	 * @return array<string, mixed>
	 */
	public static function email_vars( array $entry, Links $links ): array {
		$show = function_exists( 'encore_get_show' ) ? encore_get_show( (int) $entry['show_id'] ) : null;
		return array(
			'name'            => (string) $entry['name'],
			'show_title'      => $show ? $show->title : '',
			'show_url'        => $show ? $show->url : home_url( '/' ),
			'show_date'       => $show ? encore_format_show_date( $show, 'l j F Y' ) : '',
			'tier'            => (string) $entry['tier'],
			'qty'             => (string) $entry['qty'],
			'unsubscribe_url' => $links->unsubscribe_url( (int) $entry['id'] ),
		);
	}
}
