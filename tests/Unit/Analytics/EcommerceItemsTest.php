<?php
/**
 * Tests for GA4 item and event shapes.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Analytics;

use EncoreCore\Analytics\EcommerceItems;
use EncoreCore\Tests\Unit\TestCase;

final class EcommerceItemsTest extends TestCase {

	public function test_item_uses_exact_ga4_field_names_and_types(): void {
		$item = EcommerceItems::item(
			array(
				'id'       => 61,
				'name'     => 'Nova Saleh: Neon Mirage Tour',
				'genre'    => 'Live Music',
				'city'     => 'Abu Dhabi',
				'variant'  => 'VIP',
				'price'    => '450',
				'quantity' => '2',
			)
		);

		$this->assertSame(
			array(
				'item_id'        => '61',
				'item_name'      => 'Nova Saleh: Neon Mirage Tour',
				'item_brand'     => 'Encore Live',
				'item_category'  => 'Live Music',
				'item_category2' => 'Abu Dhabi',
				'item_variant'   => 'VIP',
				'price'          => 450.0,
				'quantity'       => 2,
			),
			$item
		);
	}

	public function test_empty_optional_fields_are_omitted(): void {
		$item = EcommerceItems::item(
			array(
				'id'    => 'show-5',
				'name'  => 'Show',
				'price' => null,
			)
		);

		$this->assertArrayNotHasKey( 'item_variant', $item );
		$this->assertArrayNotHasKey( 'price', $item );
		$this->assertSame( 1, $item['quantity'] );
	}

	public function test_event_value_is_price_times_quantity(): void {
		$items = array(
			EcommerceItems::item(
				array(
					'id'       => 1,
					'name'     => 'A',
					'price'    => 95,
					'quantity' => 2,
				)
			),
			EcommerceItems::item(
				array(
					'id'    => 2,
					'name'  => 'B',
					'price' => 295,
				)
			),
		);

		$event = EcommerceItems::event( 'add_to_cart', $items, 'AED' );

		$this->assertSame( 'add_to_cart', $event['event'] );
		$this->assertSame( 'AED', $event['ecommerce']['currency'] );
		$this->assertSame( 485.0, $event['ecommerce']['value'] );
		$this->assertCount( 2, $event['ecommerce']['items'] );
	}

	public function test_extra_fields_for_purchase(): void {
		$event = EcommerceItems::event( 'purchase', array(), 'AED', array( 'transaction_id' => '943' ) );
		$this->assertSame( '943', $event['ecommerce']['transaction_id'] );
	}
}
