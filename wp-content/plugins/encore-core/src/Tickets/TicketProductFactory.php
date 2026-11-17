<?php
/**
 * Creates the WooCommerce product that sells a show's tickets.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Domain\Tier;
use EncoreCore\Meta\Keys;
use RuntimeException;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;

defined( 'ABSPATH' ) || exit;

/**
 * One show = one variable product. Its "Tier" attribute has one variation per
 * tier, and each variation's stock quantity IS that tier's capacity. Selling
 * a ticket reduces stock, so WooCommerce's own stock system enforces capacity.
 *
 * Uses WooCommerce's CRUD classes (WC_Product_*), never raw post meta, so
 * WooCommerce's caches, lookup tables and hooks stay correct.
 */
final class TicketProductFactory {

	/**
	 * Create the product and its tier variations.
	 *
	 * @param int                                            $show_id Show the tickets are for.
	 * @param string                                         $title   Show title.
	 * @param array<string, array{price: float, stock: int}> $tiers   Tier value => price and capacity.
	 * @param bool                                           $seeded  Mark as demo content.
	 * @return int Product ID.
	 * @throws RuntimeException When WooCommerce is not active.
	 */
	public function create( int $show_id, string $title, array $tiers, bool $seeded = false ): int {
		if ( ! class_exists( WC_Product_Variable::class ) ) {
			throw new RuntimeException( 'WooCommerce must be active to create ticket products.' );
		}

		$attribute = new WC_Product_Attribute();
		$attribute->set_name( Tier::ATTRIBUTE );
		$attribute->set_options( array_keys( $tiers ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new WC_Product_Variable();
		/* translators: %s: show title. */
		$product->set_name( sprintf( __( '%s – Tickets', 'encore-core' ), $title ) );
		$product->set_status( 'publish' );
		// Fans buy from the show page; the product itself stays out of the shop grid and search.
		$product->set_catalog_visibility( 'hidden' );
		$product->set_attributes( array( $attribute ) );
		$product->update_meta_data( Keys::PRODUCT_SHOW, $show_id );
		if ( $seeded ) {
			$product->update_meta_data( Keys::SEEDED, 1 );
		}
		$product_id = $product->save();

		foreach ( $tiers as $tier => $config ) {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			// Custom (non-taxonomy) attributes are keyed by sanitize_title( name ): "tier".
			$variation->set_attributes( array( sanitize_title( Tier::ATTRIBUTE ) => $tier ) );
			$variation->set_regular_price( (string) $config['price'] );
			$variation->set_manage_stock( true );
			$variation->set_stock_quantity( $config['stock'] );
			$variation->set_stock_status( $config['stock'] > 0 ? 'instock' : 'outofstock' );
			$variation->set_virtual( true );
			$variation->save();
		}

		// Recalculate the parent's price range and stock status from its variations.
		WC_Product_Variable::sync( $product_id );

		return $product_id;
	}
}
