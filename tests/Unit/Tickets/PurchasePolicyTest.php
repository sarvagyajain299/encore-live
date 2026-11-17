<?php
/**
 * Tests for ticket purchase rules.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Tickets;

use DateTimeImmutable;
use DateTimeZone;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Tickets\PurchasePolicy;

final class PurchasePolicyTest extends TestCase {

	private PurchasePolicy $policy;
	private DateTimeImmutable $now;
	private DateTimeImmutable $next_week;

	protected function setUp(): void {
		parent::setUp();
		$this->policy    = new PurchasePolicy();
		$this->now       = new DateTimeImmutable( '2026-11-17 12:00:00', new DateTimeZone( 'Asia/Dubai' ) );
		$this->next_week = $this->now->modify( '+7 days' );
	}

	public function test_allows_normal_purchase(): void {
		$this->assertSame( PurchasePolicy::OK, $this->check( ShowStatus::OnSale, $this->next_week, 2, 0 ) );
	}

	public function test_allows_exactly_the_limit_across_cart(): void {
		$this->assertSame( PurchasePolicy::OK, $this->check( ShowStatus::OnSale, $this->next_week, 2, 4 ) );
	}

	public function test_blocks_going_over_the_limit_across_cart(): void {
		$this->assertSame( PurchasePolicy::OVER_LIMIT, $this->check( ShowStatus::OnSale, $this->next_week, 3, 4 ) );
	}

	public function test_blocks_single_add_over_limit(): void {
		$this->assertSame( PurchasePolicy::OVER_LIMIT, $this->check( ShowStatus::OnSale, $this->next_week, 7, 0 ) );
	}

	public function test_rejects_zero_or_negative_quantity(): void {
		$this->assertSame( PurchasePolicy::INVALID_QTY, $this->check( ShowStatus::OnSale, $this->next_week, 0, 0 ) );
		$this->assertSame( PurchasePolicy::INVALID_QTY, $this->check( ShowStatus::OnSale, $this->next_week, -1, 0 ) );
	}

	public function test_blocks_cancelled_show(): void {
		$this->assertSame( PurchasePolicy::CANCELLED, $this->check( ShowStatus::Cancelled, $this->next_week, 1, 0 ) );
	}

	public function test_blocks_archived_show(): void {
		$this->assertSame( PurchasePolicy::ENDED, $this->check( ShowStatus::Archived, $this->next_week, 1, 0 ) );
	}

	public function test_blocks_show_that_started_even_if_status_not_yet_archived(): void {
		// The daily archive job may not have run yet; the start time is the real cut-off.
		$this->assertSame( PurchasePolicy::ENDED, $this->check( ShowStatus::OnSale, $this->now, 1, 0 ) );
		$this->assertSame( PurchasePolicy::ENDED, $this->check( ShowStatus::OnSale, $this->now->modify( '-1 hour' ), 1, 0 ) );
	}

	public function test_blocks_show_without_a_date(): void {
		$this->assertSame( PurchasePolicy::ENDED, $this->check( ShowStatus::OnSale, null, 1, 0 ) );
	}

	public function test_blocks_sold_out_show(): void {
		$this->assertSame( PurchasePolicy::SOLD_OUT, $this->check( ShowStatus::SoldOut, $this->next_week, 1, 0 ) );
	}

	public function test_cancelled_message_wins_over_limit(): void {
		$this->assertSame( PurchasePolicy::CANCELLED, $this->check( ShowStatus::Cancelled, $this->next_week, 10, 0 ) );
	}

	/**
	 * Shorthand with a max of 6.
	 *
	 * @param ShowStatus             $status  Status.
	 * @param DateTimeImmutable|null $start   Start.
	 * @param int                    $qty     Requested.
	 * @param int                    $in_cart Already in cart.
	 */
	private function check( ShowStatus $status, ?DateTimeImmutable $start, int $qty, int $in_cart ): string {
		return $this->policy->check( $status, $start, $this->now, $qty, $in_cart, 6 );
	}
}
