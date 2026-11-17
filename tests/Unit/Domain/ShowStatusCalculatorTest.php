<?php
/**
 * Tests for the show status rules.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use EncoreCore\Domain\ShowStatus;
use EncoreCore\Domain\ShowStatusCalculator;
use EncoreCore\Tests\Unit\TestCase;

final class ShowStatusCalculatorTest extends TestCase {

	private ShowStatusCalculator $calculator;
	private DateTimeImmutable $now;
	private DateTimeImmutable $tomorrow;
	private DateTimeImmutable $yesterday;

	protected function setUp(): void {
		parent::setUp();
		$this->calculator = new ShowStatusCalculator();
		$tz               = new DateTimeZone( 'Asia/Dubai' );
		$this->now        = new DateTimeImmutable( '2026-12-01 12:00:00', $tz );
		$this->tomorrow   = $this->now->modify( '+1 day' );
		$this->yesterday  = $this->now->modify( '-1 day' );
	}

	public function test_upcoming_show_with_stock_is_on_sale(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::OnSale, $this->tomorrow, array( 10, 5, 0 ) ) );
	}

	public function test_all_tiers_at_zero_is_sold_out(): void {
		$this->assertSame( ShowStatus::SoldOut, $this->calc( ShowStatus::OnSale, $this->tomorrow, array( 0, 0, 0 ) ) );
	}

	public function test_negative_stock_counts_as_sold_out(): void {
		// Backorders can push stock below zero.
		$this->assertSame( ShowStatus::SoldOut, $this->calc( ShowStatus::OnSale, $this->tomorrow, array( 0, -2 ) ) );
	}

	public function test_restock_moves_sold_out_back_on_sale(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::SoldOut, $this->tomorrow, array( 0, 2, 0 ) ) );
	}

	public function test_unmanaged_tier_means_never_sold_out(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::OnSale, $this->tomorrow, array( 0, null ) ) );
	}

	public function test_show_without_tickets_is_not_sold_out(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::OnSale, $this->tomorrow, array() ) );
	}

	public function test_ended_show_is_archived_even_if_sold_out(): void {
		$this->assertSame( ShowStatus::Archived, $this->calc( ShowStatus::SoldOut, $this->yesterday, array( 0, 0 ) ) );
	}

	public function test_show_ending_exactly_now_is_not_archived_yet(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::OnSale, $this->now, array( 5 ) ) );
	}

	public function test_cancelled_always_wins(): void {
		$this->assertSame( ShowStatus::Cancelled, $this->calc( ShowStatus::Cancelled, $this->yesterday, array( 5 ) ) );
		$this->assertSame( ShowStatus::Cancelled, $this->calc( ShowStatus::Cancelled, $this->tomorrow, array( 0 ) ) );
	}

	public function test_redated_archived_show_comes_back_on_sale(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::Archived, $this->tomorrow, array( 5 ) ) );
	}

	public function test_unknown_end_never_archives(): void {
		$this->assertSame( ShowStatus::OnSale, $this->calc( ShowStatus::OnSale, null, array( 5 ) ) );
	}

	/**
	 * Shorthand.
	 *
	 * @param ShowStatus             $current Current status.
	 * @param DateTimeImmutable|null $ends    End time.
	 * @param array<int|null>        $stock   Tier stock.
	 */
	private function calc( ShowStatus $current, ?DateTimeImmutable $ends, array $stock ): ShowStatus {
		return $this->calculator->calculate( $current, $ends, $this->now, $stock );
	}
}
