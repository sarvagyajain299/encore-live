<?php
/**
 * Fallback show card, used when the active theme has no
 * template-parts/show-card.php (e.g. if someone switches themes).
 *
 * Expects $args['show'] (EncoreCore\Shows\Show).
 *
 * @package EncoreCore
 */

defined( 'ABSPATH' ) || exit;

$encore_show = $args['show'] ?? null;
if ( ! $encore_show ) {
	return;
}
?>
<article class="encore-card">
	<?php echo get_the_post_thumbnail( $encore_show->id, 'medium_large', array( 'alt' => '' ) ); ?>
	<h3><a href="<?php echo esc_url( $encore_show->url ); ?>"><?php echo esc_html( $encore_show->title ); ?></a></h3>
	<p><?php echo esc_html( encore_format_show_date( $encore_show ) ); ?></p>
	<?php if ( $encore_show->venue ) : ?>
		<p><?php echo esc_html( $encore_show->venue['title'] ); ?></p>
	<?php endif; ?>
</article>
