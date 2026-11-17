<?php
/**
 * Standard page (also renders WooCommerce cart, checkout and account pages).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<div class="container section">
		<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
		<article <?php post_class( 'prose prose--wide' ); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	</div>
	<?php
endwhile;

get_footer();
