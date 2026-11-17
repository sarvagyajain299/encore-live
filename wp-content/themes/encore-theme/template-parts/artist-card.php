<?php
/**
 * Artist card. Uses the current post in the loop, or $args['post'].
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_post = get_post( $args['post'] ?? null );
if ( ! $encore_post ) {
	return;
}
$encore_genres = get_the_terms( $encore_post, 'encore_genre' );
?>
<article class="artist-card">
	<div class="artist-card__media">
		<?php encore_theme_image( $encore_post->ID, 'encore-card', '(min-width: 1200px) 280px, (min-width: 640px) 33vw, 50vw', array( 'alt' => '' ) ); ?>
	</div>
	<h3 class="artist-card__name">
		<a class="artist-card__link" href="<?php echo esc_url( (string) get_permalink( $encore_post ) ); ?>"><?php echo esc_html( get_the_title( $encore_post ) ); ?></a>
	</h3>
	<?php if ( is_array( $encore_genres ) && $encore_genres ) : ?>
		<p class="artist-card__genre"><?php echo esc_html( $encore_genres[0]->name ); ?></p>
	<?php endif; ?>
</article>
