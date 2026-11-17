<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Unit tests do NOT load WordPress. WordPress functions are replaced with
 * Brain Monkey stubs per test, which keeps tests fast and isolated.
 *
 * @package EncoreCore\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Plugin code guards itself with `defined( 'ABSPATH' ) || exit;`.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
