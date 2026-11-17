<?php
/**
 * Marks waitlist entries converted when the fan buys.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Contracts\Service;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * An order "completes" for our purposes when it is paid: processing (paid,
 * not yet fulfilled) or completed. Both hooks are listened to; the state
 * machine makes the second call a harmless no-op.
 *
 * Matching rule: same billing email + same tier variation (and therefore the
 * same show). Counts whether or not the claim link was used: buying through
 * the shop in the meantime still converts the entry.
 */
final class ConversionTracker implements Service {

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
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_paid' ), 10, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_paid' ), 10, 2 );
	}

	/**
	 * Convert matching entries.
	 *
	 * @param int           $order_id Order ID.
	 * @param WC_Order|null $order    Order.
	 */
	public function on_paid( $order_id, $order = null ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $order->get_billing_email() ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product || ! $item->get_variation_id() ) {
				continue;
			}
			foreach ( $this->repo->find_convertible( $order->get_billing_email(), $item->get_variation_id() ) as $entry ) {
				$this->repo->transition(
					(int) $entry['id'],
					Status::Converted,
					'system',
					'order #' . $order->get_id(),
					array( 'converted_order_id' => $order->get_id() )
				);
			}
		}
	}
}
