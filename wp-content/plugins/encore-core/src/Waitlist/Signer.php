<?php
/**
 * Tokens and signatures for waitlist links.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * Three kinds of link go out by email:
 *
 *  1. Confirm (double opt-in): a random, single-use token. Only its HASH is
 *     stored, so a database leak can't be turned into confirm links. Once
 *     used, the hash is cleared.
 *  2. Claim: a signed, expiring URL (entry ID + expiry + HMAC). Nothing to
 *     store; tampering with the ID or expiry breaks the signature.
 *  3. Unsubscribe: signed with HMAC so nobody can unsubscribe other people
 *     by guessing IDs.
 *
 * HMAC-SHA256 with a server-side secret (derived from WordPress salts). All
 * comparisons use hash_equals(), which runs in constant time and so leaks
 * nothing through response timing.
 */
final class Signer {

	/**
	 * Create with a secret key.
	 *
	 * @param string $key Secret (e.g. wp_salt( 'auth' )). Never hard-coded.
	 */
	public function __construct( private readonly string $key ) {}

	/**
	 * A new random token: 32 bytes from the OS CSPRNG, hex-encoded (64 chars).
	 */
	public function new_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash a token for storage.
	 *
	 * @param string $token Plain token.
	 */
	public function hash_token( string $token ): string {
		return hash_hmac( 'sha256', 'token|' . $token, $this->key );
	}

	/**
	 * Does a presented token match the stored hash?
	 *
	 * @param string $token       Token from the link.
	 * @param string $stored_hash Hash from the database ('' = already used).
	 */
	public function verify_token( string $token, string $stored_hash ): bool {
		if ( '' === $stored_hash || '' === $token ) {
			return false;
		}
		return hash_equals( $stored_hash, $this->hash_token( $token ) );
	}

	/**
	 * Sign a payload such as "claim|42|1767225600".
	 *
	 * @param string $payload Data to sign.
	 */
	public function sign( string $payload ): string {
		return hash_hmac( 'sha256', $payload, $this->key );
	}

	/**
	 * Verify a signature.
	 *
	 * @param string $payload   Data that was signed.
	 * @param string $signature Signature from the link.
	 */
	public function verify( string $payload, string $signature ): bool {
		return '' !== $signature && hash_equals( $this->sign( $payload ), $signature );
	}
}
