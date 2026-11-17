<?php
/**
 * Registers post meta with WordPress.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Meta;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Calling register_post_meta() tells WordPress the type, default and sanitizer
 * of each key. That gives us three things:
 *  1. Sanitizing on EVERY write path (meta box, REST, WP-CLI, update_post_meta).
 *  2. Typed values in the REST API (show_in_rest) for the block editor and our routes.
 *  3. An auth_callback, so the REST API only lets editors of that post write it.
 */
final class MetaRegistry implements Service {

	/**
	 * Hook onto init.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register every field for every post type.
	 */
	public function register_meta(): void {
		foreach ( array( PostTypes::SHOW, PostTypes::ARTIST, PostTypes::VENUE ) as $post_type ) {
			foreach ( Fields::for_post_type( $post_type ) as $field ) {
				register_post_meta(
					$post_type,
					$field['key'],
					array(
						'type'              => $field['type'],
						'single'            => true,
						'default'           => $field['default'],
						'sanitize_callback' => static fn( $value ) => call_user_func( $field['sanitize'], $value ),
						'auth_callback'     => array( $this, 'can_edit' ),
						'show_in_rest'      => isset( $field['schema'] ) ? array( 'schema' => $field['schema'] ) : true,
					)
				);
			}
		}
	}

	/**
	 * Only users who can edit this specific post may change its protected meta.
	 *
	 * @param bool   $allowed  Default decision (false for protected keys).
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 */
	public function can_edit( bool $allowed, string $meta_key, int $post_id ): bool {
		unset( $allowed, $meta_key );
		return current_user_can( 'edit_post', $post_id );
	}
}
