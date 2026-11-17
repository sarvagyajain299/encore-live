<?php
/**
 * Taxonomies: genre and city.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Content;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Genre groups shows and artists; city groups shows and venues.
 */
final class Taxonomies implements Service {

	public const GENRE = 'encore_genre';
	public const CITY  = 'encore_city';

	/**
	 * Hook registration onto init.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register both taxonomies.
	 */
	public function register_taxonomies(): void {
		register_taxonomy(
			self::GENRE,
			array( PostTypes::SHOW, PostTypes::ARTIST ),
			$this->args( __( 'Genres', 'encore-core' ), __( 'Genre', 'encore-core' ), 'genre' )
		);

		register_taxonomy(
			self::CITY,
			array( PostTypes::SHOW, PostTypes::VENUE ),
			$this->args( __( 'Cities', 'encore-core' ), __( 'City', 'encore-core' ), 'city' )
		);
	}

	/**
	 * Shared arguments.
	 *
	 * @param string $plural   Plural label.
	 * @param string $singular Singular label.
	 * @param string $slug     URL slug.
	 * @return array<string, mixed>
	 */
	private function args( string $plural, string $singular, string $slug ): array {
		return array(
			'labels'            => array(
				'name'          => $plural,
				'singular_name' => $singular,
			),
			// Hierarchical = category-style checkboxes; editors pick from a fixed list.
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		);
	}
}
