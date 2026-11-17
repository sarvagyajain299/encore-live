<?php
/**
 * Small presentation helpers used by templates.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the encore-core template API available? Templates check this so the
 * theme still renders (without show data) if the plugin is switched off.
 */
function encore_theme_has_core(): bool {
	return function_exists( 'encore_get_show' );
}

/**
 * Status badge for shows that are not simply on sale.
 *
 * @param \EncoreCore\Shows\Show $show Show.
 */
function encore_theme_status_badge( $show ): void {
	if ( 'on_sale' === $show->status->value ) {
		return;
	}
	printf(
		'<span class="badge badge--%1$s">%2$s</span>',
		esc_attr( $show->status->value ),
		esc_html( 'archived' === $show->status->value ? __( 'Ended', 'encore-theme' ) : $show->status->label() )
	);
}

/**
 * "From AED 95" line, or nothing when there are no prices.
 *
 * @param \EncoreCore\Shows\Show $show Show.
 */
function encore_theme_price_from( $show ): string {
	if ( null === $show->min_price ) {
		return '';
	}
	/* translators: %s: formatted price, e.g. "AED 95". */
	return sprintf( __( 'From %s', 'encore-theme' ), encore_format_price( $show->min_price ) );
}

/**
 * Accessible numbered pagination.
 */
function encore_theme_pagination(): void {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => __( 'Previous', 'encore-theme' ),
			'next_text'          => __( 'Next', 'encore-theme' ),
			'screen_reader_text' => __( 'Results pages', 'encore-theme' ),
			'class'              => 'pagination',
		)
	);
}

/**
 * Featured image with explicit sizes for srcset.
 *
 * WordPress adds width/height (prevents layout shift) and srcset; we supply
 * `sizes`, so the browser can pick the right file before CSS loads.
 *
 * @param int                  $post_id Post ID.
 * @param string               $size    Registered size.
 * @param string               $sizes   The sizes attribute.
 * @param array<string, mixed> $attr    Extra attributes (loading, fetchpriority, alt, class).
 */
function encore_theme_image( int $post_id, string $size, string $sizes, array $attr = array() ): void {
	$thumbnail_id = get_post_thumbnail_id( $post_id );
	if ( ! $thumbnail_id ) {
		echo '<div class="media-fallback" aria-hidden="true"></div>';
		return;
	}
	echo wp_get_attachment_image( $thumbnail_id, $size, false, array_merge( array( 'sizes' => $sizes ), $attr ) );
}
