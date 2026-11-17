<?php
/**
 * Tests for waitlist status transitions.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit\Waitlist;

use EncoreCore\Tests\Unit\TestCase;
use EncoreCore\Waitlist\Status;
use PHPUnit\Framework\Attributes\DataProvider;

final class StatusTest extends TestCase {

	/**
	 * @return array<string, array{Status, Status}>
	 */
	public static function valid(): array {
		return array(
			'confirm'                 => array( Status::PendingConfirmation, Status::Waiting ),
			'pending expires'         => array( Status::PendingConfirmation, Status::Expired ),
			'restock notifies'        => array( Status::Waiting, Status::Notified ),
			'buys without claim link' => array( Status::Waiting, Status::Converted ),
			'claim converts'          => array( Status::Notified, Status::Converted ),
			'claim window lapses'     => array( Status::Notified, Status::Expired ),
			'unsubscribe waiting'     => array( Status::Waiting, Status::Unsubscribed ),
			'rejoin after expiry'     => array( Status::Expired, Status::PendingConfirmation ),
			'rejoin after unsub'      => array( Status::Unsubscribed, Status::PendingConfirmation ),
		);
	}

	/**
	 * @return array<string, array{Status, Status}>
	 */
	public static function invalid(): array {
		return array(
			'notify unconfirmed'       => array( Status::PendingConfirmation, Status::Notified ),
			'convert unconfirmed'      => array( Status::PendingConfirmation, Status::Converted ),
			'converted is final'       => array( Status::Converted, Status::Waiting ),
			'converted cannot expire'  => array( Status::Converted, Status::Expired ),
			'notified back to waiting' => array( Status::Notified, Status::Waiting ),
			'expired to waiting skips opt-in' => array( Status::Expired, Status::Waiting ),
			'waiting cannot expire'    => array( Status::Waiting, Status::Expired ),
			'no self transition'       => array( Status::Waiting, Status::Waiting ),
		);
	}

	#[DataProvider( 'valid' )]
	public function test_allowed_transitions( Status $from, Status $to ): void {
		$this->assertTrue( $from->can_transition_to( $to ) );
	}

	#[DataProvider( 'invalid' )]
	public function test_rejected_transitions( Status $from, Status $to ): void {
		$this->assertFalse( $from->can_transition_to( $to ) );
	}

	public function test_active_statuses(): void {
		$this->assertTrue( Status::Waiting->is_active() );
		$this->assertTrue( Status::Notified->is_active() );
		$this->assertFalse( Status::Converted->is_active() );
		$this->assertFalse( Status::Unsubscribed->is_active() );
	}
}
