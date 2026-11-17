<?php
/**
 * Creates and removes demo content.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seed;

use DateInterval;
use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Domain\ShowStatusCalculator;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Dates;
use EncoreCore\Support\Sanitizer;
use EncoreCore\Tickets\TicketProductFactory;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a realistic data set: past and upcoming shows, one sold out, one
 * cancelled, each with a linked ticket product. Everything it creates is
 * tagged with the _encore_seeded meta key, so reset() removes only that.
 */
final class DemoSeeder {

	/**
	 * Inject collaborators (easy to swap in tests).
	 *
	 * @param TicketProductFactory $products   Ticket product factory.
	 * @param ShowStatusCalculator $calculator Status rules.
	 * @param PlaceholderImage     $images     Artwork generator.
	 */
	public function __construct(
		private readonly TicketProductFactory $products,
		private readonly ShowStatusCalculator $calculator,
		private readonly PlaceholderImage $images
	) {}

	/**
	 * Is there seeded content already?
	 */
	public function has_seeded_content(): bool {
		return array() !== $this->seeded_ids( array( PostTypes::SHOW ), 1 );
	}

	/**
	 * Create all demo content.
	 *
	 * @param callable(string): void $log Progress output.
	 * @return array<string, int> Counts by type.
	 * @throws RuntimeException When WooCommerce is not active.
	 */
	public function seed( callable $log ): array {
		$terms   = $this->ensure_terms();
		$artists = array();
		$venues  = array();

		foreach ( DemoData::artists() as $slug => $artist ) {
			$id = $this->insert_post( PostTypes::ARTIST, $artist['name'], $slug, $artist['bio'] );
			update_post_meta( $id, Keys::ARTIST_BIO, $artist['bio'] );
			update_post_meta( $id, Keys::ARTIST_SOCIAL, array( 'website' => 'https://example.com/artists/' . $slug ) );
			wp_set_object_terms( $id, $terms[ Taxonomies::GENRE ][ $artist['genre'] ], Taxonomies::GENRE );
			$this->attach_image( $id, $artist['name'] );
			$artists[ $slug ] = $id;
			$log( "Artist: {$artist['name']}" );
		}

		foreach ( DemoData::venues() as $slug => $venue ) {
			$id = $this->insert_post( PostTypes::VENUE, $venue['name'], $slug, '' );
			update_post_meta( $id, Keys::VENUE_ADDRESS, $venue['address'] );
			update_post_meta( $id, Keys::VENUE_LAT, $venue['lat'] );
			update_post_meta( $id, Keys::VENUE_LNG, $venue['lng'] );
			update_post_meta( $id, Keys::VENUE_CAPACITY, $venue['capacity'] );
			update_post_meta(
				$id,
				Keys::VENUE_MAP_URL,
				sprintf( 'https://www.openstreetmap.org/?mlat=%1$F&mlon=%2$F#map=17/%1$F/%2$F', $venue['lat'], $venue['lng'] )
			);
			wp_set_object_terms( $id, $terms[ Taxonomies::CITY ][ $venue['city'] ], Taxonomies::CITY );
			$venues[ $slug ] = array(
				'id'   => $id,
				'data' => $venue,
			);
			$log( "Venue: {$venue['name']}" );
		}

		$now   = Dates::now();
		$shows = 0;
		foreach ( DemoData::shows() as $show ) {
			$venue = $venues[ $show['venue'] ];
			$start = $now->setTime( $show['hour'] ?? 20, 0 )->modify( sprintf( '%+d days', $show['days'] ) );
			$end   = $start->add( new DateInterval( 'PT2H30M' ) );
			$state = $show['state'] ?? '';

			$headliner = DemoData::artists()[ $show['artists'][0] ];
			$content   = sprintf(
				"%s live at %s.\n\n%s",
				$show['title'],
				$venue['data']['name'],
				$headliner['bio']
			);

			$id = $this->insert_post( PostTypes::SHOW, $show['title'], sanitize_title( $show['title'] ), $content );
			update_post_meta( $id, Keys::SHOW_START, $start->format( Sanitizer::DATETIME_FORMAT ) );
			update_post_meta( $id, Keys::SHOW_END, $end->format( Sanitizer::DATETIME_FORMAT ) );
			update_post_meta( $id, Keys::SHOW_DOORS, $start->sub( new DateInterval( 'PT1H' ) )->format( 'H:i' ) );
			update_post_meta( $id, Keys::SHOW_VENUE, $venue['id'] );
			update_post_meta( $id, Keys::SHOW_ARTISTS, array_map( static fn( string $slug ): int => $artists[ $slug ], $show['artists'] ) );
			update_post_meta( $id, Keys::SHOW_AGE, $show['age'] ?? 0 );
			wp_set_object_terms( $id, $terms[ Taxonomies::GENRE ][ $show['genre'] ], Taxonomies::GENRE );
			wp_set_object_terms( $id, $terms[ Taxonomies::CITY ][ $venue['data']['city'] ], Taxonomies::CITY );
			$this->attach_image( $id, $show['title'] );

			// Tier capacity = share of venue capacity. The sold-out demo show gets 0 everywhere.
			$tiers = array();
			$stock = array();
			foreach ( DemoData::prices()[ $show['genre'] ] as $tier => $price ) {
				$qty            = 'sold_out' === $state ? 0 : (int) floor( $venue['data']['capacity'] * DemoData::tier_split()[ $tier ] );
				$tiers[ $tier ] = array(
					'price' => $price,
					'stock' => $qty,
				);
				$stock[]        = $qty;
			}
			$product_id = $this->products->create( $id, $show['title'], $tiers, true );
			update_post_meta( $id, Keys::SHOW_PRODUCT, $product_id );

			// The same rules the live site uses decide the status.
			$current = 'cancelled' === $state ? ShowStatus::Cancelled : ShowStatus::OnSale;
			$status  = $this->calculator->calculate( $current, $end, $now, $stock );
			update_post_meta( $id, Keys::SHOW_STATUS, $status->value );

			++$shows;
			$log( "Show: {$show['title']} ({$status->value}, product #{$product_id})" );
		}

		return array(
			'artists' => count( $artists ),
			'venues'  => count( $venues ),
			'shows'   => $shows,
		);
	}

	/**
	 * Delete everything seed() created (posts, products, variations, images).
	 * Terms are kept: they are shared vocabulary, not demo content.
	 *
	 * @param callable(string): void $log Progress output.
	 * @return int Number of items deleted.
	 */
	public function reset( callable $log ): int {
		$deleted = 0;

		foreach ( $this->seeded_ids( array( 'product' ) ) as $product_id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( $product ) {
				// Delete variations first, then the parent, through WooCommerce so its lookup tables stay clean.
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child ) {
						$child->delete( true );
					}
				}
				$product->delete( true );
				++$deleted;
			}
		}

		foreach ( $this->seeded_ids( array( PostTypes::SHOW, PostTypes::ARTIST, PostTypes::VENUE ) ) as $post_id ) {
			wp_delete_post( $post_id, true );
			++$deleted;
		}

		foreach ( $this->seeded_ids( array( 'attachment' ) ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
			++$deleted;
		}

		$log( "Deleted {$deleted} items." );
		return $deleted;
	}

	/**
	 * Create genre and city terms if missing.
	 *
	 * @return array<string, array<string, int>> Taxonomy => slug => term ID.
	 * @throws RuntimeException When a term cannot be created.
	 */
	private function ensure_terms(): array {
		$wanted = array(
			Taxonomies::GENRE => array(
				'comedy'     => 'Comedy',
				'live-music' => 'Live Music',
			),
			Taxonomies::CITY  => array(
				'dubai'     => 'Dubai',
				'abu-dhabi' => 'Abu Dhabi',
				'sharjah'   => 'Sharjah',
			),
		);

		$ids = array();
		foreach ( $wanted as $taxonomy => $terms ) {
			foreach ( $terms as $slug => $name ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( ! $term ) {
					$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
					if ( is_wp_error( $created ) ) {
						throw new RuntimeException( esc_html( $created->get_error_message() ) );
					}
					$ids[ $taxonomy ][ $slug ] = (int) $created['term_id'];
				} else {
					$ids[ $taxonomy ][ $slug ] = (int) $term->term_id;
				}
			}
		}
		return $ids;
	}

	/**
	 * Insert a published, seeded post.
	 *
	 * @param string $post_type Post type.
	 * @param string $title     Title.
	 * @param string $slug      Slug.
	 * @param string $content   Content.
	 * @throws RuntimeException On failure.
	 */
	private function insert_post( string $post_type, string $title, string $slug, string $content ): int {
		$id = wp_insert_post(
			array(
				'post_type'    => $post_type,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
				'post_status'  => 'publish',
				'meta_input'   => array( Keys::SEEDED => 1 ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( esc_html( $id->get_error_message() ) );
		}
		return $id;
	}

	/**
	 * Generate artwork and set it as the featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Artwork seed.
	 */
	private function attach_image( int $post_id, string $title ): void {
		$attachment_id = $this->images->create_attachment( $title );
		if ( $attachment_id ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * IDs of seeded posts of the given types.
	 *
	 * @param string[] $post_types Post types.
	 * @param int      $limit      Max results (-1 = all).
	 * @return int[]
	 */
	private function seeded_ids( array $post_types, int $limit = -1 ): array {
		return get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => Keys::SEEDED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- CLI-only maintenance query.
			)
		);
	}
}
