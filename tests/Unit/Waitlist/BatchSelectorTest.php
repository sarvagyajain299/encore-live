<?php
/**
 * Tests for FIFO batch selection.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Waitlist;

use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Waitlist\BatchSelector;

final class BatchSelectorTest extends TestCase {

	private BatchSelector $selector;

	protected function setUp(): void {
		parent::setUp();
		$this->selector = new BatchSelector();
	}

	/**
	 * Queue helper: [[id, qty], ...].
	 *
	 * @param array<int, array{0:int, 1:int}> $rows Rows.
	 * @return array<int, array{id:int, qty:int}>
	 */
	private function queue( array $rows ): array {
		return array_map(
			static fn( array $r ): array => array(
				'id'  => $r[0],
				'qty' => $r[1],
			),
			$rows
		);
	}

	public function test_takes_entries_in_fifo_order_until_stock_runs_out(): void {
		$queue = $this->queue( array( array( 1, 2 ), array( 2, 2 ), array( 3, 2 ) ) );
		$this->assertSame( array( 1, 2 ), $this->selector->select( $queue, 4 ) );
	}

	public function test_large_request_that_does_not_fit_is_skipped_and_smaller_ones_behind_it_are_notified(): void {
		// The case from the brief: A wants 4, only 3 came back.
		$queue = $this->queue( array( array( 10, 4 ), array( 11, 2 ), array( 12, 1 ), array( 13, 1 ) ) );
		$this->assertSame( array( 11, 12 ), $this->selector->select( $queue, 3 ) );
	}

	public function test_skipped_entry_is_first_in_line_next_time(): void {
		// Next restock of 4: entry 10 (still first in the waiting queue) fits now.
		$queue = $this->queue( array( array( 10, 4 ), array( 13, 1 ) ) );
		$this->assertSame( array( 10 ), $this->selector->select( $queue, 4 ) );
	}

	public function test_exact_fit_uses_all_tickets(): void {
		$queue = $this->queue( array( array( 1, 3 ), array( 2, 1 ) ) );
		$this->assertSame( array( 1, 2 ), $this->selector->select( $queue, 4 ) );
	}

	public function test_nothing_available_selects_nobody(): void {
		$queue = $this->queue( array( array( 1, 1 ) ) );
		$this->assertSame( array(), $this->selector->select( $queue, 0 ) );
		$this->assertSame( array(), $this->selector->select( $queue, -2 ) );
	}

	public function test_empty_queue(): void {
		$this->assertSame( array(), $this->selector->select( array(), 10 ) );
	}

	public function test_never_offers_more_tickets_than_available(): void {
		$queue    = $this->queue( array( array( 1, 2 ), array( 2, 3 ), array( 3, 1 ), array( 4, 4 ), array( 5, 1 ) ) );
		$selected = $this->selector->select( $queue, 5 );
		$offered  = array_sum( array_map( static fn( array $e ): int => in_array( $e['id'], $selected, true ) ? $e['qty'] : 0, $queue ) );

		$this->assertSame( array( 1, 2 ), $selected );
		$this->assertLessThanOrEqual( 5, $offered );
	}

	public function test_invalid_quantities_are_ignored(): void {
		$queue = $this->queue( array( array( 1, 0 ), array( 2, -1 ), array( 3, 1 ) ) );
		$this->assertSame( array( 3 ), $this->selector->select( $queue, 2 ) );
	}
}
