<?php
/**
 * Tests for settings sanitizing and defaults.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Support;

use Brain\Monkey\Functions;
use EncoreCore\Support\Settings;
use EncoreCore\Tests\Unit\TestCase;

final class SettingsTest extends TestCase {

	public function test_sanitize_keeps_valid_values(): void {
		$clean = Settings::sanitize(
			array(
				'max_tickets_per_order' => '8',
				'gtm_container_id'      => ' gtm-ab12cd3 ',
			)
		);

		$this->assertSame( 8, $clean['max_tickets_per_order'] );
		$this->assertSame( 'GTM-AB12CD3', $clean['gtm_container_id'] );
	}

	public function test_sanitize_falls_back_to_defaults_and_drops_unknown_keys(): void {
		$clean = Settings::sanitize(
			array(
				'max_tickets_per_order' => '50',
				'gtm_container_id'      => '<script>alert(1)</script>',
				'is_admin'              => true,
			)
		);

		$this->assertSame( Settings::DEFAULTS, $clean );
	}

	public function test_sanitize_handles_non_array_input(): void {
		$this->assertSame( Settings::DEFAULTS, Settings::sanitize( 'nonsense' ) );
	}

	public function test_get_uses_defaults_for_missing_option(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$this->assertSame( 6, Settings::max_tickets_per_order() );
	}

	public function test_get_reads_saved_value(): void {
		Functions\when( 'get_option' )->justReturn( array( 'max_tickets_per_order' => 4 ) );

		$this->assertSame( 4, Settings::max_tickets_per_order() );
		$this->assertSame( '', Settings::get( 'gtm_container_id' ) );
	}
}
