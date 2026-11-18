<?php
/**
 * Removes a show's waitlist when the show is permanently deleted.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Custom tables have no foreign keys to wp_posts, so nothing removes rows
 * for a deleted show unless we do. Without this, the admin screen filled up
 * with rows for shows that no longer exist (found while taking screenshots).
 *
 * Trashing a show keeps its waitlist (trash can be undone); only the
 * permanent delete removes it. The entries hold personal data, so keeping
 * them with no show to attach them to would also be a privacy problem.
 */
final class ShowCleanup implements Service {

	/**
	 * Constructor.
	 *
	 * @param WaitlistRepository $repo Repository.
	 */
	public function __construct( private WaitlistRepository $repo ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'before_delete_post', array( $this, 'on_delete' ), 10, 2 );
	}

	/**
	 * Delete the waitlist rows of a show that is being permanently deleted.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post (passed since WP 5.5).
	 */
	public function on_delete( $post_id, $post = null ): void {
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( (int) $post_id );
		if ( PostTypes::SHOW !== $type ) {
			return;
		}
		$ids = $this->repo->ids_for_show( (int) $post_id );
		if ( $ids ) {
			$this->repo->delete( $ids, 'show-deleted' );
		}
	}
}
