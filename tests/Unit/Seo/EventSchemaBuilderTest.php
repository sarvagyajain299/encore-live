<?php
/**
 * Tests for Event JSON-LD.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Seo;

use DateTimeImmutable;
use DateTimeZone;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Seo\EventSchemaBuilder;
use EncoreCore\Shows\Show;
use EncoreCore\Tests\Unit\TestCase;

final class EventSchemaBuilderTest extends TestCase {

	/**
	 * A show fixture.
	 *
	 * @param ShowStatus $status Status.
	 * @param string     $genre  Genre.
	 */
	private function show( ShowStatus $status = ShowStatus::OnSale, string $genre = 'Comedy' ): Show {
		$tz = new DateTimeZone( 'Asia/Dubai' );
		return new Show(
			id: 5,
			title: 'Layla Haddad: Brunch Is a Personality',
			url: 'https://encore.test/shows/layla/',
			start: new DateTimeImmutable( '2026-11-29 20:00:00', $tz ),
			end: new DateTimeImmutable( '2026-11-29 22:30:00', $tz ),
			doors: '19:00',
			venue: array(
				'id'      => 2,
				'title'   => 'The Lantern Room',
				'url'     => 'https://encore.test/venues/lantern/',
				'address' => 'Warehouse 14, Al Quoz',
				'city'    => 'Dubai',
				'lat'     => 25.1372,
				'lng'     => 55.2295,
				'map_url' => '',
			),
			artists: array(
				array(
					'id'    => 9,
					'title' => 'Layla Haddad',
					'url'   => 'https://encore.test/artists/layla/',
				),
			),
			status: $status,
			product_id: 50,
			age: 18,
			min_price: 95.0,
			max_price: 295.0,
			genres: array( $genre )
		);
	}

	/**
	 * Build with standard references.
	 *
	 * @param Show $show Show.
	 * @return array<string, mixed>
	 */
	private function build( Show $show ): array {
		return ( new EventSchemaBuilder() )->build(
			$show,
			array(
				array(
					'tier'     => 'General',
					'price'    => 95.0,
					'in_stock' => true,
				),
				array(
					'tier'     => 'VIP',
					'price'    => 295.0,
					'in_stock' => false,
				),
			),
			array(
				'id'              => 'https://encore.test/shows/layla/#event',
				'webpage_id'      => 'https://encore.test/shows/layla/',
				'organization_id' => 'https://encore.test/#organization',
				'image'           => 'https://encore.test/img.jpg',
				'description'     => 'Observational stand-up.',
				'currency'        => 'AED',
				'valid_from'      => '2026-10-01T09:00:00+04:00',
			)
		);
	}

	public function test_has_google_required_properties(): void {
		$event = $this->build( $this->show() );

		$this->assertSame( 'Layla Haddad: Brunch Is a Personality', $event['name'] );
		$this->assertSame( '2026-11-29T20:00:00+04:00', $event['startDate'] );
		$this->assertSame( 'Place', $event['location']['@type'] );
		$this->assertSame( 'PostalAddress', $event['location']['address']['@type'] );
		$this->assertSame( 'AE', $event['location']['address']['addressCountry'] );
	}

	public function test_recommended_properties_and_graph_links(): void {
		$event = $this->build( $this->show() );

		$this->assertSame( 'ComedyEvent', $event['@type'] );
		$this->assertSame( '2026-11-29T22:30:00+04:00', $event['endDate'] );
		$this->assertSame( 'https://schema.org/EventScheduled', $event['eventStatus'] );
		$this->assertSame( 'https://schema.org/OfflineEventAttendanceMode', $event['eventAttendanceMode'] );
		$this->assertSame( 'https://encore.test/#organization', $event['organizer']['@id'] );
		$this->assertSame( 'Organization', $event['organizer']['@type'] );
		$this->assertSame( array( '@id' => 'https://encore.test/shows/layla/' ), $event['mainEntityOfPage'] );
		$this->assertSame( 'Person', $event['performer'][0]['@type'] );
		$this->assertSame( array( 'https://encore.test/img.jpg' ), $event['image'] );
	}

	public function test_one_offer_per_tier_with_availability(): void {
		$offers = $this->build( $this->show() )['offers'];

		$this->assertCount( 2, $offers );
		$this->assertSame( '95.00', $offers[0]['price'] );
		$this->assertSame( 'AED', $offers[0]['priceCurrency'] );
		$this->assertSame( 'https://schema.org/InStock', $offers[0]['availability'] );
		$this->assertSame( 'https://schema.org/SoldOut', $offers[1]['availability'] );
		$this->assertSame( '2026-10-01T09:00:00+04:00', $offers[0]['validFrom'] );
	}

	public function test_cancelled_show_is_marked_and_has_no_offers(): void {
		$event = $this->build( $this->show( ShowStatus::Cancelled ) );

		$this->assertSame( 'https://schema.org/EventCancelled', $event['eventStatus'] );
		$this->assertArrayNotHasKey( 'offers', $event );
	}

	public function test_music_show_uses_music_event_and_performing_group(): void {
		$event = $this->build( $this->show( ShowStatus::OnSale, 'Live Music' ) );

		$this->assertSame( 'MusicEvent', $event['@type'] );
		$this->assertSame( 'PerformingGroup', $event['performer'][0]['@type'] );
	}
}
