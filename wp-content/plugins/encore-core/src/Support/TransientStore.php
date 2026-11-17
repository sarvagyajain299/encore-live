<?php
/**
 * KeyValueStore backed by transients.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Transients use the object cache (Redis/Memcached) when present, otherwise
 * the options table, so this works on any host.
 */
final class TransientStore implements KeyValueStore {

	/**
	 * Read.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ): mixed {
		$value = get_transient( $key );
		return false === $value ? null : $value;
	}

	/**
	 * Write.
	 *
	 * @param string $key     Key.
	 * @param mixed  $value   Value.
	 * @param int    $seconds TTL.
	 */
	public function set( string $key, mixed $value, int $seconds ): void {
		set_transient( $key, $value, $seconds );
	}
}
