<?php
/**
 * Contract for every service the plugin boots.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * A service wires itself into WordPress by adding hooks in register().
 *
 * Constructors must stay side-effect free (no add_action calls), so a service
 * can be created in a unit test without WordPress running.
 */
interface Service {

	/**
	 * Attach this service's actions and filters.
	 */
	public function register(): void;
}
