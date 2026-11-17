<?php
/**
 * POST /wp-json/encore/v1/waitlist
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Http;

use EncoreCore\Contracts\Service;
use EncoreCore\Waitlist\JoinHandler;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * JSON entry point used by waitlist.js.
 *
 * Security layers:
 *  - permission_callback verifies our nonce (action 'encore_waitlist'),
 *    which proves the request came from a form rendered by this site.
 *  - args schema types and bounds every field before the callback runs.
 *  - JoinHandler adds the honeypot, per-IP rate limit and full validation.
 */
final class WaitlistController implements Service {

	public const NONCE_ACTION = 'encore_waitlist';

	/**
	 * Create.
	 *
	 * @param JoinHandler $handler Shared pipeline.
	 */
	public function __construct( private readonly JoinHandler $handler ) {}

	/**
	 * Hook route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register the route.
	 */
	public function routes(): void {
		$string = static fn( int $max ): array => array(
			'type'      => 'string',
			'maxLength' => $max,
			'default'   => '',
		);

		register_rest_route(
			'encore/v1',
			'/waitlist',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'join' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'show_id'      => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'variation_id' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'name'         => $string( 100 ),
					'email'        => $string( 190 ),
					'phone'        => $string( 30 ),
					'qty'          => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 4,
						'default' => 1,
					),
					'consent'      => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'website'      => $string( 200 ), // Honeypot.
					'nonce'        => $string( 64 ),
				),
			)
		);
	}

	/**
	 * Permission: a valid form nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function check_nonce( WP_REST_Request $request ): bool|WP_Error {
		if ( wp_verify_nonce( (string) $request['nonce'], self::NONCE_ACTION ) ) {
			return true;
		}
		return new WP_Error( 'encore_bad_nonce', __( 'Your session expired. Please reload the page and try again.', 'encore-core' ), array( 'status' => 403 ) );
	}

	/**
	 * Join.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function join( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->handler->handle( $request->get_params(), self::client_ip() );
		$body   = $result;
		unset( $body['status'] );
		if ( isset( $result['position'] ) ) {
			$body['message'] = JoinHandler::message( $result['code'], $result['position'] );
		}
		return new WP_REST_Response( $body, $result['status'] );
	}

	/**
	 * Client IP. REMOTE_ADDR only: X-Forwarded-For is client-controlled. Behind
	 * Cloudflare, the web server must restore the real IP (mod_remoteip /
	 * nginx real_ip with Cloudflare's ranges) so REMOTE_ADDR is trustworthy.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
