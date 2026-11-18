<?php
/**
 * Site hardening that belongs in code (the rest lives in server config).
 *
 * @package EncoreCore
 */

namespace EncoreCore\Security;

use EncoreCore\Contracts\Service;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Small, boring, layered defences. None of these replaces patching and
 * strong passwords; together they cut down automated attacks and information leaks.
 *
 *  - XML-RPC off: a favourite target for password brute force (system.multicall
 *    tries hundreds of passwords per request) and pingback abuse. Nothing here uses it.
 *  - User enumeration blocked: ?author=1 redirects and /wp/v2/users reveal login
 *    slugs, which is half of a brute-force attack. Blocked for visitors only;
 *    logged-in editors still get the endpoints the block editor needs.
 *  - Security headers on every front-end response (see docs/SECURITY.md).
 *  - WordPress version removed from <head> and feeds (cuts version fingerprinting).
 *  - File editing in wp-admin disabled via DISALLOW_FILE_EDIT (in wp-config, via .env).
 */
final class Hardening implements Service {

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );

		add_action( 'template_redirect', array( $this, 'block_author_scan' ), 0 );
		add_filter( 'rest_pre_dispatch', array( $this, 'block_rest_users' ), 10, 3 );

		add_action( 'send_headers', array( $this, 'security_headers' ) );
		add_filter( 'the_generator', '__return_empty_string' );
		remove_action( 'wp_head', 'wp_generator' );
	}

	/**
	 * Don't advertise the XML-RPC pingback endpoint.
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public function remove_pingback_header( $headers ): array {
		unset( $headers['X-Pingback'] );
		return (array) $headers;
	}

	/**
	 * /?author=N redirects to /author/<login-slug>/: stop that for visitors.
	 */
	public function block_author_scan(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of a public query var.
		if ( ! is_user_logged_in() && ( isset( $_GET['author'] ) || is_author() ) ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/**
	 * /wp-json/wp/v2/users lists every author's slug: require a login.
	 *
	 * @param mixed           $result  Pre-dispatch result.
	 * @param mixed           $server  REST server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function block_rest_users( $result, $server, $request ) {
		unset( $server );
		if ( ! is_user_logged_in() && $request instanceof WP_REST_Request && str_starts_with( $request->get_route(), '/wp/v2/users' ) ) {
			return new WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'encore-core' ), array( 'status' => 401 ) );
		}
		return $result;
	}

	/**
	 * Baseline security headers for front-end responses.
	 *
	 * HSTS is NOT sent here: it must only be set over HTTPS, so it lives in the
	 * production Nginx/Cloudflare config. A Content-Security-Policy is documented
	 * as a next step (GTM + inline scripts need nonces or hashes to do it properly).
	 */
	public function security_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)' );
	}
}
