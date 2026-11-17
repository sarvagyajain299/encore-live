<?php
/**
 * Shows show date and venue on cart lines and order items.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Contracts\Service;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Two hooks cover every place a fan sees their tickets:
 *  - woocommerce_get_item_data: cart and checkout (classic AND block, since the
 *    Store API reads the same filter into item_data).
 *  - woocommerce_checkout_create_order_line_item: copies the details onto the
 *    order line as item meta. Emails, My Account, the thank-you page and admin
 *    all print item meta, so one write shows everywhere. It is also a snapshot:
 *    if the show is re-dated later, the order still says what was sold.
 * The tier itself already appears as the variation attribute ("Tier: VIP").
 */
final class OrderDetails implements Service {

	public const ITEM_SHOW_ID = '_encore_show_id';

	/**
	 * Create with the lookup.
	 *
	 * @param TicketLookup $tickets Product-to-show lookup.
	 */
	public function __construct( private readonly TicketLookup $tickets = new TicketLookup() ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_item_meta' ), 10, 3 );
	}

	/**
	 * Add date and venue lines to a cart item.
	 *
	 * @param array<int, array<string, string>> $item_data Existing lines.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function cart_item_data( $item_data, $cart_item ): array {
		foreach ( $this->details( (int) ( $cart_item['product_id'] ?? 0 ) ) as $key => $value ) {
			$item_data[] = array(
				'key'   => $key,
				'value' => $value,
			);
		}
		return $item_data;
	}

	/**
	 * Snapshot the details onto the order line.
	 *
	 * @param WC_Order_Item_Product $item          Order item being created.
	 * @param string                $cart_item_key Cart key.
	 * @param array<string, mixed>  $values        Cart item.
	 */
	public function order_item_meta( $item, $cart_item_key, $values ): void {
		unset( $cart_item_key );
		$product_id = (int) ( $values['product_id'] ?? 0 );
		$show_id    = $this->tickets->show_id_for_product( $product_id );
		if ( ! $show_id ) {
			return;
		}
		// Leading underscore = hidden from customers, used for lookups (waitlist conversion, GA4).
		$item->add_meta_data( self::ITEM_SHOW_ID, $show_id, true );
		foreach ( $this->details( $product_id ) as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
	}

	/**
	 * Label => value pairs for a ticket product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	private function details( int $product_id ): array {
		$show_id = $this->tickets->show_id_for_product( $product_id );
		$show    = $show_id ? encore_get_show( $show_id ) : null;
		if ( ! $show ) {
			return array();
		}

		$details = array();
		if ( $show->start ) {
			$details[ __( 'Show date', 'encore-core' ) ] = encore_format_show_date( $show, 'l j F Y' );
		}
		if ( $show->venue ) {
			$details[ __( 'Venue', 'encore-core' ) ] = $show->venue['title'] . ( $show->venue['city'] ? ', ' . $show->venue['city'] : '' );
		}
		return $details;
	}
}
