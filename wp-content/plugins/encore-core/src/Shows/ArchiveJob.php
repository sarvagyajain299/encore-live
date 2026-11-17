<?php
/**
 * Daily job that archives past shows, and the 301 for archived URLs.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Dates;
use EncoreCore\Support\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Why archive and redirect instead of leaving past shows live?
 *  - A past event page is a dead end for searchers ("tickets" for a show that
 *    happened in March) and Google drops expired events from rich results.
 *  - A 301 ("moved permanently") to the headliner's artist page passes most
 *    of the old URL's link equity to a page that stays relevant: the artist's
 *    upcoming dates. A 404 would throw that equity away; a 302 would tell
 *    Google to keep the old URL indexed.
 *  - Archived URLs leave the XML sitemap (YoastIntegration), so crawlers stop
 *    being sent to redirects.
 */
final class ArchiveJob implements Service {

	public const HOOK = 'encore_archive_past_shows';

	/**
	 * Create.
	 *
	 * @param ShowStatusUpdater $updater Status writer (uses the same calculator rules).
	 */
	public function __construct( private readonly ShowStatusUpdater $updater = new ShowStatusUpdater() ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'template_redirect', array( $this, 'redirect_archived' ), 1 );
	}

	/**
	 * Schedule the daily WP-Cron event once (03:00 Dubai time, off-peak).
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$next = ( new \DateTimeImmutable( 'tomorrow 03:00', wp_timezone() ) )->getTimestamp();
			wp_schedule_event( $next, 'daily', self::HOOK );
		}
	}

	/**
	 * Archive every show that has ended.
	 *
	 * @return int Shows archived.
	 */
	public function run(): int {
		$ids = get_posts(
			array(
				'post_type'      => PostTypes::SHOW,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- daily batch.
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily cron, not a page view.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => Keys::SHOW_END,
						'value'   => Dates::now()->format( Sanitizer::DATETIME_FORMAT ),
						'compare' => '<',
					),
					array(
						'key'     => Keys::SHOW_STATUS,
						'value'   => array( ShowStatus::OnSale->value, ShowStatus::SoldOut->value ),
						'compare' => 'IN',
					),
				),
			)
		);

		$archived = 0;
		foreach ( $ids as $id ) {
			if ( ShowStatus::Archived === $this->updater->refresh( (int) $id ) ) {
				++$archived;
			}
		}
		return $archived;
	}

	/**
	 * 301 an archived show to its headliner (or the shows archive if it has none).
	 */
	public function redirect_archived(): void {
		if ( ! is_singular( PostTypes::SHOW ) ) {
			return;
		}
		$show = encore_get_show( get_queried_object_id() );
		if ( ! $show || ShowStatus::Archived !== $show->status ) {
			return;
		}
		$target = $show->headliner()['url'] ?? (string) get_post_type_archive_link( PostTypes::SHOW );
		wp_safe_redirect( $target, 301, 'Encore Live' );
		exit;
	}
}
