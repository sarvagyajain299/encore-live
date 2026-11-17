<?php
/**
 * Renders a cached grid of show cards.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by both shortcodes and the Elementor widget (DRY): same query
 * builder, same card markup, same cache.
 *
 * Caching: the rendered HTML is stored in a transient keyed by the filters
 * plus a "cache version" number. Any show, artist or venue change bumps the
 * version, which instantly orphans every old entry (they expire on their own).
 * This avoids hunting down every transient by prefix, which is impossible
 * when transients live in Redis.
 */
final class ShowListRenderer implements Service {

	public const VERSION_OPTION = 'encore_list_cache_version';
	private const TTL           = 12 * HOUR_IN_SECONDS;

	/**
	 * Invalidate on content changes.
	 */
	public function register(): void {
		foreach ( array( PostTypes::SHOW, PostTypes::ARTIST, PostTypes::VENUE ) as $type ) {
			add_action( 'save_post_' . $type, array( $this, 'flush' ) );
		}
		add_action( 'deleted_post', array( $this, 'flush_if_ours' ) );
		add_action( 'trashed_post', array( $this, 'flush_if_ours' ) );
		// Prices and stock badges change without the post being saved.
		add_action( 'encore_show_status_changed', array( $this, 'flush' ) );
	}

	/**
	 * Render (or return cached) cards.
	 *
	 * @param array<string, mixed> $filters   Normalised filters (ListFilters).
	 * @param string               $list_name Analytics list name, e.g. "shortcode_upcoming".
	 */
	public function render( array $filters, string $list_name ): string {
		$key    = ListFilters::cache_key( $filters + array( 'list' => $list_name ), $this->version() );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['html'], $cached['ids'] ) ) {
			// Analytics describes every card on the page, so warm the show models it will need.
			if ( Settings::get( 'gtm_container_id' ) ) {
				array_map( 'encore_get_show', $cached['ids'] );
			}
			return (string) $cached['html'];
		}

		// The listing never paginates: skip the COUNT(*) query.
		$query = encore_query_shows( $filters + array( 'paginate' => false ) );
		$html  = $this->cards( $query->posts, $list_name );

		set_transient(
			$key,
			array(
				'html' => $html,
				'ids'  => wp_list_pluck( $query->posts, 'ID' ),
			),
			self::TTL
		);
		return $html;
	}

	/**
	 * Bump the cache version.
	 */
	public function flush(): void {
		update_option( self::VERSION_OPTION, $this->version() + 1, true );
	}

	/**
	 * Flush only for our post types.
	 *
	 * @param int $post_id Post ID.
	 */
	public function flush_if_ours( int $post_id ): void {
		if ( in_array( get_post_type( $post_id ), array( PostTypes::SHOW, PostTypes::ARTIST, PostTypes::VENUE ), true ) ) {
			$this->flush();
		}
	}

	/**
	 * Current cache version.
	 */
	private function version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Card grid markup. Prefers the theme's card template so shortcodes match
	 * the rest of the site; falls back to the plugin's own minimal template.
	 *
	 * @param \WP_Post[] $posts     Shows.
	 * @param string     $list_name List name.
	 */
	private function cards( array $posts, string $list_name ): string {
		if ( ! $posts ) {
			return '<p class="encore-empty">' . esc_html__( 'No upcoming shows right now. Check back soon.', 'encore-core' ) . '</p>';
		}

		$theme_card = locate_template( 'template-parts/show-card.php' );

		ob_start();
		echo '<div class="card-grid encore-show-list">';
		foreach ( $posts as $index => $post ) {
			$args = array(
				'show'  => encore_get_show( $post ),
				'list'  => $list_name,
				'index' => $index,
			);
			if ( $theme_card ) {
				load_template( $theme_card, false, $args );
			} else {
				include ENCORE_CORE_DIR . '/templates/show-card.php';
			}
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
