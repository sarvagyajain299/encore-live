<?php
/**
 * Yoast schema graph piece for shows.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seo;

use EncoreCore\Content\PostTypes;
use EncoreCore\Tickets\TicketLookup;
use Yoast\WP\SEO\Config\Schema_IDs;
use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

defined( 'ABSPATH' ) || exit;

/**
 * Yoast outputs ONE JSON-LD graph per page (WebSite, Organization, WebPage,
 * BreadcrumbList …), with nodes linked by @id. Adding our Event as a piece
 * of that graph, instead of echoing a second <script type="application/ld+json">,
 * means:
 *  - organizer points at Yoast's Organization node (no duplicate org data)
 *  - mainEntityOfPage points at Yoast's WebPage node
 *  - search engines see one consistent graph instead of two disconnected ones
 *
 * Only loaded inside the wpseo_schema_graph_pieces filter, so this class
 * exists only when Yoast is active. Yoast sets $this->context and
 * $this->helpers after the filter runs.
 */
final class EventSchemaPiece extends Abstract_Schema_Piece {

	/**
	 * Only on single show pages.
	 */
	public function is_needed() {
		return is_singular( PostTypes::SHOW );
	}

	/**
	 * Build the Event node.
	 *
	 * @return array<string, mixed>|false
	 */
	public function generate() {
		$show = encore_get_show( (int) $this->context->indexable->object_id );
		if ( ! $show ) {
			return false;
		}

		$image   = (string) get_the_post_thumbnail_url( $show->id, 'encore-hero' );
		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $show->id ) );

		return ( new EventSchemaBuilder() )->build(
			$show,
			( new TicketLookup() )->tiers( $show->product_id ),
			array(
				'id'                => $this->context->canonical . '#event',
				'webpage_id'        => $this->context->main_schema_id,
				'organization_id'   => $this->context->site_url . Schema_IDs::ORGANIZATION_HASH,
				'organization_name' => (string) get_bloginfo( 'name' ),
				'organization_url'  => $this->context->site_url,
				'image'             => $image,
				'description'       => $excerpt,
				'currency'          => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'AED',
				'valid_from'        => (string) get_post_time( 'c', false, $show->id ),
			)
		);
	}
}
