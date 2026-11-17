<?php
/**
 * Show card.
 *
 * Args:
 *  - show     EncoreCore\Shows\Show (required)
 *  - heading  'h2' | 'h3' (default h3)
 *  - eager    bool, load the image eagerly (first cards above the fold)
 *  - list     string, list name for analytics (Phase 6)
 *  - index    int, position in the list for analytics
 *
 * The whole card is clickable through the title link, stretched with CSS.
 * There is one link per card, so screen readers announce it once.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_show = $args['show'] ?? null;
if ( ! $encore_show ) {
	return;
}
$encore_heading = $args['heading'] ?? 'h3';
$encore_heading = in_array( $encore_heading, array( 'h2', 'h3' ), true ) ? $encore_heading : 'h3';
$encore_venue   = $encore_show->venue;
?>
<article class="show-card show-card--<?php echo esc_attr( $encore_show->status->value ); ?>"
	data-show-id="<?php echo esc_attr( (string) $encore_show->id ); ?>"
	data-list="<?php echo esc_attr( $args['list'] ?? '' ); ?>"
	data-index="<?php echo esc_attr( (string) ( $args['index'] ?? 0 ) ); ?>">
	<div class="show-card__media">
		<?php
		encore_theme_image(
			$encore_show->id,
			'encore-card',
			'(min-width: 1200px) 380px, (min-width: 640px) 50vw, 100vw',
			array(
				'alt'     => '', // Decorative: the title next to it already names the show.
				'loading' => empty( $args['eager'] ) ? 'lazy' : 'eager',
			)
		);
		?>
		<?php encore_theme_status_badge( $encore_show ); ?>
	</div>
	<div class="show-card__body">
		<p class="show-card__date">
			<?php if ( $encore_show->start ) : ?>
				<time datetime="<?php echo esc_attr( $encore_show->start->format( 'c' ) ); ?>"><?php echo esc_html( encore_format_show_date( $encore_show ) ); ?></time>
			<?php endif; ?>
		</p>
		<<?php echo esc_html( $encore_heading ); ?> class="show-card__title">
			<a class="show-card__link" href="<?php echo esc_url( $encore_show->url ); ?>"><?php echo esc_html( $encore_show->title ); ?></a>
		</<?php echo esc_html( $encore_heading ); ?>>
		<?php if ( $encore_venue ) : ?>
			<p class="show-card__venue"><?php echo esc_html( $encore_venue['title'] . ( $encore_venue['city'] ? ', ' . $encore_venue['city'] : '' ) ); ?></p>
		<?php endif; ?>
		<?php if ( $encore_show->is_on_sale() && null !== $encore_show->min_price ) : ?>
			<p class="show-card__price"><?php echo esc_html( encore_theme_price_from( $encore_show ) ); ?></p>
		<?php endif; ?>
	</div>
</article>
