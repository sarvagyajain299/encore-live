<?php
/**
 * Decides a show's status from facts.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Domain;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Pure logic: no WordPress, no database. Callers gather the facts (current
 * status, end time, tier stock) and this class applies the rules. That makes
 * every rule unit-testable in milliseconds.
 *
 * Rules, in priority order:
 *  1. Cancelled is a human decision. Only a human undoes it, so it always wins.
 *  2. A show that has ended is Archived.
 *  3. If there is at least one managed tier and every tier has 0 stock, it is SoldOut.
 *  4. Otherwise it is OnSale. A previously Archived or SoldOut show comes back on
 *     sale when it is re-dated or restocked.
 */
final class ShowStatusCalculator {

	/**
	 * Calculate the status.
	 *
	 * @param ShowStatus             $current    Status stored today.
	 * @param DateTimeImmutable|null $ends_at    When the show ends (null = unknown, never archives).
	 * @param DateTimeImmutable      $now        The current time (injected so tests can freeze it).
	 * @param array<int|null>        $tier_stock Stock per tier. null = stock not managed (unlimited).
	 */
	public function calculate(
		ShowStatus $current,
		?DateTimeImmutable $ends_at,
		DateTimeImmutable $now,
		array $tier_stock
	): ShowStatus {
		if ( ShowStatus::Cancelled === $current ) {
			return ShowStatus::Cancelled;
		}

		if ( null !== $ends_at && $ends_at < $now ) {
			return ShowStatus::Archived;
		}

		if ( $this->is_sold_out( $tier_stock ) ) {
			return ShowStatus::SoldOut;
		}

		return ShowStatus::OnSale;
	}

	/**
	 * True when there is at least one tier and none has stock left.
	 * An unmanaged tier (null) means unlimited, so the show is never sold out.
	 *
	 * @param array<int|null> $tier_stock Stock per tier.
	 */
	public function is_sold_out( array $tier_stock ): bool {
		if ( array() === $tier_stock ) {
			return false;
		}

		foreach ( $tier_stock as $stock ) {
			if ( null === $stock || $stock > 0 ) {
				return false;
			}
		}

		return true;
	}
}
