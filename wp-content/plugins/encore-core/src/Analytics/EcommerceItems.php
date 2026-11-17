<?php
/**
 * GA4 e-commerce item shapes.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * GA4's recommended e-commerce events all carry an items[] array with the
 * same field names. Getting them exactly right matters: GA4 ignores unknown
 * keys silently, so a typo (itemId, item_price) means empty reports with no error.
 *
 * Mapping for tickets:
 *   item_id       variation ID (one per tier), or "show-<id>" in listings
 *   item_name     show title
 *   item_brand    "Encore Live"
 *   item_category genre (Comedy / Live Music)
 *   item_category2 city
 *   item_variant  tier (General / Premium / VIP)
 *   price         unit price (number, not string)
 *   quantity      tickets
 */
final class EcommerceItems {

	public const BRAND = 'Encore Live';

	/**
	 * One GA4 item.
	 *
	 * @param array<string, mixed> $data id, name, genre, city, variant, price, quantity, list, index.
	 * @return array<string, mixed>
	 */
	public static function item( array $data ): array {
		$item = array(
			'item_id'    => (string) $data['id'],
			'item_name'  => (string) $data['name'],
			'item_brand' => self::BRAND,
		);
		foreach ( array(
			'genre'   => 'item_category',
			'city'    => 'item_category2',
			'variant' => 'item_variant',
			'list'    => 'item_list_name',
		) as $from => $to ) {
			if ( ! empty( $data[ $from ] ) ) {
				$item[ $to ] = (string) $data[ $from ];
			}
		}
		if ( isset( $data['price'] ) && null !== $data['price'] ) {
			$item['price'] = round( (float) $data['price'], 2 );
		}
		if ( isset( $data['index'] ) ) {
			$item['index'] = (int) $data['index'];
		}
		$item['quantity'] = max( 1, (int) ( $data['quantity'] ?? 1 ) );
		return $item;
	}

	/**
	 * Total value of items (price x quantity), for add_to_cart / begin_checkout.
	 *
	 * @param array<int, array<string, mixed>> $items GA4 items.
	 */
	public static function value( array $items ): float {
		$total = 0.0;
		foreach ( $items as $item ) {
			$total += (float) ( $item['price'] ?? 0 ) * (int) ( $item['quantity'] ?? 1 );
		}
		return round( $total, 2 );
	}

	/**
	 * A dataLayer event with an ecommerce payload.
	 *
	 * @param string                           $event Event name (view_item, purchase, …).
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param string                           $currency ISO currency.
	 * @param array<string, mixed>             $extra  Extra ecommerce fields (transaction_id, tax, item_list_name…).
	 * @return array<string, mixed>
	 */
	public static function event( string $event, array $items, string $currency, array $extra = array() ): array {
		return array(
			'event'     => $event,
			'ecommerce' => array_merge(
				array(
					'currency' => $currency,
					'value'    => self::value( $items ),
					'items'    => array_values( $items ),
				),
				$extra
			),
		);
	}
}
