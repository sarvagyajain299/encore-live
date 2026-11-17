<?php
/**
 * Generic card for any post type (venues, posts, search results).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_type = get_post_type_object( (string) get_post_type() );
?>
<article <?php post_class( 'entry-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<div class="entry-card__media">
			<?php encore_theme_image( get_the_ID(), 'encore-card', '(min-width: 1200px) 380px, (min-width: 640px) 50vw, 100vw', array( 'alt' => '' ) ); ?>
		</div>
	<?php endif; ?>
	<div class="entry-card__body">
		<?php if ( $encore_type && is_search() ) : ?>
			<p class="eyebrow"><?php echo esc_html( $encore_type->labels->singular_name ); ?></p>
		<?php endif; ?>
		<h2 class="entry-card__title"><a class="entry-card__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<?php if ( has_excerpt() || get_the_content() ) : ?>
			<p class="entry-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
		<?php endif; ?>
	</div>
</article>
