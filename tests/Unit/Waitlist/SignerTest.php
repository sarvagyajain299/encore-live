<?php
/**
 * Tests for token hashing and link signatures.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Waitlist;

use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Waitlist\Signer;

final class SignerTest extends TestCase {

	private Signer $signer;

	protected function setUp(): void {
		parent::setUp();
		$this->signer = new Signer( 'test-secret' );
	}

	public function test_tokens_are_random_64_hex_chars(): void {
		$a = $this->signer->new_token();
		$b = $this->signer->new_token();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $a );
		$this->assertNotSame( $a, $b );
	}

	public function test_stored_hash_is_not_the_token(): void {
		$token = $this->signer->new_token();
		$hash  = $this->signer->hash_token( $token );

		$this->assertNotSame( $token, $hash );
		$this->assertSame( 64, strlen( $hash ) );
	}

	public function test_verify_token_accepts_the_right_token_only(): void {
		$token = $this->signer->new_token();
		$hash  = $this->signer->hash_token( $token );

		$this->assertTrue( $this->signer->verify_token( $token, $hash ) );
		$this->assertFalse( $this->signer->verify_token( $this->signer->new_token(), $hash ) );
		$this->assertFalse( $this->signer->verify_token( '', $hash ) );
	}

	public function test_used_token_cannot_be_reused_once_hash_is_cleared(): void {
		$token = $this->signer->new_token();
		$this->assertFalse( $this->signer->verify_token( $token, '' ) );
	}

	public function test_hash_depends_on_server_secret(): void {
		$token = 'abc';
		$this->assertNotSame( $this->signer->hash_token( $token ), ( new Signer( 'other-secret' ) )->hash_token( $token ) );
	}

	public function test_signature_detects_tampering(): void {
		$sig = $this->signer->sign( 'claim|42|1767225600' );

		$this->assertTrue( $this->signer->verify( 'claim|42|1767225600', $sig ) );
		$this->assertFalse( $this->signer->verify( 'claim|43|1767225600', $sig ), 'changed entry id' );
		$this->assertFalse( $this->signer->verify( 'claim|42|1999999999', $sig ), 'extended expiry' );
		$this->assertFalse( $this->signer->verify( 'unsubscribe|42', $sig ), 'reused for another purpose' );
		$this->assertFalse( $this->signer->verify( 'claim|42|1767225600', '' ) );
	}
}
