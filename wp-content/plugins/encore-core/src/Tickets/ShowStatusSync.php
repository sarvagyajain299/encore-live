<?php
/**
 * Keeps show status in step with ticket stock.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Contracts\Service;
use EncoreCore\Shows\ShowStatusUpdater;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce fires these hooks whenever stock is written: on a sale, when a
 * refund or cancellation restocks, and when an admin edits stock. So the
 * last ticket selling flips the show to sold_out, and a refund flips it back.
 */
final class ShowStatusSync implements Service {

	/**
	 * Create with collaborators.
	 *
	 * @param TicketLookup      $tickets Product-to-show lookup.
	 * @param ShowStatusUpdater $updater Status writer.
	 */
	public function __construct(
		private readonly TicketLookup $tickets = new TicketLookup(),
		private readonly ShowStatusUpdater $updater = new ShowStatusUpdater()
	) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock_change' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'on_stock_change' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_stock_status_change' ), 10, 3 );
	}

	/**
	 * Stock quantity changed.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	public function on_stock_change( $product ): void {
		if ( $product instanceof WC_Product ) {
			$this->refresh( $product->get_id() );
		}
	}

	/**
	 * Stock status changed (covers products that don't manage quantities).
	 *
	 * @param int        $product_id Variation ID.
	 * @param string     $status     New stock status.
	 * @param WC_Product $product    Variation.
	 */
	public function on_stock_status_change( $product_id, $status, $product ): void {
		unset( $status, $product );
		$this->refresh( (int) $product_id );
	}

	/**
	 * Recalculate the show this product belongs to.
	 *
	 * @param int $product_id Product or variation ID.
	 */
	private function refresh( int $product_id ): void {
		$show_id = $this->tickets->show_id_for_product( $product_id );
		if ( $show_id ) {
			$this->updater->refresh( $show_id );
		}
	}
}
