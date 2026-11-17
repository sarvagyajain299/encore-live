<?php
/**
 * Server-side purchase rules for ticket products.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Contracts\Service;
use EncoreCore\Support\Dates;
use EncoreCore\Support\Settings;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces PurchasePolicy at every point a cart can change:
 *  - woocommerce_add_to_cart_validation  (classic form AND Store API add-item)
 *  - woocommerce_update_cart_validation  (classic cart quantity changes)
 *  - woocommerce_store_api_product_quantity_maximum (block cart +/- buttons)
 *  - woocommerce_check_cart_items        (before checkout: catches a show
 *                                         cancelled while it sat in a cart)
 */
final class CartValidation implements Service {

	/**
	 * Create with collaborators.
	 *
	 * @param TicketLookup   $tickets Product-to-show lookup.
	 * @param PurchasePolicy $policy  Rules.
	 */
	public function __construct(
		private readonly TicketLookup $tickets = new TicketLookup(),
		private readonly PurchasePolicy $policy = new PurchasePolicy()
	) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add' ), 10, 4 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_update' ), 10, 4 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'store_api_max' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'validate_cart' ) );
	}

	/**
	 * Validate an add-to-cart.
	 *
	 * @param bool $passed       Result so far.
	 * @param int  $product_id   Parent product ID.
	 * @param int  $quantity     Quantity being added.
	 * @param int  $variation_id Variation ID.
	 */
	public function validate_add( $passed, $product_id, $quantity, $variation_id = 0 ): bool {
		$show_id = $this->tickets->show_id_for_product( (int) $product_id );
		if ( ! $passed || ! $show_id ) {
			return (bool) $passed;
		}
		unset( $variation_id );
		return $this->allow( $show_id, (int) $quantity, $this->tickets_in_cart( $show_id ) );
	}

	/**
	 * Validate a quantity change in the classic cart.
	 *
	 * @param bool                 $passed        Result so far.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values        Cart item.
	 * @param int                  $quantity      New quantity.
	 */
	public function validate_update( $passed, $cart_item_key, $values, $quantity ): bool {
		$show_id = $this->tickets->show_id_for_product( (int) ( $values['product_id'] ?? 0 ) );
		if ( ! $passed || ! $show_id ) {
			return (bool) $passed;
		}
		return $this->allow( $show_id, (int) $quantity, $this->tickets_in_cart( $show_id, (string) $cart_item_key ) );
	}

	/**
	 * Cap the block cart's quantity stepper at the per-order limit.
	 *
	 * @param int|float  $max     Current maximum.
	 * @param WC_Product $product Product.
	 */
	public function store_api_max( $max, $product ): int|float {
		if ( $product instanceof WC_Product && $this->tickets->show_id_for_product( $product->get_id() ) ) {
			return min( $max, Settings::max_tickets_per_order() );
		}
		return $max;
	}

	/**
	 * Re-check the whole cart before checkout. Notices of type "error" block checkout.
	 */
	public function validate_cart(): void {
		if ( ! WC()->cart ) {
			return;
		}
		$per_show = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$show_id = $this->tickets->show_id_for_product( (int) $item['product_id'] );
			if ( $show_id ) {
				$per_show[ $show_id ] = ( $per_show[ $show_id ] ?? 0 ) + (int) $item['quantity'];
			}
		}
		foreach ( $per_show as $show_id => $quantity ) {
			$this->allow( $show_id, $quantity, 0 );
		}
	}

	/**
	 * Apply the policy; add a notice when it says no.
	 *
	 * @param int $show_id  Show ID.
	 * @param int $quantity Tickets requested.
	 * @param int $in_cart  Tickets already in the cart for this show.
	 */
	private function allow( int $show_id, int $quantity, int $in_cart ): bool {
		$show = encore_get_show( $show_id );
		if ( ! $show ) {
			return true;
		}

		$max    = Settings::max_tickets_per_order();
		$result = $this->policy->check( $show->status, $show->start, Dates::now(), $quantity, $in_cart, $max );
		if ( PurchasePolicy::OK === $result ) {
			return true;
		}

		wc_add_notice( esc_html( $this->message( $result, $show->title, $max, $in_cart ) ), 'error' );
		return false;
	}

	/**
	 * Fan-facing message for a policy result.
	 *
	 * @param string $result  PurchasePolicy constant.
	 * @param string $title   Show title.
	 * @param int    $max     Per-order max.
	 * @param int    $in_cart Already in cart.
	 */
	private function message( string $result, string $title, int $max, int $in_cart ): string {
		return match ( $result ) {
			/* translators: %s: show title. */
			PurchasePolicy::CANCELLED => sprintf( __( '%s has been cancelled, so tickets can no longer be bought.', 'encore-core' ), $title ),
			/* translators: %s: show title. */
			PurchasePolicy::ENDED     => sprintf( __( 'Ticket sales for %s have closed.', 'encore-core' ), $title ),
			/* translators: %s: show title. */
			PurchasePolicy::SOLD_OUT  => sprintf( __( '%s is sold out.', 'encore-core' ), $title ),
			PurchasePolicy::OVER_LIMIT => $in_cart
				/* translators: 1: max tickets, 2: show title, 3: tickets already in cart. */
				? sprintf( __( 'You can buy up to %1$d tickets for %2$s per order, and you already have %3$d in your cart.', 'encore-core' ), $max, $title, $in_cart )
				/* translators: 1: max tickets, 2: show title. */
				: sprintf( __( 'You can buy up to %1$d tickets for %2$s per order.', 'encore-core' ), $max, $title ),
			default                    => __( 'Please choose a valid number of tickets.', 'encore-core' ),
		};
	}

	/**
	 * Tickets for a show already in the cart.
	 *
	 * @param int    $show_id  Show ID.
	 * @param string $skip_key Cart item to ignore (the one being updated).
	 */
	private function tickets_in_cart( int $show_id, string $skip_key = '' ): int {
		if ( ! WC()->cart ) {
			return 0;
		}
		$total = 0;
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( $key !== $skip_key && $this->tickets->show_id_for_product( (int) $item['product_id'] ) === $show_id ) {
				$total += (int) $item['quantity'];
			}
		}
		return $total;
	}
}
