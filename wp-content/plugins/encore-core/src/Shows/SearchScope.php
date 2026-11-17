<?php
/**
 * Limits site search to fan-facing content.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress search includes every public post type, which would surface the
 * hidden "– Tickets" products. Restrict it to shows, artists, venues and pages.
 */
final class SearchScope implements Service {

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'pre_get_posts', array( $this, 'scope' ) );
	}

	/**
	 * Narrow the main search query.
	 *
	 * @param WP_Query $query Query about to run.
	 */
	public function scope( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}
		$query->set( 'post_type', array( PostTypes::SHOW, PostTypes::ARTIST, PostTypes::VENUE, 'page' ) );
	}
}
