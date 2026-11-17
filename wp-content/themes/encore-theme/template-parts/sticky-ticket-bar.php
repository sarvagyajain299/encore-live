<?php
/**
 * Sticky "Get tickets" bar for small screens.
 *
 * Shown only while the real ticket panel is off-screen (main.js uses
 * IntersectionObserver), so it never covers the thing it points to.
 *
 * Args: show (EncoreCore\Shows\Show).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_show = $args['show'] ?? null;
if ( ! $encore_show || ! in_array( $encore_show->status->value, array( 'on_sale', 'sold_out' ), true ) ) {
	return;
}
$encore_sold_out = 'sold_out' === $encore_show->status->value;
?>
<div class="sticky-bar" data-sticky-bar>
	<div class="sticky-bar__info">
		<span class="sticky-bar__title"><?php echo esc_html( $encore_show->title ); ?></span>
		<?php if ( ! $encore_sold_out && null !== $encore_show->min_price ) : ?>
			<span class="sticky-bar__price"><?php echo esc_html( encore_theme_price_from( $encore_show ) ); ?></span>
		<?php endif; ?>
	</div>
	<a class="button button--primary" href="#tickets">
		<?php $encore_sold_out ? esc_html_e( 'Join waitlist', 'encore-theme' ) : esc_html_e( 'Get tickets', 'encore-theme' ); ?>
	</a>
</div>
