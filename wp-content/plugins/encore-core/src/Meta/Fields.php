<?php
/**
 * Field definitions per post type.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Meta;

use EncoreCore\Content\PostTypes;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Support\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * A single source of truth for each field: its meta type, REST schema,
 * sanitizer and form input. MetaRegistry and MetaBox both read from here.
 * Adding a field is one array entry, not three edits in three files.
 *
 * Field keys:
 *  - key        meta key (see Keys)
 *  - label      admin label
 *  - type       register_post_meta type: string|integer|number|array|object
 *  - input      form control: datetime|time|text|textarea|url|number|select|post|posts|social
 *  - sanitize   callable(mixed): mixed
 *  - default    value when unset
 *  - schema     (optional) REST schema for array/object types
 *  - options    (select) value => label
 *  - post_type  (post|posts) post type to choose from
 *  - help       (optional) help text under the input
 */
final class Fields {

	/**
	 * Fields for a post type.
	 *
	 * @param string $post_type Post type name.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_post_type( string $post_type ): array {
		return match ( $post_type ) {
			PostTypes::SHOW   => self::show(),
			PostTypes::ARTIST => self::artist(),
			PostTypes::VENUE  => self::venue(),
			default           => array(),
		};
	}

	/**
	 * Show fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function show(): array {
		$statuses = array();
		foreach ( ShowStatus::cases() as $status ) {
			$statuses[ $status->value ] = $status->label();
		}

		return array(
			array(
				'key'      => Keys::SHOW_START,
				'label'    => __( 'Starts', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'datetime',
				'sanitize' => array( Sanitizer::class, 'datetime' ),
				'default'  => '',
				'help'     => __( 'Local time (Asia/Dubai).', 'encore-core' ),
			),
			array(
				'key'      => Keys::SHOW_END,
				'label'    => __( 'Ends', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'datetime',
				'sanitize' => array( Sanitizer::class, 'datetime' ),
				'default'  => '',
			),
			array(
				'key'      => Keys::SHOW_DOORS,
				'label'    => __( 'Doors open', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'time',
				'sanitize' => array( Sanitizer::class, 'time' ),
				'default'  => '',
			),
			array(
				'key'       => Keys::SHOW_VENUE,
				'label'     => __( 'Venue', 'encore-core' ),
				'type'      => 'integer',
				'input'     => 'post',
				'post_type' => PostTypes::VENUE,
				'sanitize'  => array( Sanitizer::class, 'id' ),
				'default'   => 0,
			),
			array(
				'key'       => Keys::SHOW_ARTISTS,
				'label'     => __( 'Artists', 'encore-core' ),
				'type'      => 'array',
				'input'     => 'posts',
				'post_type' => PostTypes::ARTIST,
				'sanitize'  => array( Sanitizer::class, 'id_list' ),
				'default'   => array(),
				'schema'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'help'      => __( 'The first artist is the headliner.', 'encore-core' ),
			),
			array(
				'key'      => Keys::SHOW_PRODUCT,
				'label'    => __( 'Ticket product ID', 'encore-core' ),
				'type'     => 'integer',
				'input'    => 'number',
				'sanitize' => array( Sanitizer::class, 'id' ),
				'default'  => 0,
				'help'     => __( 'The variable WooCommerce product whose Tier variations are the tickets.', 'encore-core' ),
			),
			array(
				'key'      => Keys::SHOW_AGE,
				'label'    => __( 'Age restriction', 'encore-core' ),
				'type'     => 'integer',
				'input'    => 'select',
				'options'  => array(
					0  => __( 'All ages', 'encore-core' ),
					16 => '16+',
					18 => '18+',
					21 => '21+',
				),
				'sanitize' => array( Sanitizer::class, 'age' ),
				'default'  => 0,
			),
			array(
				'key'      => Keys::SHOW_STATUS,
				'label'    => __( 'Status', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'select',
				'options'  => $statuses,
				'sanitize' => array( Sanitizer::class, 'status' ),
				'default'  => ShowStatus::OnSale->value,
				'help'     => __( 'Sold out and archived are normally set automatically.', 'encore-core' ),
			),
		);
	}

	/**
	 * Artist fields. Genre is the encore_genre taxonomy; headshot is the featured image.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function artist(): array {
		$properties = array();
		foreach ( Sanitizer::SOCIAL_NETWORKS as $network ) {
			$properties[ $network ] = array(
				'type'   => 'string',
				'format' => 'uri',
			);
		}

		return array(
			array(
				'key'      => Keys::ARTIST_BIO,
				'label'    => __( 'Short bio', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'textarea',
				'sanitize' => array( Sanitizer::class, 'textarea' ),
				'default'  => '',
				'help'     => __( 'One or two sentences for cards and listings.', 'encore-core' ),
			),
			array(
				'key'      => Keys::ARTIST_SOCIAL,
				'label'    => __( 'Social links', 'encore-core' ),
				'type'     => 'object',
				'input'    => 'social',
				'sanitize' => array( Sanitizer::class, 'social_links' ),
				'default'  => array(),
				'schema'   => array(
					'type'                 => 'object',
					'properties'           => $properties,
					'additionalProperties' => false,
				),
			),
		);
	}

	/**
	 * Venue fields. City is the encore_city taxonomy.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function venue(): array {
		return array(
			array(
				'key'      => Keys::VENUE_ADDRESS,
				'label'    => __( 'Street address', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'text',
				'sanitize' => array( Sanitizer::class, 'text' ),
				'default'  => '',
			),
			array(
				'key'      => Keys::VENUE_LAT,
				'label'    => __( 'Latitude', 'encore-core' ),
				'type'     => 'number',
				'input'    => 'number',
				'step'     => '0.000001',
				'sanitize' => array( Sanitizer::class, 'latitude' ),
				'default'  => 0,
			),
			array(
				'key'      => Keys::VENUE_LNG,
				'label'    => __( 'Longitude', 'encore-core' ),
				'type'     => 'number',
				'input'    => 'number',
				'step'     => '0.000001',
				'sanitize' => array( Sanitizer::class, 'longitude' ),
				'default'  => 0,
			),
			array(
				'key'      => Keys::VENUE_CAPACITY,
				'label'    => __( 'Capacity', 'encore-core' ),
				'type'     => 'integer',
				'input'    => 'number',
				'sanitize' => array( Sanitizer::class, 'capacity' ),
				'default'  => 0,
			),
			array(
				'key'      => Keys::VENUE_MAP_URL,
				'label'    => __( 'Map link', 'encore-core' ),
				'type'     => 'string',
				'input'    => 'url',
				'sanitize' => array( Sanitizer::class, 'url' ),
				'default'  => '',
			),
		);
	}
}
