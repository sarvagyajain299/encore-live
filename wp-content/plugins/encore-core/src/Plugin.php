<?php
/**
 * Plugin bootstrap and service registration.
 *
 * @package EncoreCore
 */

namespace EncoreCore;

use EncoreCore\Admin\MetaBox;
use EncoreCore\Admin\SettingsPage;
use EncoreCore\Analytics\DataLayer;
use EncoreCore\Performance\PageCachePurge;
use EncoreCore\Security\Hardening;
use EncoreCore\Seo\YoastIntegration;
use EncoreCore\Shows\ArchiveJob;
use EncoreCore\Integrations\Elementor\ElementorIntegration;
use EncoreCore\Rest\ShowsController;
use EncoreCore\Shows\Shortcodes;
use EncoreCore\Shows\ShowListRenderer;
use EncoreCore\Tickets\AttendeePhoneField;
use EncoreCore\Tickets\CartValidation;
use EncoreCore\Tickets\OrderDetails;
use EncoreCore\Tickets\ShowStatusSync;
use EncoreCore\Tickets\TicketPanel;
use EncoreCore\Waitlist\WaitlistModule;
use EncoreCore\Admin\ShowListColumns;
use EncoreCore\Cli\CliService;
use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;
use EncoreCore\Contracts\Service;
use EncoreCore\Meta\MetaRegistry;
use EncoreCore\Shows\ArchiveFilters;
use EncoreCore\Shows\ArtistIndex;
use EncoreCore\Shows\SearchScope;

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
			new ArtistIndex(),
			new ShowListRenderer(), // Registers cache invalidation hooks (needed in admin too).
			new Shortcodes(),
			new ShowsController(),
			new ElementorIntegration(),
			new YoastIntegration(),
			new Hardening(),
			new ArchiveJob(),
		);

		if ( ! is_admin() ) {
			$services[] = new ArchiveFilters();
			$services[] = new SearchScope();
		}

		// Ticketing needs WooCommerce. Without it the site still lists shows.
		if ( class_exists( 'WooCommerce' ) ) {
			$services[] = new CartValidation();
			$services[] = new OrderDetails();
			$services[] = new AttendeePhoneField();
			$services[] = new ShowStatusSync();
			$services[] = new WaitlistModule();
			$services[] = new PageCachePurge();
			if ( ! is_admin() ) {
				$services[] = new TicketPanel();
				$services[] = new DataLayer();
			}
		}

		if ( is_admin() ) {
			$services[] = new SettingsPage();
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
