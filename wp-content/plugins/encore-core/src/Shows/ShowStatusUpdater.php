<?php
/**
 * Recalculates and stores a show's status.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Domain\ShowStatus;
use EncoreCore\Domain\ShowStatusCalculator;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Dates;
use EncoreCore\Tickets\TicketLookup;

defined( 'ABSPATH' ) || exit;

/**
 * Gathers the facts (stored status, end time, tier stock), asks the pure
 * calculator, saves the answer and announces changes. Used by stock sync
 * (Phase 3) and by the daily archive job (Phase 6).
 */
final class ShowStatusUpdater {

	/**
	 * Create with collaborators.
	 *
	 * @param ShowStatusCalculator $calculator Rules.
	 * @param TicketLookup         $tickets    Tier stock reader.
	 */
	public function __construct(
		private readonly ShowStatusCalculator $calculator = new ShowStatusCalculator(),
		private readonly TicketLookup $tickets = new TicketLookup()
	) {}

	/**
	 * Recalculate one show.
	 *
	 * @param int $show_id Show ID.
	 * @return ShowStatus The (possibly new) status.
	 */
	public function refresh( int $show_id ): ShowStatus {
		$current = ShowStatus::tryFrom( (string) get_post_meta( $show_id, Keys::SHOW_STATUS, true ) ) ?? ShowStatus::OnSale;
		$ends_at = Dates::from_meta( (string) get_post_meta( $show_id, Keys::SHOW_END, true ) )
			?? Dates::from_meta( (string) get_post_meta( $show_id, Keys::SHOW_START, true ) );

		$stock = array();
		if ( function_exists( 'wc_get_product' ) ) {
			$product_id = (int) get_post_meta( $show_id, Keys::SHOW_PRODUCT, true );
			$stock      = array_column( $this->tickets->tiers( $product_id ), 'stock' );
		}

		$new = $this->calculator->calculate( $current, $ends_at, Dates::now(), $stock );

		if ( $new !== $current ) {
			update_post_meta( $show_id, Keys::SHOW_STATUS, $new->value );

			/**
			 * A show's status changed (e.g. on_sale -> sold_out after the last ticket sold).
			 *
			 * @param int        $show_id Show ID.
			 * @param ShowStatus $new     New status.
			 * @param ShowStatus $old     Previous status.
			 */
			do_action( 'encore_show_status_changed', $show_id, $new, $current );
		}

		return $new;
	}
}
