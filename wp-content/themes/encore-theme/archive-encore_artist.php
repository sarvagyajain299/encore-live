<?php
/**
 * Artists archive (/artists/), alphabetical.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container section">
	<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Artists', 'encore-theme' ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="card-grid card-grid--small">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/artist-card' );
			endwhile;
			?>
		</div>
		<?php encore_theme_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No artists yet.', 'encore-theme' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
