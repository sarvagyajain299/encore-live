<?php
/**
 * Picks which waiting fans to notify when tickets come back.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * The rule ("skip and continue"):
 *
 * Walk the queue in FIFO order. Notify an entry if its requested quantity
 * fits in the tickets still unallocated. If it does NOT fit, skip it but keep
 * walking: smaller requests further back may fit.
 *
 * Example: 3 tickets return; the queue is [A wants 4, B wants 2, C wants 1, D wants 1].
 *   Strict FIFO would stop at A and notify nobody (3 tickets sit unsold).
 *   Skip-and-continue notifies B and C (3 tickets). A keeps first place for
 *   the next restock; D is next after that.
 *
 * Why: tickets that sit unsold help nobody, and skipped entries lose
 * nothing because their position is unchanged. The trade-off: a large group
 * can be passed over repeatedly when restocks are small. A fairness tweak
 * (e.g. "after N skips, hold tickets until the group's request fits") is
 * documented as a follow-up.
 *
 * Pure: plain arrays in, IDs out. Unit-tested.
 */
final class BatchSelector {

	/**
	 * Select entries to notify.
	 *
	 * @param array<int, array{id:int, qty:int}> $queue     Waiting entries, already in FIFO order.
	 * @param int                                $available Tickets free to offer (stock minus open claims).
	 * @return int[] Entry IDs to notify, in queue order.
	 */
	public function select( array $queue, int $available ): array {
		$selected = array();
		foreach ( $queue as $entry ) {
			if ( $available <= 0 ) {
				break;
			}
			$qty = (int) $entry['qty'];
			if ( $qty >= 1 && $qty <= $available ) {
				$selected[] = (int) $entry['id'];
				$available -= $qty;
			}
		}
		return $selected;
	}
}
