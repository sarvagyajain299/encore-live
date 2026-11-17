<?php
/**
 * Search results (shows, artists, venues, pages; scoped by encore-core).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
global $wp_query;
?>
<div class="container section">
	<header class="page-header">
		<h1 class="page-title">
			<?php
			printf(
				/* translators: %s: search query. */
				esc_html__( 'Search results for “%s”', 'encore-theme' ),
				esc_html( get_search_query() )
			);
			?>
		</h1>
		<?php get_search_form(); ?>
		<p class="results-count" role="status">
			<?php
			printf(
				/* translators: %d: number of results. */
				esc_html( _n( '%d result', '%d results', (int) $wp_query->found_posts, 'encore-theme' ) ),
				(int) $wp_query->found_posts
			);
			?>
		</p>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="card-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				if ( 'encore_show' === get_post_type() && encore_theme_has_core() ) {
					get_template_part(
						'template-parts/show-card',
						null,
						array(
							'show'    => encore_get_show(),
							'heading' => 'h2',
						)
					);
				} else {
					get_template_part( 'template-parts/entry-card' );
				}
			endwhile;
			?>
		</div>
		<?php encore_theme_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing matched. Try an artist name, a venue or a city.', 'encore-theme' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
