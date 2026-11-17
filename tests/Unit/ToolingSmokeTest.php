<?php
/**
 * Proves the test tooling works before any real code exists:
 * PHPUnit runs, PHP is 8.2+, and Brain Monkey can stub WordPress functions.
 *
 * @package EncoreCore\Tests
 */

namespace EncoreCore\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

final class ToolingSmokeTest extends TestCase {

	public function test_runs_on_php_82_or_newer(): void {
		$this->assertTrue( version_compare( PHP_VERSION, '8.2.0', '>=' ) );
	}

	public function test_brain_monkey_stubs_wordpress_escaping(): void {
		// esc_html() does not exist here (no WordPress loaded); Brain Monkey fakes it.
		Functions\stubEscapeFunctions();

		$this->assertSame( '&lt;b&gt;Encore&lt;/b&gt;', esc_html( '<b>Encore</b>' ) );
	}

	public function test_brain_monkey_asserts_hooks_are_added(): void {
		// Brain Monkey ships its own add_action(); expectAdded() records calls to it.
		Actions\expectAdded( 'init' )->once()->with( 'encore_boot' );

		add_action( 'init', 'encore_boot' );
	}
}
