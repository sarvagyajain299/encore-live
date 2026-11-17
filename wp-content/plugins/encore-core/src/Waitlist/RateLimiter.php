<?php
/**
 * Per-IP rate limit for waitlist sign-ups.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Support\KeyValueStore;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window counter: at most $limit attempts per $window seconds per IP.
 *
 * Privacy: the raw IP is never stored. The key is an HMAC of the IP, so the
 * transient table holds no personal data and keys can't be reversed into
 * addresses without the server secret.
 */
final class RateLimiter {

	/**
	 * Create.
	 *
	 * @param KeyValueStore $store  Storage (transients in production, memory in tests).
	 * @param string        $secret Server secret for hashing IPs.
	 * @param int           $limit  Attempts allowed per window.
	 * @param int           $window Window length in seconds.
	 */
	public function __construct(
		private readonly KeyValueStore $store,
		private readonly string $secret,
		private readonly int $limit = 5,
		private readonly int $window = 600
	) {}

	/**
	 * Record an attempt and say whether it is allowed.
	 *
	 * @param string $ip  Client IP.
	 * @param int    $now Current Unix time (injected for tests).
	 */
	public function attempt( string $ip, int $now ): bool {
		$key   = $this->key( $ip );
		$state = $this->store->get( $key );

		// New window if none exists or the current one has ended.
		if ( ! is_array( $state ) || $now >= (int) $state['reset_at'] ) {
			$state = array(
				'count'    => 0,
				'reset_at' => $now + $this->window,
			);
		}

		if ( $state['count'] >= $this->limit ) {
			return false;
		}

		++$state['count'];
		$this->store->set( $key, $state, max( 1, (int) $state['reset_at'] - $now ) );
		return true;
	}

	/**
	 * Storage key for an IP: short (transient names max 172 chars) and anonymised.
	 *
	 * @param string $ip Client IP.
	 */
	public function key( string $ip ): string {
		return 'encore_wl_rl_' . substr( hash_hmac( 'sha256', $ip, $this->secret ), 0, 32 );
	}
}
