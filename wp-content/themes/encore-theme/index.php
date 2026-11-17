<?php
/**
 * Fallback template: the bottom of the template hierarchy. Used for blog
 * posts, venue archives and anything without a more specific template.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container section">
	<?php get_template_part( 'template-parts/breadcrumbs' ); ?>

	<?php if ( is_singular() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'prose' ); ?>>
				<h1><?php the_title(); ?></h1>
				<?php the_content(); ?>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<header class="page-header">
			<h1 class="page-title"><?php echo wp_kses_post( is_home() ? __( 'Latest', 'encore-theme' ) : get_the_archive_title() ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="card-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/entry-card' );
				endwhile;
				?>
			</div>
			<?php encore_theme_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Nothing here yet.', 'encore-theme' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php
get_footer();
