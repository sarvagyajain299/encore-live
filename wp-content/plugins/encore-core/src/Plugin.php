<?php
/**
 * Plugin bootstrap and service registration.
 *
 * @package EncoreCore
 */

namespace EncoreCore;

use EncoreCore\Admin\MetaBox;
use EncoreCore\Admin\ShowListColumns;
use EncoreCore\Cli\CliService;
use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Contracts\Service;
use EncoreCore\Meta\MetaRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that decides which services run.
 *
 * Reading services() tells you everything the plugin does, in one screen.
 */
final class Plugin {

	/**
	 * Single shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Guards against booting twice.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Get the shared instance.
	 *
	 * A singleton is acceptable here because WordPress itself has exactly one
	 * plugin lifecycle per request. Services are NOT singletons; they are plain
	 * objects created here, which keeps them testable.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register every service's hooks. Safe to call more than once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		foreach ( $this->services() as $service ) {
			$service->register();
		}
	}

	/**
	 * Build the list of services for this request.
	 *
	 * Admin-only and CLI-only services are skipped on the front end, so their
	 * code is never even autoloaded there.
	 *
	 * @return Service[]
	 */
	private function services(): array {
		$services = array(
			new PostTypes(),
			new Taxonomies(),
			new MetaRegistry(),
		);

		if ( is_admin() ) {
			$services[] = new MetaBox( PostTypes::SHOW, __( 'Show details', 'encore-core' ) );
			$services[] = new MetaBox( PostTypes::ARTIST, __( 'Artist details', 'encore-core' ) );
			$services[] = new MetaBox( PostTypes::VENUE, __( 'Venue details', 'encore-core' ) );
			$services[] = new ShowListColumns();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$services[] = new CliService();
		}

		/**
		 * Filter the services Encore Core boots. Lets a site-specific plugin add
		 * or replace a service without editing this file.
		 *
		 * @param Service[] $services Services to register.
		 */
		return apply_filters( 'encore_core_services', $services );
	}
}
