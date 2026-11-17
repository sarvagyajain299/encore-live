<?php
/**
 * No-JS fallback (admin-post.php) for the waitlist form.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Http;

use EncoreCore\Contracts\Service;
use EncoreCore\Waitlist\JoinHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Without JavaScript, the form posts to admin-post.php?action=encore_waitlist_join.
 * The same JoinHandler runs, then we redirect back to the show
 * (Post/Redirect/Get, so a refresh can't resubmit). Only a result CODE goes
 * in the URL, never the email or name.
 */
final class NoJsHandler implements Service {

	public const ACTION = 'encore_waitlist_join';

	/**
	 * Create.
	 *
	 * @param JoinHandler $handler Shared pipeline.
	 */
	public function __construct( private readonly JoinHandler $handler ) {}

	/**
	 * Hook in: nopriv = logged-out visitors, plain = logged-in users.
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Process and redirect.
	 */
	public function handle(): void {
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$show_id = isset( $_POST['show_id'] ) ? absint( $_POST['show_id'] ) : 0;
		$back    = $show_id ? (string) get_permalink( $show_id ) : home_url( '/' );

		if ( ! wp_verify_nonce( $nonce, WaitlistController::NONCE_ACTION ) ) {
			$this->redirect( $back, 'expired' );
		}

		$fields = array( 'show_id', 'variation_id', 'name', 'email', 'phone', 'qty', 'consent', 'website' );
		$input  = array();
		foreach ( $fields as $field ) {
			// Each value is validated and normalised by JoinRequest.
			$input[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}

		$result = $this->handler->handle( $input, WaitlistController::client_ip() );
		$this->redirect( $back, $result['code'] );
	}

	/**
	 * Redirect to the show's ticket panel with a result code.
	 *
	 * @param string $url  Show URL.
	 * @param string $code Result code.
	 * @return never
	 */
	private function redirect( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( 'waitlist', rawurlencode( $code ), $url ) . '#tickets', 303 );
		exit;
	}
}
