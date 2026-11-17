<?php
/**
 * Builds and verifies the links in waitlist emails.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps Signer with a key derived from WordPress's secret salts (wp-config),
 * so links can't be forged without server access and rotate if salts rotate.
 */
final class Links {

	public const QUERY_ACTION = 'encore_wl';

	/**
	 * Create.
	 *
	 * @param Signer $signer Signer with the site secret.
	 */
	public function __construct( private readonly Signer $signer ) {}

	/**
	 * Factory using the site's auth salt.
	 */
	public static function create(): self {
		return new self( new Signer( wp_salt( 'auth' ) . '|encore-waitlist' ) );
	}

	/**
	 * The signer (for token generation and verification).
	 */
	public function signer(): Signer {
		return $this->signer;
	}

	/**
	 * Double opt-in confirmation link.
	 *
	 * @param int    $id    Entry ID.
	 * @param string $token Plain single-use token.
	 */
	public function confirm_url( int $id, string $token ): string {
		return $this->url(
			array(
				self::QUERY_ACTION => 'confirm',
				'id'               => $id,
				'token'            => $token,
			)
		);
	}

	/**
	 * Signed, expiring claim link that pre-fills the cart.
	 *
	 * @param int $id      Entry ID.
	 * @param int $expires Unix time the claim window closes.
	 */
	public function claim_url( int $id, int $expires ): string {
		return $this->url(
			array(
				self::QUERY_ACTION => 'claim',
				'id'               => $id,
				'exp'              => $expires,
				'sig'              => $this->signer->sign( "claim|{$id}|{$expires}" ),
			)
		);
	}

	/**
	 * One-click unsubscribe link.
	 *
	 * @param int $id Entry ID.
	 */
	public function unsubscribe_url( int $id ): string {
		return $this->url(
			array(
				self::QUERY_ACTION => 'unsubscribe',
				'id'               => $id,
				'sig'              => $this->signer->sign( "unsubscribe|{$id}" ),
			)
		);
	}

	/**
	 * Verify a claim link.
	 *
	 * @param int    $id      Entry ID.
	 * @param int    $expires Expiry.
	 * @param string $sig     Signature.
	 */
	public function verify_claim( int $id, int $expires, string $sig ): bool {
		return $this->signer->verify( "claim|{$id}|{$expires}", $sig );
	}

	/**
	 * Verify an unsubscribe link.
	 *
	 * @param int    $id  Entry ID.
	 * @param string $sig Signature.
	 */
	public function verify_unsubscribe( int $id, string $sig ): bool {
		return $this->signer->verify( "unsubscribe|{$id}", $sig );
	}

	/**
	 * Build a home URL with query args.
	 *
	 * @param array<string, mixed> $args Args.
	 */
	private function url( array $args ): string {
		return add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $args ) ), home_url( '/' ) );
	}
}
