<?php
/**
 * Detects tickets coming back and queues the notifier.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Contracts\Service;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * Refund restocks, cancelled orders and admin capacity changes all write stock
 * through WooCommerce, which fires woocommerce_variation_set_stock.
 *
 * The listener must stay fast: it runs inside the request that changed stock
 * (an admin clicking "Refund", a checkout). So it never sends mail itself. It
 * checks one indexed COUNT and enqueues an Action Scheduler job that does
 * the real work in the background.
 */
final class RestockListener implements Service {

	/**
	 * Create.
	 *
	 * @param WaitlistRepository $repo Data.
	 */
	public function __construct( private readonly WaitlistRepository $repo ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_stock_status' ), 10, 2 );
	}

	/**
	 * Stock quantity written.
	 *
	 * @param WC_Product $product Variation.
	 */
	public function on_stock( $product ): void {
		if ( $product instanceof WC_Product_Variation && (int) $product->get_stock_quantity() > 0 ) {
			$this->maybe_enqueue( $product->get_id() );
		}
	}

	/**
	 * Stock status flipped (unmanaged stock).
	 *
	 * @param int    $variation_id Variation.
	 * @param string $status       New status.
	 */
	public function on_stock_status( $variation_id, $status ): void {
		if ( 'instock' === $status ) {
			$this->maybe_enqueue( (int) $variation_id );
		}
	}

	/**
	 * Enqueue only when someone is actually waiting.
	 *
	 * @param int $variation_id Variation.
	 */
	private function maybe_enqueue( int $variation_id ): void {
		if ( $this->repo->waiting_count( $variation_id ) > 0 ) {
			Scheduler::enqueue_notify( $variation_id );
		}
	}
}
