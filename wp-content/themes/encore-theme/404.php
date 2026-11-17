<?php
/**
 * Not found.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container section empty-state empty-state--page">
	<p class="eyebrow">404</p>
	<h1 class="page-title"><?php esc_html_e( 'This show has left the building', 'encore-theme' ); ?></h1>
	<p><?php esc_html_e( 'The page you were looking for doesn’t exist or has moved. Try a search, or browse what’s on.', 'encore-theme' ); ?></p>
	<?php get_search_form(); ?>
	<p><a class="button button--primary" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'Browse all shows', 'encore-theme' ); ?></a></p>
</div>
<?php
get_footer();
