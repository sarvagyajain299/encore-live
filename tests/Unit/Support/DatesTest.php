<?php
/**
 * Tests for date parsing.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Support;

use DateTimeZone;
use EncoreCore\Support\Dates;
use EncoreCore\Tests\Unit\TestCase;

final class DatesTest extends TestCase {

	public function test_parses_stored_value_in_given_timezone(): void {
		$date = Dates::from_meta( '2026-12-01 20:00:00', new DateTimeZone( 'Asia/Dubai' ) );

		$this->assertNotNull( $date );
		// ISO 8601 with Dubai's +04:00 offset, exactly what Event schema needs.
		$this->assertSame( '2026-12-01T20:00:00+04:00', $date->format( 'c' ) );
	}

	public function test_empty_or_invalid_returns_null(): void {
		$tz = new DateTimeZone( 'Asia/Dubai' );
		$this->assertNull( Dates::from_meta( '', $tz ) );
		$this->assertNull( Dates::from_meta( '01/12/2026', $tz ) );
	}
}
