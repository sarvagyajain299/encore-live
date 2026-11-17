<?php
/**
 * Tier selector on the show page.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Tickets;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Shows\Show;
use EncoreCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prints a plain HTML form that posts to the cart with WooCommerce's standard
 * add-to-cart parameters. With no JS it still works: WooCommerce's form
 * handler adds the item (running our validation) and the fan lands on the cart.
 *
 * Why not the Store API from JS? The standard form is progressive, has
 * no client-side cart state to keep in sync, and the server-side validation is
 * identical, because the Store API also fires woocommerce_add_to_cart_validation.
 */
final class TicketPanel implements Service {

	/**
	 * Below this many tickets left, show an "Only N left" hint.
	 *
	 * TODO(sarvagya): let site owners change this without editing the plugin.
	 * Hint: add a private method low_stock_threshold() that returns
	 * (int) apply_filters( 'encore_low_stock_threshold', self::LOW_STOCK, $show ),
	 * use it in render() instead of self::LOW_STOCK, and document the filter
	 * with a docblock like the one on encore_show_status_changed.
	 */
	private const LOW_STOCK = 10;

	/**
	 * Create with the tier reader.
	 *
	 * @param TicketLookup $tickets Tier reader.
	 */
	public function __construct( private readonly TicketLookup $tickets = new TicketLookup() ) {}

	/**
	 * Hook into the theme's ticket panel and enqueue the small enhancement script.
	 */
	public function register(): void {
		add_action( 'encore_ticket_panel', array( $this, 'render' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Enqueue tickets.js on show pages only.
	 */
	public function assets(): void {
		if ( ! is_singular( PostTypes::SHOW ) ) {
			return;
		}
		$file = ENCORE_CORE_DIR . '/assets/js/tickets.js';
		wp_enqueue_script(
			'encore-tickets',
			plugins_url( 'assets/js/tickets.js', ENCORE_CORE_FILE ),
			array(),
			(string) filemtime( $file ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Print the selector.
	 *
	 * @param Show $show Show.
	 */
	public function render( Show $show ): void {
		$tiers = $this->tiers_for( $show );
		if ( ! $tiers ) {
			return;
		}

		$in_stock = array_filter( $tiers, static fn( array $tier ): bool => $tier['in_stock'] );
		if ( ! $in_stock ) {
			// Fully sold out: the waitlist (Phase 5) hooks in at a later priority.
			printf( '<p class="ticket-panel__message">%s</p>', esc_html__( 'All tiers are sold out.', 'encore-core' ) );
			return;
		}

		$max_order = Settings::max_tickets_per_order();
		$first     = array_key_first( $in_stock );
		?>
		<form class="tier-form" method="post" action="<?php echo esc_url( wc_get_cart_url() ); ?>" data-tier-form>
			<fieldset class="tier-form__tiers">
				<legend class="tier-form__legend"><?php esc_html_e( 'Choose your tier', 'encore-core' ); ?></legend>
				<?php foreach ( $tiers as $index => $tier ) : ?>
					<?php $max = null === $tier['stock'] ? $max_order : min( $max_order, $tier['stock'] ); ?>
					<label class="tier<?php echo $tier['in_stock'] ? '' : ' tier--sold-out'; ?>">
						<input type="radio" name="variation_id" value="<?php echo esc_attr( (string) $tier['variation_id'] ); ?>"
							data-max="<?php echo esc_attr( (string) max( 0, $max ) ); ?>"
							data-tier="<?php echo esc_attr( $tier['tier'] ); ?>"
							data-price="<?php echo esc_attr( (string) $tier['price'] ); ?>"
							<?php checked( $index, $first ); ?>
							<?php disabled( ! $tier['in_stock'] ); ?> required>
						<span class="tier__name"><?php echo esc_html( $tier['tier'] ); ?></span>
						<span class="tier__price"><?php echo esc_html( encore_format_price( $tier['price'] ) ); ?></span>
						<span class="tier__stock">
							<?php
							if ( ! $tier['in_stock'] ) {
								esc_html_e( 'Sold out', 'encore-core' );
							} elseif ( null !== $tier['stock'] && $tier['stock'] <= self::LOW_STOCK ) {
								/* translators: %d: tickets left. */
								echo esc_html( sprintf( _n( 'Only %d left', 'Only %d left', $tier['stock'], 'encore-core' ), $tier['stock'] ) );
							}
							?>
						</span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="tier-form__qty">
				<label for="encore-qty"><?php esc_html_e( 'Tickets', 'encore-core' ); ?></label>
				<select id="encore-qty" name="quantity" data-qty>
					<?php for ( $i = 1; $i <= $max_order; $i++ ) : ?>
						<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( (string) $i ); ?></option>
					<?php endfor; ?>
				</select>
				<p class="tier-form__hint">
					<?php
					/* translators: %d: maximum tickets per order. */
					echo esc_html( sprintf( __( 'Maximum %d tickets per order.', 'encore-core' ), $max_order ) );
					?>
				</p>
			</div>

			<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( (string) $show->product_id ); ?>">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $show->product_id ); ?>">
			<button class="button button--primary button--block" type="submit"><?php esc_html_e( 'Add to cart', 'encore-core' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Tiers when the show is buyable, else none.
	 *
	 * @param Show $show Show.
	 * @return array<int, array<string, mixed>>
	 */
	private function tiers_for( Show $show ): array {
		if ( ! in_array( $show->status->value, array( 'on_sale', 'sold_out' ), true ) || ! $show->product_id ) {
			return array();
		}
		return $this->tickets->tiers( $show->product_id );
	}
}
