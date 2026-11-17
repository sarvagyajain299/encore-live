<?php
/**
 * Tests for the public API response shape.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Rest;

use DateTimeImmutable;
use DateTimeZone;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Rest\ShowSerializer;
use EncoreCore\Shows\Show;
use EncoreCore\Tests\Unit\TestCase;

final class ShowSerializerTest extends TestCase {

	public function test_full_show(): void {
		$tz   = new DateTimeZone( 'Asia/Dubai' );
		$show = new Show(
			id: 42,
			title: 'Nova Saleh: Neon Mirage Tour',
			url: 'http://localhost:8080/shows/nova/',
			start: new DateTimeImmutable( '2026-12-22 20:00:00', $tz ),
			end: new DateTimeImmutable( '2026-12-22 22:30:00', $tz ),
			doors: '19:00',
			venue: array(
				'id'      => 7,
				'title'   => 'Corniche Arts Stage',
				'url'     => 'http://localhost:8080/venues/corniche/',
				'address' => 'Corniche Road West',
				'city'    => 'Abu Dhabi',
				'lat'     => 24.4672,
				'lng'     => 54.3225,
				'map_url' => 'https://osm.example',
			),
			artists: array(
				array(
					'id'    => 3,
					'title' => 'Nova Saleh',
					'url'   => 'http://localhost:8080/artists/nova/',
				),
			),
			status: ShowStatus::OnSale,
			product_id: 99,
			age: 0,
			min_price: 150.0,
			max_price: 450.0,
			genres: array( 'Live Music' )
		);

		$json = ( new ShowSerializer() )->to_array( $show, 'AED', 'http://img/x.jpg' );

		$this->assertSame( '2026-12-22T20:00:00+04:00', $json['start'] );
		$this->assertSame( 'on_sale', $json['status'] );
		$this->assertSame( 'Corniche Arts Stage', $json['venue']['name'] );
		$this->assertSame(
			array(
				'lat' => 24.4672,
				'lng' => 54.3225,
			),
			$json['venue']['geo']
		);
		$this->assertSame( 'Nova Saleh', $json['artists'][0]['name'] );
		$this->assertSame(
			array(
				'currency' => 'AED',
				'min'      => 150.0,
				'max'      => 450.0,
			),
			$json['price']
		);
		// Internal fields must not leak into the public contract.
		$this->assertArrayNotHasKey( 'product_id', $json );
		$this->assertArrayNotHasKey( 'map_url', $json['venue'] );
	}

	public function test_missing_data_becomes_null_not_empty_strings(): void {
		$show = new Show( 1, 'TBA', 'http://x', null, null, '', null, array(), ShowStatus::Cancelled, 0, 0, null, null, array() );

		$json = ( new ShowSerializer() )->to_array( $show, 'AED', '' );

		$this->assertNull( $json['start'] );
		$this->assertNull( $json['doors'] );
		$this->assertNull( $json['venue'] );
		$this->assertNull( $json['price'] );
		$this->assertNull( $json['image'] );
		$this->assertSame( array(), $json['artists'] );
		$this->assertSame( 'cancelled', $json['status'] );
	}
}
