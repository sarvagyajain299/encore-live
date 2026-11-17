<?php
/**
 * Base class for unit tests: starts and stops Brain Monkey around each test.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	// Verifies Mockery expectations (e.g. "add_action called once") after each test.
	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
