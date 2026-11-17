<?php
/**
 * Custom post types: shows, artists, venues.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Content;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the three content types the site is built around.
 */
final class PostTypes implements Service {

	public const SHOW   = 'encore_show';
	public const ARTIST = 'encore_artist';
	public const VENUE  = 'encore_venue';

	/**
	 * Hook registration onto init, the earliest point post types should be registered.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register all three post types.
	 */
	public function register_post_types(): void {
		register_post_type(
			self::SHOW,
			$this->args(
				__( 'Shows', 'encore-core' ),
				__( 'Show', 'encore-core' ),
				'shows',
				'dashicons-tickets-alt',
				array( 'title', 'editor', 'thumbnail', 'excerpt' )
			)
		);

		register_post_type(
			self::ARTIST,
			$this->args(
				__( 'Artists', 'encore-core' ),
				__( 'Artist', 'encore-core' ),
				'artists',
				'dashicons-microphone',
				array( 'title', 'editor', 'thumbnail' )
			)
		);

		register_post_type(
			self::VENUE,
			$this->args(
				__( 'Venues', 'encore-core' ),
				__( 'Venue', 'encore-core' ),
				'venues',
				'dashicons-location',
				array( 'title', 'editor', 'thumbnail' )
			)
		);
	}

	/**
	 * Shared arguments, so the three types behave consistently.
	 *
	 * @param string   $plural   Plural label.
	 * @param string   $singular Singular label.
	 * @param string   $slug     URL slug and archive path.
	 * @param string   $icon     Dashicon name.
	 * @param string[] $supports Core features.
	 * @return array<string, mixed>
	 */
	private function args( string $plural, string $singular, string $slug, string $icon, array $supports ): array {
		return array(
			'labels'       => array(
				'name'          => $plural,
				'singular_name' => $singular,
				/* translators: %s: singular post type name. */
				'add_new_item'  => sprintf( __( 'Add new %s', 'encore-core' ), $singular ),
				/* translators: %s: singular post type name. */
				'edit_item'     => sprintf( __( 'Edit %s', 'encore-core' ), $singular ),
				'menu_name'     => $plural,
			),
			'public'       => true,
			'has_archive'  => $slug,
			'rewrite'      => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'menu_icon'    => $icon,
			// 'custom-fields' is required for registered meta to appear in the REST API.
			'supports'     => array_merge( $supports, array( 'custom-fields' ) ),
			// show_in_rest enables the block editor and the /wp/v2 REST endpoints.
			'show_in_rest' => true,
		);
	}
}
