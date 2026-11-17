<?php
/**
 * Minimal expiring key-value store.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Abstracts transients so logic that needs short-lived storage (rate
 * limiting) can be unit-tested with an in-memory implementation.
 */
interface KeyValueStore {

	/**
	 * Read a value (null when missing or expired).
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ): mixed;

	/**
	 * Write a value with a lifetime.
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param int    $seconds Time to live.
	 */
	public function set( string $key, mixed $value, int $seconds ): void;
}
