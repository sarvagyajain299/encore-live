<?php
/**
 * Activation and deactivation.
 *
 * @package EncoreCore
 */

namespace EncoreCore;

use EncoreCore\Content\PostTypes;
use EncoreCore\Content\Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Runs once when the plugin is switched on or off.
 */
final class Lifecycle {

	/**
	 * On activation, register the post types right away and flush rewrite rules.
	 *
	 * Without this, /shows/ would 404 until someone re-saved the Permalinks
	 * screen, because WordPress caches its rewrite rules in the database.
	 */
	public static function activate(): void {
		( new PostTypes() )->register_post_types();
		( new Taxonomies() )->register_taxonomies();
		flush_rewrite_rules();
		\EncoreCore\Waitlist\Schema::install();
		update_option( 'encore_core_version', ENCORE_CORE_VERSION );
	}

	/**
	 * On deactivation, flush rules so our URLs stop resolving. Data is kept:
	 * deleting content belongs in uninstall, never in deactivate.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
