<?php
/**
 * Loads shows.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Dates;
use EncoreCore\Support\Sanitizer;
use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * The only class that knows how a show is stored. Builds Show read models
 * and runs show queries via ShowQueryArgs.
 */
final class ShowRepository {

	/**
	 * Per-request cache, so a show rendered twice (card + schema) is built once.
	 *
	 * @var array<int, Show>
	 */
	private array $cache = array();

	/**
	 * Create with its query builder.
	 *
	 * @param ShowQueryArgs $query_args Query-args builder.
	 */
	public function __construct( private readonly ShowQueryArgs $query_args = new ShowQueryArgs() ) {}

	/**
	 * Load one show.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 */
	public function find( int|WP_Post $post ): ?Show {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || PostTypes::SHOW !== $post->post_type ) {
			return null;
		}
		if ( isset( $this->cache[ $post->ID ] ) ) {
			return $this->cache[ $post->ID ];
		}

		$product_id    = (int) get_post_meta( $post->ID, Keys::SHOW_PRODUCT, true );
		[ $min, $max ] = $this->price_range( $product_id );

		$show = new Show(
			id: $post->ID,
			title: get_the_title( $post ),
			url: (string) get_permalink( $post ),
			start: Dates::from_meta( (string) get_post_meta( $post->ID, Keys::SHOW_START, true ) ),
			end: Dates::from_meta( (string) get_post_meta( $post->ID, Keys::SHOW_END, true ) ),
			doors: (string) get_post_meta( $post->ID, Keys::SHOW_DOORS, true ),
			venue: $this->venue( (int) get_post_meta( $post->ID, Keys::SHOW_VENUE, true ) ),
			artists: $this->artists( Sanitizer::id_list( get_post_meta( $post->ID, Keys::SHOW_ARTISTS, true ) ) ),
			status: ShowStatus::tryFrom( (string) get_post_meta( $post->ID, Keys::SHOW_STATUS, true ) ) ?? ShowStatus::OnSale,
			product_id: $product_id,
			age: (int) get_post_meta( $post->ID, Keys::SHOW_AGE, true ),
			min_price: $min,
			max_price: $max,
			genres: $this->genres( $post )
		);

		$this->cache[ $post->ID ] = $show;
		return $show;
	}

	/**
	 * Run a filtered show query.
	 *
	 * @param array<string, mixed> $filters See ShowQueryArgs::build().
	 */
	public function query( array $filters ): WP_Query {
		return new WP_Query( $this->query_args->build( $filters, Dates::now() ) );
	}

	/**
	 * Genre names.
	 *
	 * @param WP_Post $post Show.
	 * @return string[]
	 */
	private function genres( WP_Post $post ): array {
		$terms = get_the_terms( $post, Taxonomies::GENRE );
		return is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array();
	}

	/**
	 * Venue summary.
	 *
	 * @param int $venue_id Venue post ID.
	 * @return array<string, mixed>|null
	 */
	private function venue( int $venue_id ): ?array {
		$venue = $venue_id ? get_post( $venue_id ) : null;
		if ( ! $venue instanceof WP_Post || 'publish' !== $venue->post_status ) {
			return null;
		}

		$cities = get_the_terms( $venue, Taxonomies::CITY );

		return array(
			'id'      => $venue->ID,
			'title'   => get_the_title( $venue ),
			'url'     => (string) get_permalink( $venue ),
			'address' => (string) get_post_meta( $venue->ID, Keys::VENUE_ADDRESS, true ),
			'city'    => is_array( $cities ) && $cities ? $cities[0]->name : '',
			'lat'     => (float) get_post_meta( $venue->ID, Keys::VENUE_LAT, true ),
			'lng'     => (float) get_post_meta( $venue->ID, Keys::VENUE_LNG, true ),
			'map_url' => (string) get_post_meta( $venue->ID, Keys::VENUE_MAP_URL, true ),
		);
	}

	/**
	 * Published artists, in stored order.
	 *
	 * @param int[] $ids Artist IDs.
	 * @return array<int, array{id:int,title:string,url:string}>
	 */
	private function artists( array $ids ): array {
		$artists = array();
		foreach ( $ids as $id ) {
			$artist = get_post( $id );
			if ( $artist instanceof WP_Post && 'publish' === $artist->post_status && PostTypes::ARTIST === $artist->post_type ) {
				$artists[] = array(
					'id'    => $artist->ID,
					'title' => get_the_title( $artist ),
					'url'   => (string) get_permalink( $artist ),
				);
			}
		}
		return $artists;
	}

	/**
	 * Cheapest and dearest tier price.
	 *
	 * @param int $product_id Ticket product ID.
	 * @return array{0: float|null, 1: float|null}
	 */
	private function price_range( int $product_id ): array {
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			return array( null, null );
		}
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product_Variable ) {
			return array( null, null );
		}
		// false = include out-of-stock variations, so a sold-out show still shows its prices.
		$min = $product->get_variation_price( 'min', false );
		$max = $product->get_variation_price( 'max', false );
		return array( '' === $min ? null : (float) $min, '' === $max ? null : (float) $max );
	}
}
