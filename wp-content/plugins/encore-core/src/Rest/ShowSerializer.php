<?php
/**
 * Turns a Show into its public JSON shape.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Rest;

use EncoreCore\Shows\Show;

defined( 'ABSPATH' ) || exit;

/**
 * The public API contract. Keeping it in one class means the response shape
 * is documented, versioned (encore/v1) and unit-tested, and internal changes
 * (meta key names, storage) never leak to API consumers.
 */
final class ShowSerializer {

	/**
	 * Serialize a show.
	 *
	 * @param Show   $show     Show.
	 * @param string $currency ISO currency code.
	 * @param string $image    Image URL ('' when none).
	 * @return array<string, mixed>
	 */
	public function to_array( Show $show, string $currency, string $image ): array {
		return array(
			'id'              => $show->id,
			'title'           => $show->title,
			'url'             => $show->url,
			'status'          => $show->status->value,
			// ISO 8601 with offset, e.g. 2026-12-01T20:00:00+04:00.
			'start'           => $show->start?->format( 'c' ),
			'end'             => $show->end?->format( 'c' ),
			'doors'           => '' !== $show->doors ? $show->doors : null,
			'age_restriction' => $show->age,
			'genres'          => array_values( $show->genres ),
			'venue'           => $show->venue ? array(
				'id'      => $show->venue['id'],
				'name'    => $show->venue['title'],
				'url'     => $show->venue['url'],
				'address' => $show->venue['address'],
				'city'    => $show->venue['city'],
				'geo'     => array(
					'lat' => $show->venue['lat'],
					'lng' => $show->venue['lng'],
				),
			) : null,
			'artists'         => array_map(
				static fn( array $artist ): array => array(
					'id'   => $artist['id'],
					'name' => $artist['title'],
					'url'  => $artist['url'],
				),
				$show->artists
			),
			'price'           => null === $show->min_price ? null : array(
				'currency' => $currency,
				'min'      => $show->min_price,
				'max'      => $show->max_price,
			),
			'image'           => '' !== $image ? $image : null,
		);
	}
}
