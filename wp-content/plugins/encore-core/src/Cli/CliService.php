<?php
/**
 * Registers WP-CLI commands.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Cli;

use EncoreCore\Contracts\Service;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Kept separate from EncoreCommand because WP-CLI turns every public method
 * of a command class into a subcommand. register() must not become `wp encore register`.
 */
final class CliService implements Service {

	/**
	 * Add the `wp encore` command namespace.
	 */
	public function register(): void {
		WP_CLI::add_command( 'encore', EncoreCommand::class );
	}
}
