<?php
/**
 * Tests for the meta field sanitizers.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Support;

use Brain\Monkey\Functions;
use EncoreCore\Support\Sanitizer;
use EncoreCore\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SanitizerTest extends TestCase {

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function datetime_cases(): array {
		return array(
			'html datetime-local'      => array( '2026-12-01T20:30', '2026-12-01 20:30:00' ),
			'with seconds'             => array( '2026-12-01 20:30:15', '2026-12-01 20:30:15' ),
			'surrounding whitespace'   => array( '  2026-12-01 20:30 ', '2026-12-01 20:30:00' ),
			'impossible date rejected' => array( '2026-02-31 20:00', '' ),
			'hour 24 rejected'         => array( '2026-12-01 24:00', '' ),
			'date only rejected'       => array( '2026-12-01', '' ),
			'garbage rejected'         => array( 'next friday', '' ),
			'non-string rejected'      => array( array( '2026-12-01' ), '' ),
		);
	}

	#[DataProvider( 'datetime_cases' )]
	public function test_datetime( mixed $input, string $expected ): void {
		$this->assertSame( $expected, Sanitizer::datetime( $input ) );
	}

	public function test_time_accepts_24h_and_rejects_invalid(): void {
		$this->assertSame( '19:00', Sanitizer::time( '19:00' ) );
		$this->assertSame( '', Sanitizer::time( '7pm' ) );
		$this->assertSame( '', Sanitizer::time( '25:00' ) );
		$this->assertSame( '', Sanitizer::time( 1900 ) );
	}

	public function test_id_only_accepts_positive_whole_numbers(): void {
		$this->assertSame( 42, Sanitizer::id( '42' ) );
		$this->assertSame( 42, Sanitizer::id( 42 ) );
		$this->assertSame( 0, Sanitizer::id( -5 ) );
		$this->assertSame( 0, Sanitizer::id( '4.2' ) );
		$this->assertSame( 0, Sanitizer::id( '42abc' ) );
		$this->assertSame( 0, Sanitizer::id( null ) );
	}

	public function test_id_list_drops_invalid_and_duplicates_and_keeps_order(): void {
		$this->assertSame( array( 7, 3, 9 ), Sanitizer::id_list( array( '7', '3', 'x', '7', '0', 9 ) ) );
		$this->assertSame( array( 5, 6 ), Sanitizer::id_list( '5,6' ) );
		$this->assertSame( array(), Sanitizer::id_list( 12 ) );
	}

	public function test_status_falls_back_to_on_sale(): void {
		$this->assertSame( 'sold_out', Sanitizer::status( 'sold_out' ) );
		$this->assertSame( 'on_sale', Sanitizer::status( 'SOLD_OUT' ) );
		$this->assertSame( 'on_sale', Sanitizer::status( null ) );
	}

	public function test_age_only_allows_known_restrictions(): void {
		$this->assertSame( 18, Sanitizer::age( '18' ) );
		$this->assertSame( 0, Sanitizer::age( '17' ) );
	}

	public function test_capacity_is_capped(): void {
		$this->assertSame( 250, Sanitizer::capacity( '250' ) );
		$this->assertSame( 100000, Sanitizer::capacity( '999999' ) );
	}

	public function test_latitude_range_and_rounding(): void {
		$this->assertSame( 25.137201, Sanitizer::latitude( '25.1372012' ) );
		$this->assertSame( -90.0, Sanitizer::latitude( '-90' ) );
		$this->assertSame( 0.0, Sanitizer::latitude( '90.5' ) );
		$this->assertSame( 0.0, Sanitizer::latitude( 'north' ) );
	}

	// TODO(sarvagya): add test_longitude_range() covering 180, -180, 180.0001 and '55.2295'.
	//   Hint: copy test_latitude_range_and_rounding(); the limit for longitude is 180.
	//   Run it with: .\scripts\encore.ps1 test --filter longitude

	public function test_url_allows_only_http_and_https(): void {
		// Fake esc_url_raw(): we test OUR rule (which protocols we pass), not WordPress's escaping.
		Functions\when( 'esc_url_raw' )->alias(
			static fn( string $url, array $protocols ): string => in_array( (string) parse_url( $url, PHP_URL_SCHEME ), $protocols, true ) ? $url : ''
		);

		$this->assertSame( 'https://example.com', Sanitizer::url( ' https://example.com ' ) );
		$this->assertSame( '', Sanitizer::url( 'javascript:alert(1)' ) );
		$this->assertSame( '', Sanitizer::url( '' ) );
		$this->assertSame( '', Sanitizer::url( array() ) );
	}

	public function test_social_links_keep_only_known_networks_with_valid_urls(): void {
		Functions\when( 'esc_url_raw' )->alias(
			static fn( string $url ): string => str_starts_with( $url, 'https://' ) ? $url : ''
		);

		$result = Sanitizer::social_links(
			array(
				'instagram' => 'https://example.com/ig',
				'myspace'   => 'https://example.com/old',
				'youtube'   => 'not a url',
				'website'   => '',
			)
		);

		$this->assertSame( array( 'instagram' => 'https://example.com/ig' ), $result );
		$this->assertSame( array(), Sanitizer::social_links( 'https://example.com' ) );
	}

	public function test_text_uses_wordpress_sanitizer_and_rejects_arrays(): void {
		Functions\expect( 'sanitize_text_field' )->once()->with( 'Hello' )->andReturn( 'Hello' );

		$this->assertSame( 'Hello', Sanitizer::text( 'Hello' ) );
		$this->assertSame( '', Sanitizer::text( array( 'Hello' ) ) );
	}
}
