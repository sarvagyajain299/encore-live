<?php
/**
 * Ticket call to action on the show page.
 *
 * Phase 2: price summary and status-aware messaging.
 * Phase 3: the tier selector is printed through the encore_ticket_panel action.
 *
 * Args: show (EncoreCore\Shows\Show).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_show = $args['show'] ?? null;
if ( ! $encore_show ) {
	return;
}
$encore_status = $encore_show->status->value;
?>
<section id="tickets" class="ticket-panel ticket-panel--<?php echo esc_attr( $encore_status ); ?>" aria-labelledby="tickets-title">
	<h2 id="tickets-title" class="ticket-panel__title"><?php esc_html_e( 'Tickets', 'encore-theme' ); ?></h2>

	<?php if ( 'cancelled' === $encore_status ) : ?>
		<p class="ticket-panel__message"><?php esc_html_e( 'This show has been cancelled. Ticket holders will be refunded automatically to their original payment method.', 'encore-theme' ); ?></p>
	<?php elseif ( 'archived' === $encore_status ) : ?>
		<p class="ticket-panel__message"><?php esc_html_e( 'This show has ended.', 'encore-theme' ); ?></p>
	<?php else : ?>
		<?php if ( null !== $encore_show->min_price ) : ?>
			<p class="ticket-panel__price">
				<?php
				echo esc_html(
					$encore_show->max_price && $encore_show->max_price !== $encore_show->min_price
						? encore_format_price( $encore_show->min_price ) . ' – ' . encore_format_price( $encore_show->max_price )
						: encore_format_price( $encore_show->min_price )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
		/**
		 * Prints the purchase UI (tier selector or waitlist form).
		 *
		 * @param EncoreCore\Shows\Show $show Show.
		 */
		do_action( 'encore_ticket_panel', $encore_show );
		?>
	<?php endif; ?>
</section>
