<?php
/**
 * Tests for UAE phone normalisation.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Support;

use EncoreCore\Support\PhoneNumber;
use EncoreCore\Tests\Unit\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PhoneNumberTest extends TestCase {

	/**
	 * @return array<string, array{mixed, string|null}>
	 */
	public static function cases(): array {
		return array(
			'local mobile with spaces'  => array( '050 123 4567', '+971501234567' ),
			'local mobile compact'      => array( '0551234567', '+971551234567' ),
			'international plus'        => array( '+971 52 123 4567', '+971521234567' ),
			'international 00'          => array( '00971 56 123 4567', '+971561234567' ),
			'international no plus'     => array( '971581234567', '+971581234567' ),
			'dashes and brackets'       => array( '(050) 123-4567', '+971501234567' ),
			'dubai landline'            => array( '04 123 4567', '+97141234567' ),
			'abu dhabi landline intl'   => array( '+971 2 123 4567', '+97121234567' ),
			'unknown mobile network 51' => array( '051 123 4567', null ),
			'too short'                 => array( '050 123 456', null ),
			'too long'                  => array( '050 123 45678', null ),
			'other country'             => array( '+44 7700 900123', null ),
			'no prefix at all'          => array( '501234567', null ),
			'letters'                   => array( '050 CALL NOW', null ),
			'empty'                     => array( '', null ),
			'not a string'              => array( 501234567, null ),
		);
	}

	#[DataProvider( 'cases' )]
	public function test_normalize_uae( mixed $input, ?string $expected ): void {
		$this->assertSame( $expected, PhoneNumber::normalize_uae( $input ) );
	}
}
