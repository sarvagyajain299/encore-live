<?php
/**
 * Tests for the per-IP rate limiter.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Waitlist;

use EncoreCore\Support\KeyValueStore;
use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Waitlist\RateLimiter;

final class RateLimiterTest extends TestCase {

	private KeyValueStore $store;

	protected function setUp(): void {
		parent::setUp();
		// In-memory store with real expiry semantics driven by $this->now.
		$this->store = new class() implements KeyValueStore {
			/** @var array<string, array{value:mixed, expires:int}> */
			public array $data = array();
			public int $now    = 0;

			public function get( string $key ): mixed {
				$row = $this->data[ $key ] ?? null;
				return ( $row && $row['expires'] > $this->now ) ? $row['value'] : null;
			}

			public function set( string $key, mixed $value, int $seconds ): void {
				$this->data[ $key ] = array(
					'value'   => $value,
					'expires' => $this->now + $seconds,
				);
			}
		};
	}

	public function test_allows_up_to_the_limit_then_blocks(): void {
		$limiter = new RateLimiter( $this->store, 'secret', 3, 600 );

		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1000 ) );
		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1001 ) );
		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1002 ) );
		$this->assertFalse( $limiter->attempt( '10.0.0.1', 1003 ) );
	}

	public function test_limits_are_per_ip(): void {
		$limiter = new RateLimiter( $this->store, 'secret', 1, 600 );

		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1000 ) );
		$this->assertTrue( $limiter->attempt( '10.0.0.2', 1000 ) );
		$this->assertFalse( $limiter->attempt( '10.0.0.1', 1000 ) );
	}

	public function test_window_resets_after_it_ends(): void {
		$limiter = new RateLimiter( $this->store, 'secret', 1, 600 );

		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1000 ) );
		$this->assertFalse( $limiter->attempt( '10.0.0.1', 1599 ) );
		$this->store->now = 1600;
		$this->assertTrue( $limiter->attempt( '10.0.0.1', 1600 ) );
	}

	public function test_raw_ip_is_never_stored(): void {
		$limiter = new RateLimiter( $this->store, 'secret' );
		$limiter->attempt( '203.0.113.7', 1000 );

		$key = array_key_first( $this->store->data );
		$this->assertStringNotContainsString( '203.0.113.7', $key );
		$this->assertStringNotContainsString( '203.0.113.7', (string) wp_json_encode_safe( $this->store->data ) );
		$this->assertLessThanOrEqual( 172, strlen( $key ) );
	}
}

/**
 * Local JSON helper (no WordPress in unit tests).
 *
 * @param mixed $data Data.
 */
function wp_json_encode_safe( mixed $data ): string|false {
	return json_encode( $data );
}
