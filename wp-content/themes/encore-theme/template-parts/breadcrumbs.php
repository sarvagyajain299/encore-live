<?php
/**
 * Breadcrumbs.
 *
 * Prefers Yoast's breadcrumbs, which also add BreadcrumbList structured data
 * to Yoast's schema graph. Falls back to a simple trail when Yoast is off.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

if ( is_front_page() ) {
	return;
}

if ( function_exists( 'yoast_breadcrumb' ) ) {
	yoast_breadcrumb( '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'encore-theme' ) . '">', '</nav>' );
	return;
}

$encore_crumbs = array(
	array( __( 'Home', 'encore-theme' ), home_url( '/' ) ),
);
$encore_type   = is_singular() ? get_post_type() : get_query_var( 'post_type' );
if ( is_string( $encore_type ) && in_array( $encore_type, array( 'encore_show', 'encore_artist', 'encore_venue' ), true ) ) {
	$encore_object   = get_post_type_object( $encore_type );
	$encore_crumbs[] = array( $encore_object ? $encore_object->labels->name : '', is_singular() ? get_post_type_archive_link( $encore_type ) : '' );
}
if ( is_singular() ) {
	$encore_crumbs[] = array( get_the_title(), '' );
}
?>
<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'encore-theme' ); ?>">
	<ol>
		<?php foreach ( $encore_crumbs as $encore_crumb ) : ?>
			<li>
				<?php if ( $encore_crumb[1] ) : ?>
					<a href="<?php echo esc_url( $encore_crumb[1] ); ?>"><?php echo esc_html( $encore_crumb[0] ); ?></a>
				<?php else : ?>
					<span aria-current="page"><?php echo esc_html( $encore_crumb[0] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
