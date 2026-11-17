<?php
/**
 * `wp encore` commands.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Cli;

use EncoreCore\Domain\ShowStatusCalculator;
use EncoreCore\Seed\DemoSeeder;
use EncoreCore\Seed\PlaceholderImage;
use EncoreCore\Tickets\TicketProductFactory;
use Throwable;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage Encore Live demo content.
 *
 * WP-CLI reads these docblocks to build `wp help encore`.
 */
final class EncoreCommand {

	/**
	 * Create demo artists, venues, shows and ticket products.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Delete existing demo content first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp encore seed
	 *     wp encore seed --force
	 *
	 * @param string[]              $args       Positional args (unused).
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		unset( $args );
		$seeder = $this->seeder();

		if ( $seeder->has_seeded_content() ) {
			if ( ! WP_CLI\Utils\get_flag_value( $assoc_args, 'force', false ) ) {
				WP_CLI::error( 'Demo content already exists. Run `wp encore reset` first, or use --force.' );
			}
			$seeder->reset( array( WP_CLI::class, 'log' ) );
		}

		try {
			$counts = $seeder->seed( array( WP_CLI::class, 'log' ) );
		} catch ( Throwable $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( sprintf( 'Seeded %d artists, %d venues, %d shows.', $counts['artists'], $counts['venues'], $counts['shows'] ) );
	}

	/**
	 * Delete all demo content created by `wp encore seed`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp encore reset
	 */
	public function reset(): void {
		$deleted = $this->seeder()->reset( array( WP_CLI::class, 'log' ) );
		WP_CLI::success( sprintf( 'Removed %d demo items.', $deleted ) );
	}

	/**
	 * Wire the seeder with its dependencies.
	 */
	private function seeder(): DemoSeeder {
		return new DemoSeeder( new TicketProductFactory(), new ShowStatusCalculator(), new PlaceholderImage() );
	}
}
