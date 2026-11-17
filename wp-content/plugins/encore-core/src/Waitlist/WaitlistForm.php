<?php
/**
 * "Join the waitlist" form on the show page.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Shows\Show;
use EncoreCore\Tickets\TicketLookup;
use EncoreCore\Waitlist\Http\LinkHandler;
use EncoreCore\Waitlist\Http\NoJsHandler;
use EncoreCore\Waitlist\Http\WaitlistController;

defined( 'ABSPATH' ) || exit;

/**
 * Printed in the ticket panel (after the tier selector) whenever at least
 * one tier is sold out. A normal POST form to admin-post.php; waitlist.js
 * upgrades it to fetch() + REST so the page doesn't reload.
 */
final class WaitlistForm implements Service {

	/**
	 * Create.
	 *
	 * @param TicketLookup $tickets Tier reader.
	 */
	public function __construct( private readonly TicketLookup $tickets = new TicketLookup() ) {}

	/**
	 * Hook in after the tier selector (priority 10).
	 */
	public function register(): void {
		add_action( 'encore_ticket_panel', array( $this, 'render' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Enqueue waitlist.js on show pages.
	 */
	public function assets(): void {
		if ( ! is_singular( PostTypes::SHOW ) ) {
			return;
		}
		wp_enqueue_script(
			'encore-waitlist',
			plugins_url( 'assets/js/waitlist.js', ENCORE_CORE_FILE ),
			array(),
			(string) filemtime( ENCORE_CORE_DIR . '/assets/js/waitlist.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Print the form.
	 *
	 * @param Show $show Show.
	 */
	public function render( Show $show ): void {
		if ( ! in_array( $show->status->value, array( 'on_sale', 'sold_out' ), true ) ) {
			return;
		}
		$sold_out = array_filter( $this->tickets->tiers( $show->product_id ), static fn( array $t ): bool => ! $t['in_stock'] );
		if ( ! $sold_out ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only result code from our own redirect.
		$code = isset( $_GET['waitlist'] ) ? sanitize_key( wp_unslash( $_GET['waitlist'] ) ) : '';
		$id   = 'waitlist-' . $show->id;
		?>
		<div class="waitlist" id="waitlist">
			<h3 class="waitlist__title"><?php esc_html_e( 'Join the waitlist', 'encore-core' ); ?></h3>
			<p class="waitlist__intro"><?php esc_html_e( 'If tickets come back, we email fans in the order they confirmed. You’ll get 24 hours to buy.', 'encore-core' ); ?></p>

			<form class="waitlist__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-waitlist-form data-endpoint="<?php echo esc_url( rest_url( 'encore/v1/waitlist' ) ); ?>"
				data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" novalidate>
				<input type="hidden" name="action" value="<?php echo esc_attr( NoJsHandler::ACTION ); ?>">
				<input type="hidden" name="show_id" value="<?php echo esc_attr( (string) $show->id ); ?>">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( WaitlistController::NONCE_ACTION ) ); ?>">

				<p class="field">
					<label for="<?php echo esc_attr( $id ); ?>-tier"><?php esc_html_e( 'Tier', 'encore-core' ); ?></label>
					<select id="<?php echo esc_attr( $id ); ?>-tier" name="variation_id" required>
						<?php foreach ( $sold_out as $tier ) : ?>
							<option value="<?php echo esc_attr( (string) $tier['variation_id'] ); ?>"><?php echo esc_html( $tier['tier'] . ' — ' . encore_format_price( $tier['price'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="field">
					<label for="<?php echo esc_attr( $id ); ?>-name"><?php esc_html_e( 'Name', 'encore-core' ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>-name" name="name" type="text" autocomplete="name" maxlength="100" required>
				</p>
				<p class="field">
					<label for="<?php echo esc_attr( $id ); ?>-email"><?php esc_html_e( 'Email', 'encore-core' ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>-email" name="email" type="email" autocomplete="email" maxlength="190" required>
				</p>
				<p class="field">
					<label for="<?php echo esc_attr( $id ); ?>-phone"><?php esc_html_e( 'Mobile (optional, UAE)', 'encore-core' ); ?></label>
					<input id="<?php echo esc_attr( $id ); ?>-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="050 123 4567">
				</p>
				<p class="field">
					<label for="<?php echo esc_attr( $id ); ?>-qty"><?php esc_html_e( 'Tickets wanted', 'encore-core' ); ?></label>
					<select id="<?php echo esc_attr( $id ); ?>-qty" name="qty">
						<?php for ( $i = 1; $i <= JoinRequest::MAX_QTY; $i++ ) : ?>
							<option value="<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( (string) $i ); ?></option>
						<?php endfor; ?>
					</select>
				</p>

				<?php // Honeypot: hidden from people (and screen readers), irresistible to bots. ?>
				<p class="waitlist__hp" aria-hidden="true">
					<label for="<?php echo esc_attr( $id ); ?>-website">Website</label>
					<input id="<?php echo esc_attr( $id ); ?>-website" name="website" type="text" tabindex="-1" autocomplete="off">
				</p>

				<p class="field field--check">
					<input id="<?php echo esc_attr( $id ); ?>-consent" name="consent" type="checkbox" value="1" required>
					<label for="<?php echo esc_attr( $id ); ?>-consent"><?php esc_html_e( 'Email me about tickets for this show. I can unsubscribe at any time.', 'encore-core' ); ?></label>
				</p>

				<button class="button button--ghost button--block" type="submit"><?php esc_html_e( 'Join the waitlist', 'encore-core' ); ?></button>
				<p class="waitlist__message" role="status" aria-live="polite" data-waitlist-message><?php echo $code ? esc_html( LinkHandler::message( $code ) ) : ''; ?></p>
			</form>
		</div>
		<?php
	}
}
