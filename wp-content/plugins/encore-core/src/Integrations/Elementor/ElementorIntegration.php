<?php
/**
 * Registers our Elementor widgets.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Integrations\Elementor;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Hooking elementor/widgets/register is safe even without Elementor: the
 * action simply never fires, and the widget class is never loaded.
 */
final class ElementorIntegration implements Service {

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
	}

	/**
	 * Register widgets with Elementor's manager.
	 *
	 * @param \Elementor\Widgets_Manager $manager Widgets manager.
	 */
	public function widgets( $manager ): void {
		$manager->register( new UpcomingShowsWidget() );
	}
}
