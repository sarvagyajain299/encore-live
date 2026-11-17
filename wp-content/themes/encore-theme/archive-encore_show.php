<?php
/**
 * Shows archive (/shows/). The main query is already filtered and sorted by
 * EncoreCore\Shows\ArchiveFilters in pre_get_posts, so this template only loops.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
$encore_past = 'past' === get_query_var( 'show_when' );
?>
<div class="container section">
	<?php get_template_part( 'template-parts/breadcrumbs' ); ?>

	<header class="page-header">
		<h1 class="page-title"><?php $encore_past ? esc_html_e( 'Past shows', 'encore-theme' ) : esc_html_e( 'All shows', 'encore-theme' ); ?></h1>
	</header>

	<?php
	if ( encore_theme_has_core() ) {
		get_template_part( 'template-parts/show-filters' );
	}
	?>

	<p class="results-count" role="status">
		<?php
		printf(
			/* translators: %d: number of shows. */
			esc_html( _n( '%d show', '%d shows', (int) $wp_query->found_posts, 'encore-theme' ) ),
			(int) $wp_query->found_posts
		);
		?>
	</p>

	<?php if ( have_posts() && encore_theme_has_core() ) : ?>
		<div class="card-grid">
			<?php
			$encore_index = 0;
			while ( have_posts() ) :
				the_post();
				get_template_part(
					'template-parts/show-card',
					null,
					array(
						'show'    => encore_get_show(),
						'heading' => 'h2',
						'eager'   => $encore_index < 2,
						'list'    => 'shows_archive',
						'index'   => $encore_index++,
					)
				);
			endwhile;
			?>
		</div>
		<?php encore_theme_pagination(); ?>
	<?php else : ?>
		<div class="empty-state">
			<p><?php esc_html_e( 'No shows match those filters yet.', 'encore-theme' ); ?></p>
			<a class="button button--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'See all upcoming shows', 'encore-theme' ); ?></a>
		</div>
	<?php endif; ?>
</div>
<?php
get_footer();
