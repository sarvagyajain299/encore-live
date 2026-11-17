<?php
/**
 * Google Tag Manager container and GA4 e-commerce dataLayer events.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Analytics;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Shows\Show;
use EncoreCore\Support\Settings;
use EncoreCore\Tickets\TicketLookup;
use WC_Order;
use WC_Order_Item_Product;

defined( 'ABSPATH' ) || exit;

/**
 * Nothing here runs unless a GTM container ID is saved in Encore → Settings.
 * The ID comes from the database, never from code, so staging and production
 * can use different containers.
 *
 * Where each event comes from:
 *   view_item_list  analytics.js, one per card list on the page (data-list)
 *   select_item     analytics.js, on card click
 *   view_item       server, single show page (one item per tier)
 *   add_to_cart     server: queued in the WooCommerce session on
 *                   woocommerce_add_to_cart, printed on the NEXT page view
 *                   (the add happens on a POST that redirects to the cart, so
 *                   a push during that request would never reach the browser)
 *   begin_checkout  server, checkout page
 *   purchase        server, order-received page, ONCE per order (order meta guard)
 */
final class DataLayer implements Service {

	private const SESSION_KEY   = 'encore_ga_events';
	private const PURCHASE_FLAG = '_encore_ga_purchase_tracked';

	/**
	 * Create.
	 *
	 * @param TicketLookup $tickets Ticket lookup.
	 */
	public function __construct( private readonly TicketLookup $tickets = new TicketLookup() ) {}

	/**
	 * Hook in only when a container is configured.
	 */
	public function register(): void {
		if ( '' === $this->container_id() ) {
			return;
		}
		add_action( 'wp_head', array( $this, 'head' ), 1 );
		add_action( 'wp_body_open', array( $this, 'noscript' ), 1 );
		add_action( 'woocommerce_add_to_cart', array( $this, 'queue_add_to_cart' ), 10, 4 );
		add_action( 'wp_footer', array( $this, 'footer' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * GTM loader, as early in <head> as possible (Google's recommendation).
	 */
	public function head(): void {
		$id = wp_json_encode( $this->container_id() );
		wp_print_inline_script_tag(
			"window.dataLayer=window.dataLayer||[];(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',{$id});"
		);
	}

	/**
	 * GTM noscript iframe right after <body>.
	 */
	public function noscript(): void {
		printf(
			'<noscript><iframe src="%s" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>',
			esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $this->container_id() ) )
		);
	}

	/**
	 * Card-level events (view_item_list, select_item).
	 */
	public function assets(): void {
		wp_enqueue_script(
			'encore-analytics',
			plugins_url( 'assets/js/analytics.js', ENCORE_CORE_FILE ),
			array(),
			(string) filemtime( ENCORE_CORE_DIR . '/assets/js/analytics.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Remember an add-to-cart for the next page view.
	 *
	 * @param string $cart_item_key Cart key.
	 * @param int    $product_id    Parent product.
	 * @param int    $quantity      Quantity.
	 * @param int    $variation_id  Variation.
	 */
	public function queue_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id ): void {
		unset( $cart_item_key );
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$item = $this->ticket_item( (int) $variation_id ? (int) $variation_id : (int) $product_id, (int) $quantity );
		if ( ! $item ) {
			return;
		}
		$queue   = (array) WC()->session->get( self::SESSION_KEY, array() );
		$queue[] = EcommerceItems::event( 'add_to_cart', array( $item ), $this->currency() );
		WC()->session->set( self::SESSION_KEY, $queue );
	}

	/**
	 * Print items map and page-level events.
	 */
	public function footer(): void {
		$events = array();

		if ( function_exists( 'WC' ) && WC()->session ) {
			$events = (array) WC()->session->get( self::SESSION_KEY, array() );
			WC()->session->set( self::SESSION_KEY, array() );
		}

		if ( is_singular( PostTypes::SHOW ) ) {
			$show = encore_get_show( get_queried_object_id() );
			if ( $show ) {
				$items = array();
				foreach ( $this->tickets->tiers( $show->product_id ) as $tier ) {
					$items[] = $this->show_item( $show, $tier['variation_id'], $tier['tier'], $tier['price'] );
				}
				if ( $items ) {
					$events[] = EcommerceItems::event( 'view_item', $items, $this->currency() );
				}
			}
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() && WC()->cart ) {
			$items = array();
			foreach ( WC()->cart->get_cart() as $line ) {
				$item = $this->ticket_item( (int) ( $line['variation_id'] ? $line['variation_id'] : $line['product_id'] ), (int) $line['quantity'] );
				if ( $item ) {
					$items[] = $item;
				}
			}
			if ( $items ) {
				$events[] = EcommerceItems::event( 'begin_checkout', $items, $this->currency() );
			}
		}

		$purchase = $this->purchase_event();
		if ( $purchase ) {
			$events[] = $purchase;
		}

		// Items for every show card rendered on this page, keyed by show ID, for analytics.js.
		$cards = array();
		foreach ( encore_shows()->loaded() as $show ) {
			$cards[ $show->id ] = $this->show_item( $show, 0, '', $show->min_price );
		}

		$js = 'window.dataLayer=window.dataLayer||[];window.encoreAnalytics=' . wp_json_encode(
			array(
				'currency' => $this->currency(),
				'items'    => (object) $cards,
			),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
		) . ';';
		foreach ( $events as $event ) {
			// Clear the previous ecommerce object first, as Google recommends, so fields don't leak between events.
			$js .= 'dataLayer.push({ecommerce:null});dataLayer.push(' . wp_json_encode( $event, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ');';
		}
		wp_print_inline_script_tag( $js );
	}

	/**
	 * Purchase event for the order-received page, once per order.
	 *
	 * The order key in the URL is checked, so nobody can read another
	 * customer's order totals by changing the ID. The meta flag stops a page
	 * refresh or a revisit from counting the same revenue twice.
	 *
	 * @return array<string, mixed>|null
	 */
	private function purchase_event(): ?array {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return null;
		}
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		$key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the order key IS the authorisation.
		if ( ! $order instanceof WC_Order || ! hash_equals( $order->get_order_key(), $key ) || $order->get_meta( self::PURCHASE_FLAG ) ) {
			return null;
		}

		$items = array();
		foreach ( $order->get_items() as $line ) {
			if ( ! $line instanceof WC_Order_Item_Product ) {
				continue;
			}
			$qty  = max( 1, (int) $line->get_quantity() );
			$item = $this->ticket_item( $line->get_variation_id() ? $line->get_variation_id() : $line->get_product_id(), $qty, (float) $line->get_total() / $qty );
			if ( $item ) {
				$items[] = $item;
			}
		}
		if ( ! $items ) {
			return null;
		}

		$order->update_meta_data( self::PURCHASE_FLAG, gmdate( 'c' ) );
		$order->save_meta_data();

		$event                       = EcommerceItems::event(
			'purchase',
			$items,
			$order->get_currency(),
			array(
				'transaction_id' => (string) $order->get_order_number(),
				'tax'            => round( (float) $order->get_total_tax(), 2 ),
				'shipping'       => round( (float) $order->get_shipping_total(), 2 ),
			)
		);
		$event['ecommerce']['value'] = round( (float) $order->get_total(), 2 );
		return $event;
	}

	/**
	 * GA4 item for a ticket product/variation.
	 *
	 * @param int        $product_id Variation or product ID.
	 * @param int        $quantity   Quantity.
	 * @param float|null $price      Unit price override (e.g. after discounts).
	 * @return array<string, mixed>|null
	 */
	private function ticket_item( int $product_id, int $quantity, ?float $price = null ): ?array {
		$product = wc_get_product( $product_id );
		$show    = $product ? encore_get_show( $this->tickets->show_id_for_product( $product_id ) ) : null;
		if ( ! $product || ! $show ) {
			return null;
		}
		$tier = $product instanceof \WC_Product_Variation ? (string) $product->get_attribute( 'Tier' ) : '';
		return $this->show_item( $show, $product_id, $tier, $price ?? (float) $product->get_price(), $quantity );
	}

	/**
	 * GA4 item from a show.
	 *
	 * @param Show       $show         Show.
	 * @param int        $variation_id Variation (0 = the show itself, in listings).
	 * @param string     $tier         Tier.
	 * @param float|null $price        Price.
	 * @param int        $quantity     Quantity.
	 * @return array<string, mixed>
	 */
	private function show_item( Show $show, int $variation_id, string $tier, ?float $price, int $quantity = 1 ): array {
		return EcommerceItems::item(
			array(
				'id'       => $variation_id ? (string) $variation_id : 'show-' . $show->id,
				'name'     => $show->title,
				'genre'    => $show->genres[0] ?? '',
				'city'     => $show->venue['city'] ?? '',
				'variant'  => $tier,
				'price'    => $price,
				'quantity' => $quantity,
			)
		);
	}

	/**
	 * Store currency.
	 */
	private function currency(): string {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'AED';
	}

	/**
	 * Configured container ID ('' = disabled).
	 */
	private function container_id(): string {
		return (string) Settings::get( 'gtm_container_id' );
	}
}
