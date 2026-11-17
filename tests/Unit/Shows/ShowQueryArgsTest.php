<?php
/**
 * Tests for the shared show query-args builder.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Shows;

use DateTimeImmutable;
use DateTimeZone;
use EncoreCore\Shows\ShowQueryArgs;
use EncoreCore\Tests\Unit\TestCase;

final class ShowQueryArgsTest extends TestCase {

	private ShowQueryArgs $builder;
	private DateTimeImmutable $now;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new ShowQueryArgs();
		$this->now     = new DateTimeImmutable( '2026-11-17 15:30:00', new DateTimeZone( 'Asia/Dubai' ) );
	}

	public function test_defaults_to_upcoming_shows_soonest_first(): void {
		$args = $this->builder->build( array(), $this->now );

		$this->assertSame( 'encore_show', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( 12, $args['posts_per_page'] );
		$this->assertSame( '_encore_start', $args['meta_key'] );
		$this->assertSame( 'meta_value', $args['orderby'] );
		$this->assertSame( 'ASC', $args['order'] );
		$this->assertContains(
			array(
				'key'     => '_encore_start',
				'value'   => '2026-11-17 15:30:00',
				'compare' => '>=',
			),
			$args['meta_query']
		);
		$this->assertArrayNotHasKey( 'tax_query', $args );
	}

	public function test_skips_count_query_unless_paginating(): void {
		$this->assertTrue( $this->builder->build( array(), $this->now )['no_found_rows'] );
		$this->assertFalse( $this->builder->build( array( 'paginate' => true ), $this->now )['no_found_rows'] );
	}

	public function test_past_shows_are_newest_first(): void {
		$args = $this->builder->build( array( 'when' => 'past' ), $this->now );

		$this->assertSame( 'DESC', $args['order'] );
		$this->assertSame( '<', $args['meta_query'][0]['compare'] );
	}

	public function test_all_has_no_time_clause(): void {
		$this->assertArrayNotHasKey( 'meta_query', $this->builder->build( array( 'when' => 'all' ), $this->now ) );
	}

	public function test_unknown_when_falls_back_to_upcoming(): void {
		$this->assertSame( 'ASC', $this->builder->build( array( 'when' => 'yesterday' ), $this->now )['order'] );
	}

	public function test_limit_is_clamped(): void {
		$this->assertSame( 50, $this->builder->build( array( 'limit' => 999 ), $this->now )['posts_per_page'] );
		$this->assertSame( 12, $this->builder->build( array( 'limit' => '0' ), $this->now )['posts_per_page'] );
		$this->assertSame( 12, $this->builder->build( array( 'limit' => 'all' ), $this->now )['posts_per_page'] );
		$this->assertSame( 6, $this->builder->build( array( 'limit' => '6' ), $this->now )['posts_per_page'] );
	}

	public function test_page_is_at_least_one(): void {
		$this->assertSame( 1, $this->builder->build( array( 'page' => -3 ), $this->now )['paged'] );
		$this->assertSame( 3, $this->builder->build( array( 'page' => '3' ), $this->now )['paged'] );
	}

	public function test_month_becomes_whole_month_range(): void {
		$args = $this->builder->build( array( 'month' => '2027-02' ), $this->now );

		$this->assertContains(
			array(
				'key'     => '_encore_start',
				'value'   => array( '2027-02-01 00:00:00', '2027-02-28 23:59:59' ),
				'compare' => 'BETWEEN',
			),
			$args['meta_query']
		);
	}

	public function test_invalid_month_is_ignored(): void {
		$args = $this->builder->build( array( 'month' => '2027-13' ), $this->now );
		$this->assertCount( 2, $args['meta_query'] ); // relation + upcoming clause only.
	}

	public function test_from_and_to_are_inclusive_whole_days(): void {
		$args    = $this->builder->build(
			array(
				'from' => '2026-12-01',
				'to'   => '2026-12-31',
				'when' => 'all',
			),
			$this->now
		);
		$clauses = array_slice( $args['meta_query'], 1 );

		$this->assertSame( '2026-12-01 00:00:00', $clauses[0]['value'] );
		$this->assertSame( '>=', $clauses[0]['compare'] );
		$this->assertSame( '2026-12-31 23:59:59', $clauses[1]['value'] );
		$this->assertSame( '<=', $clauses[1]['compare'] );
	}

	public function test_impossible_dates_are_ignored(): void {
		$args = $this->builder->build(
			array(
				'from' => '2026-02-30',
				'when' => 'all',
			),
			$this->now
		);
		$this->assertArrayNotHasKey( 'meta_query', $args );
	}

	public function test_artist_uses_exact_match_on_index_key(): void {
		$args = $this->builder->build(
			array(
				'artist' => '18',
				'when'   => 'all',
			),
			$this->now
		);

		$this->assertSame(
			array(
				'key'     => '_encore_artist',
				'value'   => '18',
				'compare' => '=',
			),
			$args['meta_query'][0]
		);
	}

	public function test_city_and_genre_become_tax_query_with_cleaned_slugs(): void {
		$args = $this->builder->build(
			array(
				'city'  => ' Abu-Dhabi ',
				'genre' => 'comedy<script>',
			),
			$this->now
		);

		$this->assertSame( 'AND', $args['tax_query']['relation'] );
		$this->assertSame( 'abu-dhabi', $args['tax_query'][0]['terms'] );
		$this->assertSame( 'encore_city', $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( 'comedyscript', $args['tax_query'][1]['terms'] );
	}

	public function test_non_string_filters_are_ignored(): void {
		$args = $this->builder->build(
			array(
				'city'  => array( 'dubai' ),
				'month' => 202701,
			),
			$this->now
		);

		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertCount( 2, $args['meta_query'] );
	}
}
