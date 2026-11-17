<?php
/**
 * Columns on the Shows admin list.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Admin;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Meta\Keys;
use EncoreCore\Support\Dates;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Show date, Venue and Status columns, and makes Show date sortable.
 */
final class ShowListColumns implements Service {

	private const COL_START  = 'encore_start';
	private const COL_VENUE  = 'encore_venue';
	private const COL_STATUS = 'encore_status';

	/**
	 * Hook the column filters and the sort handler.
	 */
	public function register(): void {
		add_filter( 'manage_' . PostTypes::SHOW . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostTypes::SHOW . '_posts_custom_column', array( $this, 'render' ), 10, 2 );
		add_filter( 'manage_edit-' . PostTypes::SHOW . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'pre_get_posts', array( $this, 'sort' ) );
	}

	/**
	 * Insert our columns after the title, replacing the publish date
	 * (the show date is what editors care about).
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		unset( $columns['date'] );

		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result[ self::COL_START ]  = __( 'Show date', 'encore-core' );
				$result[ self::COL_VENUE ]  = __( 'Venue', 'encore-core' );
				$result[ self::COL_STATUS ] = __( 'Status', 'encore-core' );
				// TODO(sarvagya): add a "Doors" column showing Keys::SHOW_DOORS (e.g. "19:00").
				// Hint: add a COL_DOORS constant, add it here, then add a `case` in render()
				// that echoes esc_html( $doors ) or '&mdash;' when empty. Not sortable.
			}
		}
		return $result;
	}

	/**
	 * Print one cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Show ID.
	 */
	public function render( string $column, int $post_id ): void {
		switch ( $column ) {
			case self::COL_START:
				$start = Dates::from_meta( (string) get_post_meta( $post_id, Keys::SHOW_START, true ) );
				echo $start ? esc_html( wp_date( 'j M Y, g:i a', $start->getTimestamp() ) ) : '&mdash;';
				break;

			case self::COL_VENUE:
				$venue_id = (int) get_post_meta( $post_id, Keys::SHOW_VENUE, true );
				if ( $venue_id ) {
					printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $venue_id ) ), esc_html( get_the_title( $venue_id ) ) );
				} else {
					echo '&mdash;';
				}
				break;

			case self::COL_STATUS:
				$status = ShowStatus::tryFrom( (string) get_post_meta( $post_id, Keys::SHOW_STATUS, true ) ) ?? ShowStatus::OnSale;
				printf( '<span class="encore-status encore-status--%s">%s</span>', esc_attr( $status->value ), esc_html( $status->label() ) );
				break;
		}
	}

	/**
	 * Declare Show date sortable. The value becomes the ?orderby= query arg.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable( array $columns ): array {
		$columns[ self::COL_START ] = self::COL_START;
		return $columns;
	}

	/**
	 * Translate ?orderby=encore_start into a meta sort. Also make it the
	 * default order of the Shows screen (newest show first).
	 *
	 * @param WP_Query $query The query being prepared.
	 */
	public function sort( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || PostTypes::SHOW !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( '' !== $orderby && self::COL_START !== $orderby ) {
			return;
		}

		// Stored as 'Y-m-d H:i:s', so string order is chronological order.
		$query->set( 'meta_key', Keys::SHOW_START ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only list, bounded by pagination.
		$query->set( 'orderby', 'meta_value' );
		if ( '' === $orderby ) {
			$query->set( 'order', 'DESC' );
		}
	}
}
