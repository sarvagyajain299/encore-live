<?php
/**
 * Reads ticket tiers from WooCommerce.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Domain\Tier;
use EncoreCore\Meta\Keys;
use WC_Product;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * The bridge between WooCommerce products and shows.
 */
final class TicketLookup {

	/**
	 * Which show does this product or variation sell tickets for?
	 *
	 * @param int $product_id Product or variation ID.
	 * @return int Show ID, or 0 when it is not a ticket product.
	 */
	public function show_id_for_product( int $product_id ): int {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof WC_Product ) {
			return 0;
		}
		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return (int) get_post_meta( $parent_id, Keys::PRODUCT_SHOW, true );
	}

	/**
	 * Tiers of a ticket product, in tier order (General, Premium, VIP).
	 *
	 * @param int $product_id Variable product ID.
	 * @return array<int, array{variation_id:int, tier:string, price:float, stock:int|null, in_stock:bool}>
	 */
	public function tiers( int $product_id ): array {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof \WC_Product_Variable ) {
			return array();
		}

		$tiers = array();
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation instanceof WC_Product_Variation || 'publish' !== $variation->get_status() ) {
				continue;
			}
			$managed = $variation->managing_stock();
			$stock   = $managed ? (int) $variation->get_stock_quantity() : null;
			// Unmanaged but manually marked out of stock counts as 0.
			if ( ! $managed && ! $variation->is_in_stock() ) {
				$stock = 0;
			}
			$tiers[] = array(
				'variation_id' => $variation->get_id(),
				'tier'         => (string) $variation->get_attribute( Tier::ATTRIBUTE ),
				'price'        => (float) $variation->get_price(),
				'stock'        => $stock,
				'in_stock'     => $variation->is_in_stock() && ( null === $stock || $stock > 0 ),
			);
		}

		$order = array_flip( Tier::values() );
		usort( $tiers, static fn( array $a, array $b ): int => ( $order[ $a['tier'] ] ?? 99 ) <=> ( $order[ $b['tier'] ] ?? 99 ) );

		return $tiers;
	}
}
