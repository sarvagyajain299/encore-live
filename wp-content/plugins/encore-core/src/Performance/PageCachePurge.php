<?php
/**
 * Keeps cached pages honest about ticket availability.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Performance;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Tickets\TicketLookup;
use WC_Product;

defined( 'ABSPATH' ) || exit;

/**
 * WP Super Cache purges a page when its POST is saved, but a ticket sale
 * changes stock without saving the show. Without this, a cached show page
 * could say "Only 3 left" (or "Sold out") for up to the cache lifetime.
 *
 *  - stock change on a tier  -> purge that show's page
 *  - show status change      -> also purge home and /shows/ (badges, "on sale now")
 *
 * Every call is behind function_exists(), so with the cache plugin off this
 * class does nothing.
 */
final class PageCachePurge implements Service {

	/**
	 * Create.
	 *
	 * @param TicketLookup $tickets Product to show lookup.
	 */
	public function __construct( private readonly TicketLookup $tickets = new TicketLookup() ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'woocommerce_variation_set_stock', array( $this, 'on_stock' ) );
		add_action( 'encore_show_status_changed', array( $this, 'on_status' ) );
	}

	/**
	 * Purge the show whose stock changed.
	 *
	 * @param WC_Product $product Variation.
	 */
	public function on_stock( $product ): void {
		if ( $product instanceof WC_Product ) {
			$this->purge_post( $this->tickets->show_id_for_product( $product->get_id() ) );
		}
	}

	/**
	 * Purge the show plus listing pages that show its status.
	 *
	 * @param int $show_id Show ID.
	 */
	public function on_status( $show_id ): void {
		$this->purge_post( (int) $show_id );
		if ( function_exists( 'wpsc_delete_url_cache' ) ) {
			wpsc_delete_url_cache( home_url( '/' ) );
			wpsc_delete_url_cache( (string) get_post_type_archive_link( PostTypes::SHOW ) );
		}
	}

	/**
	 * Purge one post's cached page.
	 *
	 * @param int $post_id Post ID.
	 */
	private function purge_post( int $post_id ): void {
		if ( $post_id && function_exists( 'wpsc_delete_post_cache' ) ) {
			wpsc_delete_post_cache( $post_id );
		}
	}
}
