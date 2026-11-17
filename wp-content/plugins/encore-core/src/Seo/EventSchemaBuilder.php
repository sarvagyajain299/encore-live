<?php
/**
 * Builds schema.org Event JSON-LD for a show.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seo;

use EncoreCore\Domain\ShowStatus;
use EncoreCore\Shows\Show;

defined( 'ABSPATH' ) || exit;

/**
 * Pure: a Show plus a few references in, an array out. Unit-tested against
 * Google's Event rich-result requirements:
 *   required:    name, startDate, location (with address)
 *   recommended: endDate, eventStatus, eventAttendanceMode, image, description,
 *                offers (price, priceCurrency, availability, url, validFrom),
 *                performer, organizer
 *
 * Dates use ISO 8601 WITH the +04:00 offset. Without an offset Google
 * guesses the timezone, and Dubai shows can appear at the wrong hour in search.
 */
final class EventSchemaBuilder {

	/**
	 * Build the Event node.
	 *
	 * @param Show                             $show  Show.
	 * @param array<int, array<string, mixed>> $tiers Tiers from TicketLookup (tier, price, in_stock).
	 * @param array<string, string>            $refs  id, webpage_id, organization_id, image, description, currency, valid_from.
	 * @return array<string, mixed>
	 */
	public function build( Show $show, array $tiers, array $refs ): array {
		$event = array(
			'@type'               => $this->types( $show ),
			'@id'                 => $refs['id'],
			'name'                => $show->title,
			'url'                 => $show->url,
			'mainEntityOfPage'    => array( '@id' => $refs['webpage_id'] ),
			'eventStatus'         => ShowStatus::Cancelled === $show->status
				? 'https://schema.org/EventCancelled'
				: 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			// Full node with Yoast's @id: Yoast only prints its Organization node once a
			// logo is set, and a bare @id reference would otherwise point at nothing.
			// Nodes that share an @id are merged by consumers, so this is safe either way.
			'organizer'           => array(
				'@type' => 'Organization',
				'@id'   => $refs['organization_id'],
				'name'  => $refs['organization_name'] ?? 'Encore Live',
				'url'   => $refs['organization_url'] ?? '',
			),
		);

		if ( $show->start ) {
			$event['startDate'] = $show->start->format( 'c' );
		}
		if ( $show->end ) {
			$event['endDate'] = $show->end->format( 'c' );
		}
		if ( ! empty( $refs['description'] ) ) {
			$event['description'] = $refs['description'];
		}
		if ( ! empty( $refs['image'] ) ) {
			$event['image'] = array( $refs['image'] );
		}
		// TODO(sarvagya): add 'doorTime' (a schema.org Event property) when $show->doors is set.
		// Hint: combine the start DATE with the doors TIME: $show->start->setTime( hour, minute )->format( 'c' ),
		// splitting $show->doors ("19:00") with explode( ':', … ). Add a test in EventSchemaBuilderTest.
		if ( $show->age ) {
			$event['typicalAgeRange'] = $show->age . '-';
		}

		if ( $show->venue ) {
			$event['location'] = array(
				'@type'   => 'Place',
				'name'    => $show->venue['title'],
				'url'     => $show->venue['url'],
				'address' => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $show->venue['address'],
					'addressLocality' => $show->venue['city'],
					'addressCountry'  => 'AE',
				),
			);
			if ( $show->venue['lat'] || $show->venue['lng'] ) {
				$event['location']['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $show->venue['lat'],
					'longitude' => $show->venue['lng'],
				);
			}
		}

		if ( $show->artists ) {
			$type               = in_array( 'Live Music', $show->genres, true ) ? 'PerformingGroup' : 'Person';
			$event['performer'] = array_map(
				static fn( array $artist ): array => array(
					'@type' => $type,
					'name'  => $artist['title'],
					'url'   => $artist['url'],
				),
				$show->artists
			);
		}

		$offers = array();
		foreach ( $tiers as $tier ) {
			$offer = array(
				'@type'         => 'Offer',
				'name'          => $tier['tier'],
				'price'         => number_format( (float) $tier['price'], 2, '.', '' ),
				'priceCurrency' => $refs['currency'],
				'availability'  => $tier['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
				'url'           => $show->url . '#tickets',
			);
			if ( ! empty( $refs['valid_from'] ) ) {
				$offer['validFrom'] = $refs['valid_from'];
			}
			$offers[] = $offer;
		}
		if ( $offers && ShowStatus::Cancelled !== $show->status ) {
			$event['offers'] = $offers;
		}

		return $event;
	}

	/**
	 * Most specific type that fits, alongside the generic Event type.
	 *
	 * @param Show $show Show.
	 * @return string|string[]
	 */
	private function types( Show $show ): string|array {
		if ( in_array( 'Comedy', $show->genres, true ) ) {
			return 'ComedyEvent';
		}
		if ( in_array( 'Live Music', $show->genres, true ) ) {
			return 'MusicEvent';
		}
		return 'Event';
	}
}
