<?php
/**
 * Handles confirm / claim / unsubscribe links from emails.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Http;

use EncoreCore\Contracts\Service;
use EncoreCore\Waitlist\JoinHandler;
use EncoreCore\Waitlist\Links;
use EncoreCore\Waitlist\WaitlistRepository;
use EncoreCore\Waitlist\WaitlistService;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on template_redirect (front end, before any output), performs the
 * action, then redirects so the token never stays in the address bar or in
 * browser history after use.
 *
 * Note on nonces: these links have no WordPress nonce. A nonce expires
 * within a day and is tied to a session; email links must work days later
 * from any device. Their protection is the single-use token or HMAC signature.
 */
final class LinkHandler implements Service {

	/**
	 * Create.
	 *
	 * @param WaitlistService    $service Service.
	 * @param WaitlistRepository $repo    Data (to find the show for redirects).
	 */
	public function __construct(
		private readonly WaitlistService $service,
		private readonly WaitlistRepository $repo
	) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
	}

	/**
	 * Dispatch.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- signed/token links, see class doc.
		$action = isset( $_GET[ Links::QUERY_ACTION ] ) ? sanitize_key( wp_unslash( $_GET[ Links::QUERY_ACTION ] ) ) : '';
		if ( '' === $action ) {
			return;
		}
		$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$sig   = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';
		$exp   = isset( $_GET['exp'] ) ? absint( $_GET['exp'] ) : 0;
		// phpcs:enable

		$entry    = $this->repo->find( $id );
		$show_url = $entry ? (string) get_permalink( (int) $entry['show_id'] ) : home_url( '/' );

		switch ( $action ) {
			case 'confirm':
				$code = $this->service->confirm( $id, $token );
				$this->back( $show_url, 'invalid' === $code ? 'bad_link' : $code );
				break;

			case 'unsubscribe':
				$code = $this->service->unsubscribe( $id, $sig );
				$this->back( $show_url, 'invalid' === $code ? 'bad_link' : $code );
				break;

			case 'claim':
				$code = $this->service->claim( $id, $exp, $sig );
				if ( 'added' === $code ) {
					wc_add_notice( __( 'Your waitlist tickets are in your cart. Check out soon: they are not held for you.', 'encore-core' ) );
					wp_safe_redirect( wc_get_cart_url() );
					exit;
				}
				$this->back( $show_url, 'invalid' === $code ? 'bad_link' : ( 'unavailable' === $code ? 'claim_unavailable' : $code ) );
				break;
		}
	}

	/**
	 * Redirect to the show with a result code.
	 *
	 * @param string $url  Show URL.
	 * @param string $code Code.
	 * @return never
	 */
	private function back( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( 'waitlist', rawurlencode( $code ), $url ) . '#tickets' );
		exit;
	}

	/**
	 * Message for codes only links produce (others come from JoinHandler).
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		return 'claim_unavailable' === $code
			? __( 'Sorry, those tickets have just been bought by someone else. You can join the waitlist again below.', 'encore-core' )
			: JoinHandler::message( $code );
	}
}
