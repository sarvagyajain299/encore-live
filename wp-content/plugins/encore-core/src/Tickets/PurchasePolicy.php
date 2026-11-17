<?php
/**
 * Can this many tickets be bought for this show right now?
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use DateTimeImmutable;
use EncoreCore\Domain\ShowStatus;

defined( 'ABSPATH' ) || exit;

/**
 * Pure rules, checked server-side on every add-to-cart, every cart update and
 * again before checkout. The tier selector's client-side limits are only a
 * convenience; these checks are the real gate.
 */
final class PurchasePolicy {

	public const OK          = '';
	public const INVALID_QTY = 'invalid_quantity';
	public const CANCELLED   = 'cancelled';
	public const ENDED       = 'ended';
	public const SOLD_OUT    = 'sold_out';
	public const OVER_LIMIT  = 'over_limit';

	/**
	 * Check a purchase.
	 *
	 * @param ShowStatus             $status    Show status.
	 * @param DateTimeImmutable|null $starts_at Show start (null = unknown, treated as ended).
	 * @param DateTimeImmutable      $now       Current time.
	 * @param int                    $requested Tickets being added.
	 * @param int                    $in_cart   Tickets for this show already in the cart.
	 * @param int                    $max       Per-order maximum for one show.
	 * @return string One of the constants; OK ('') when allowed.
	 */
	public function check(
		ShowStatus $status,
		?DateTimeImmutable $starts_at,
		DateTimeImmutable $now,
		int $requested,
		int $in_cart,
		int $max
	): string {
		if ( $requested < 1 ) {
			return self::INVALID_QTY;
		}
		if ( ShowStatus::Cancelled === $status ) {
			return self::CANCELLED;
		}
		// Sales close when the show starts. A show without a date can't be sold.
		if ( ShowStatus::Archived === $status || null === $starts_at || $starts_at <= $now ) {
			return self::ENDED;
		}
		if ( ShowStatus::SoldOut === $status ) {
			return self::SOLD_OUT;
		}
		if ( $requested + $in_cart > $max ) {
			return self::OVER_LIMIT;
		}
		return self::OK;
	}
}
