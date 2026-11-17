<?php
/**
 * Plugin Name:       Encore Core
 * Description:       Business logic for Encore Live: shows, artists, venues, ticketing and waitlists.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Sarvagya Jain
 * License:           GPL-2.0-or-later
 * Text Domain:       encore-core
 *
 * This file only bootstraps. Everything else lives in namespaced classes under
 * src/, loaded on demand by Composer's PSR-4 autoloader.
 *
 * @package EncoreCore
 */

defined( 'ABSPATH' ) || exit;

define( 'ENCORE_CORE_VERSION', '0.2.0' );
define( 'ENCORE_CORE_FILE', __FILE__ );
define( 'ENCORE_CORE_DIR', __DIR__ );

$encore_core_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $encore_core_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Encore Core is missing its autoloader. Run the install command (it runs composer install for the plugin).', 'encore-core' )
			);
		}
	);
	return;
}

require_once $encore_core_autoload;

register_activation_hook( __FILE__, array( \EncoreCore\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \EncoreCore\Lifecycle::class, 'deactivate' ) );

// Boot after every plugin has loaded, so WooCommerce classes are available.
add_action(
	'plugins_loaded',
	static function (): void {
		\EncoreCore\Plugin::instance()->boot();
	}
);
