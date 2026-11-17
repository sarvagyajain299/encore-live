<?php
/**
 * Keeps the per-artist lookup meta in sync.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Contracts\Service;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * The artist list is stored as ONE serialized array (good for ordering:
 * headliner first). Serialized data can't be queried precisely, though:
 * "shows with artist 18" would need LIKE '%i:18;%', which is slow and fragile.
 *
 * So whenever the list changes, we also write one plain `_encore_artist` row
 * per artist. Queries then use an exact '=' match on that key. This is
 * deliberate denormalisation: the list is the source of truth, the rows
 * are an index.
 */
final class ArtistIndex implements Service {

	/**
	 * Listen for any write to the artist list, whatever the source (meta box, REST, CLI).
	 */
	public function register(): void {
		add_action( 'added_post_meta', array( $this, 'sync' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'clear' ), 10, 3 );
	}

	/**
	 * Rebuild the index rows for a show.
	 *
	 * @param int|int[] $meta_id    Meta row ID (unused).
	 * @param int       $post_id    Post ID.
	 * @param string    $meta_key   Key that changed.
	 * @param mixed     $meta_value New value.
	 */
	public function sync( $meta_id, int $post_id, string $meta_key, $meta_value ): void {
		unset( $meta_id );
		if ( Keys::SHOW_ARTISTS !== $meta_key ) {
			return;
		}

		delete_post_meta( $post_id, Keys::SHOW_ARTIST_INDEX );
		foreach ( Sanitizer::id_list( maybe_unserialize( $meta_value ) ) as $artist_id ) {
			add_post_meta( $post_id, Keys::SHOW_ARTIST_INDEX, $artist_id );
		}
	}

	/**
	 * Remove index rows when the list itself is deleted.
	 *
	 * @param int[]  $meta_ids Meta row IDs (unused).
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Key that was deleted.
	 */
	public function clear( $meta_ids, int $post_id, string $meta_key ): void {
		unset( $meta_ids );
		if ( Keys::SHOW_ARTISTS === $meta_key ) {
			delete_post_meta( $post_id, Keys::SHOW_ARTIST_INDEX );
		}
	}
}
