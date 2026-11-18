<?php
/**
 * Builds and registers every waitlist service.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Contracts\Service;
use EncoreCore\Support\TransientStore;
use EncoreCore\Waitlist\Admin\WaitlistPage;
use EncoreCore\Waitlist\Emails\EmailRegistry;
use EncoreCore\Waitlist\Http\LinkHandler;
use EncoreCore\Waitlist\Http\NoJsHandler;
use EncoreCore\Waitlist\Http\WaitlistController;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root for the waitlist: the one place that knows how the
 * objects fit together. Everything else receives its collaborators through
 * the constructor (dependency injection), which is what made the pure parts
 * unit-testable.
 */
final class WaitlistModule implements Service {

	/**
	 * Build the graph and register services for this request.
	 */
	public function register(): void {
		Schema::maybe_upgrade();

		$repo     = new WaitlistRepository();
		$links    = Links::create();
		$notifier = new WaitlistNotifier( $repo, new BatchSelector(), $links );
		$service  = new WaitlistService( $repo, $links );
		// TODO(sarvagya): make the limit configurable with a filter, e.g.
		// (int) apply_filters( 'encore_waitlist_rate_limit', 5 ) passed as RateLimiter's 3rd argument.
		// Document the filter in docs/INTEGRATION_CONTRACT.md (section 5, Hooks).
		$limiter = new RateLimiter( new TransientStore(), wp_salt( 'nonce' ) );
		$handler = new JoinHandler( $service, $limiter );

		$services = array(
			new EmailRegistry(),
			new Scheduler( $notifier ),
			new RestockListener( $repo ),
			new ConversionTracker( $repo ),
			new Privacy( $repo ),
			new ShowCleanup( $repo ),
			new WaitlistController( $handler ),
			new NoJsHandler( $handler ),
		);

		if ( is_admin() ) {
			$services[] = new WaitlistPage( $repo, $notifier );
		} else {
			$services[] = new WaitlistForm();
			$services[] = new LinkHandler( $service, $repo );
		}

		foreach ( $services as $item ) {
			$item->register();
		}
	}
}
