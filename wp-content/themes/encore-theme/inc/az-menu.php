<?php
/**
 * "Shows A–Z" mega-menu data.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

const ENCORE_THEME_AZ_TRANSIENT = 'encore_theme_az_index';

/**
 * Artists grouped by first letter, e.g. [ 'C' => [ [title, url], ... ] ].
 *
 * The menu is on every page, so the grouped list is cached in a transient
 * (one option row, or Redis with an object cache) and rebuilt only when an
 * artist changes. "The Creek Street Trio" files under C, like a record shop.
 *
 * @return array<string, array<int, array{title: string, url: string}>>
 */
function encore_theme_az_index(): array {
	$cached = get_transient( ENCORE_THEME_AZ_TRANSIENT );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$artists = get_posts(
		array(
			'post_type'      => 'encore_artist',
			'post_status'    => 'publish',
			'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- full A–Z list, cached.
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);

	$index = array();
	foreach ( $artists as $artist ) {
		$title  = get_the_title( $artist );
		$sort   = preg_replace( '/^the\s+/i', '', $title );
		$letter = strtoupper( remove_accents( mb_substr( (string) $sort, 0, 1 ) ) );
		$letter = ctype_alpha( $letter ) ? $letter : '#';

		$index[ $letter ][] = array(
			'title' => $title,
			'url'   => (string) get_permalink( $artist ),
			'sort'  => strtolower( (string) $sort ),
		);
	}

	ksort( $index );
	foreach ( $index as &$group ) {
		usort( $group, static fn( array $a, array $b ): int => strcmp( $a['sort'], $b['sort'] ) );
	}
	unset( $group );

	set_transient( ENCORE_THEME_AZ_TRANSIENT, $index, DAY_IN_SECONDS );
	return $index;
}

/**
 * Drop the cache when an artist is saved, trashed or deleted.
 *
 * @param int $post_id Post ID.
 */
function encore_theme_az_flush( int $post_id ): void {
	if ( 'encore_artist' === get_post_type( $post_id ) ) {
		delete_transient( ENCORE_THEME_AZ_TRANSIENT );
	}
}
add_action( 'save_post', 'encore_theme_az_flush' );
add_action( 'deleted_post', 'encore_theme_az_flush' );
add_action( 'trashed_post', 'encore_theme_az_flush' );
