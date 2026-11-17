<?php
/**
 * Tests for shortcode/widget filter normalisation and cache keys.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Shows;

use Brain\Monkey\Functions;
use EncoreCore\Shows\ListFilters;
use EncoreCore\Tests\Unit\TestCase;

final class ListFiltersTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	public function test_normalises_editor_input(): void {
		$this->assertSame(
			array(
				'limit' => 4,
				'city'  => 'abu-dhabi',
				'genre' => 'live-music',
			),
			ListFilters::from_atts(
				array(
					'limit' => ' 4 ',
					'city'  => 'Abu Dhabi',
					'genre' => 'Live_Music',
				)
			)
		);
	}

	public function test_defaults_and_caps_limit(): void {
		$this->assertSame( array( 'limit' => 6 ), ListFilters::from_atts( array() ) );
		$this->assertSame( array( 'limit' => 6 ), ListFilters::from_atts( array( 'limit' => 'lots' ) ) );
		$this->assertSame( array( 'limit' => ListFilters::MAX_LISTING ), ListFilters::from_atts( array( 'limit' => '500' ) ) );
	}

	public function test_strips_unsafe_characters_from_slugs(): void {
		$this->assertSame( 'dubaiscript', ListFilters::from_atts( array( 'city' => 'dubai"><script>' ) )['city'] );
	}

	public function test_artist_id_is_kept_when_valid(): void {
		$this->assertSame( 18, ListFilters::from_atts( array( 'artist_id' => 18 ) )['artist'] );
		$this->assertArrayNotHasKey( 'artist', ListFilters::from_atts( array( 'artist_id' => 'x' ) ) );
	}

	public function test_cache_key_ignores_attribute_order(): void {
		$a = ListFilters::cache_key(
			array(
				'city'  => 'dubai',
				'genre' => 'comedy',
			),
			3
		);
		$b = ListFilters::cache_key(
			array(
				'genre' => 'comedy',
				'city'  => 'dubai',
			),
			3
		);
		$this->assertSame( $a, $b );
	}

	public function test_cache_key_changes_with_version_and_filters(): void {
		$base = ListFilters::cache_key( array( 'city' => 'dubai' ), 3 );

		$this->assertNotSame( $base, ListFilters::cache_key( array( 'city' => 'dubai' ), 4 ) );
		$this->assertNotSame( $base, ListFilters::cache_key( array( 'city' => 'sharjah' ), 3 ) );
		// Transient names are limited to 172 characters.
		$this->assertLessThanOrEqual( 172, strlen( $base ) );
	}
}
