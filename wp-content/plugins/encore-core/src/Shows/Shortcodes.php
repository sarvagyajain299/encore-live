<?php
/**
 * [encore_upcoming_shows] and [encore_artist_shows].
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Lets editors drop show listings into any page, post or Elementor
 * shortcode widget.
 *
 *   [encore_upcoming_shows limit="6" city="dubai" genre="comedy"]
 *   [encore_artist_shows artist="layla-haddad"]
 *
 * Shortcode callbacks must RETURN their HTML (never echo), or the output
 * would appear above the post content.
 */
final class Shortcodes implements Service {

	/**
	 * Create with the shared renderer.
	 *
	 * @param ShowListRenderer $renderer Renderer.
	 */
	public function __construct( private readonly ShowListRenderer $renderer = new ShowListRenderer() ) {}

	/**
	 * Register shortcodes.
	 */
	public function register(): void {
		add_shortcode( 'encore_upcoming_shows', array( $this, 'upcoming' ) );
		add_shortcode( 'encore_artist_shows', array( $this, 'artist' ) );
	}

	/**
	 * [encore_upcoming_shows limit="6" city="dubai" genre="comedy"]
	 *
	 * @param array<string, string>|string $atts Attributes ('' when none given).
	 */
	public function upcoming( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit' => '6',
				'city'  => '',
				'genre' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'encore_upcoming_shows'
		);
		return $this->renderer->render( ListFilters::from_atts( $atts ), 'shortcode_upcoming' );
	}

	/**
	 * [encore_artist_shows artist="layla-haddad" limit="6"]
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function artist( $atts ): string {
		$atts = shortcode_atts(
			array(
				'artist' => '',
				'limit'  => '6',
			),
			is_array( $atts ) ? $atts : array(),
			'encore_artist_shows'
		);

		$artist = get_page_by_path( sanitize_title( $atts['artist'] ), OBJECT, PostTypes::ARTIST );
		if ( ! $artist instanceof WP_Post || 'publish' !== $artist->post_status ) {
			// Tell editors (only) why nothing shows; visitors see nothing.
			return current_user_can( 'edit_posts' )
				? '<p class="encore-empty">' . esc_html__( '[encore_artist_shows]: artist not found. Use the artist slug, e.g. artist="layla-haddad".', 'encore-core' ) . '</p>'
				: '';
		}

		$filters = ListFilters::from_atts(
			array(
				'limit'     => $atts['limit'],
				'artist_id' => $artist->ID,
			)
		);
		return $this->renderer->render( $filters, 'shortcode_artist' );
	}
}
