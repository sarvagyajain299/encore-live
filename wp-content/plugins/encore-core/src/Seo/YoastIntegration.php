<?php
/**
 * Yoast SEO integration: schema piece, robots, canonicals, sitemap.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seo;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Meta\Keys;
use EncoreCore\Shows\ArchiveFilters;

defined( 'ABSPATH' ) || exit;

/**
 * Every hook here is a Yoast filter, so with Yoast inactive nothing runs.
 */
final class YoastIntegration implements Service {

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'wpseo_schema_graph_pieces', array( $this, 'add_event_piece' ), 11, 2 );
		add_filter( 'wpseo_robots', array( $this, 'robots' ) );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'exclude_archived' ) );
	}

	/**
	 * Add the Event piece to Yoast's graph.
	 *
	 * @param array<int, object> $pieces  Graph pieces.
	 * @param object             $context Meta tags context.
	 * @return array<int, object>
	 */
	public function add_event_piece( $pieces, $context ): array {
		unset( $context );
		$pieces[] = new EventSchemaPiece();
		return $pieces;
	}

	/**
	 * Filtered archives (?show_city=… etc.) are near-duplicates of /shows/:
	 * keep them out of the index but let crawlers follow the links to shows.
	 *
	 * No canonical is set on them: Yoast drops the canonical on noindex pages,
	 * because noindex plus a canonical pointing elsewhere are conflicting signals.
	 *
	 * @param string $robots Robots directive.
	 */
	public function robots( $robots ): string {
		if ( $this->is_filtered_archive() ) {
			return 'noindex, follow';
		}
		return (string) $robots;
	}

	/**
	 * Archived shows 301 to their artist, so they don't belong in the sitemap.
	 *
	 * @param int[] $ids Excluded IDs.
	 * @return int[]
	 */
	public function exclude_archived( $ids ): array {
		$archived = get_posts(
			array(
				'post_type'      => PostTypes::SHOW,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- sitemap build, cached by Yoast.
				'no_found_rows'  => true,
				'meta_key'       => Keys::SHOW_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => ShowStatus::Archived->value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return array_values( array_unique( array_merge( array_map( 'intval', (array) $ids ), $archived ) ) );
	}

	/**
	 * Shows archive with any filter applied.
	 */
	private function is_filtered_archive(): bool {
		return is_post_type_archive( PostTypes::SHOW ) && ArchiveFilters::is_filtered();
	}
}
