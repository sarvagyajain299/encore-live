<?php
/**
 * Custom tables for the waitlist, with versioned migrations.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * Why custom tables instead of a post type + meta?
 *   The queue query ("next waiting fans for variation X, oldest first") needs
 *   real columns and composite indexes. Done in wp_postmeta, it would be
 *   several self-joins on an unindexed LONGTEXT column. Custom tables also give
 *   a UNIQUE constraint (one entry per fan per tier) enforced by MySQL itself.
 *
 * Why versioned migrations?
 *   Activation hooks do NOT run when a plugin is updated by copying files
 *   (git pull, deploys, or updates over FTP). So on every load we compare the
 *   stored schema version with the code's version and run the upgrade once
 *   when they differ. dbDelta() then diffs CREATE TABLE against the live table
 *   and adds missing columns and indexes without dropping data.
 *
 * dbDelta is picky: two spaces after PRIMARY KEY, one column per line, KEY
 * (not INDEX), no backticks. The SQL below follows those rules on purpose.
 */
final class Schema {

	public const VERSION        = '1.0.0';
	public const VERSION_OPTION = 'encore_waitlist_db_version';

	/**
	 * Entries table name.
	 */
	public static function entries(): string {
		global $wpdb;
		return $wpdb->prefix . 'encore_waitlist';
	}

	/**
	 * Audit log table name.
	 */
	public static function log(): string {
		global $wpdb;
		return $wpdb->prefix . 'encore_waitlist_log';
	}

	/**
	 * Run the migration if the stored version is behind. Cheap: one autoloaded option read.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or upgrade both tables.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$entries = self::entries();
		$log     = self::log();

		/*
		 * Indexes, and the query each one serves:
		 *  - show_variation_email (UNIQUE): one entry per fan per tier; makes
		 *    "already on the list?" an index lookup and blocks duplicates even
		 *    under concurrent submits.
		 *  - queue (variation_id, status, confirmed_at, id): the FIFO queue query
		 *    WHERE variation_id = ? AND status = 'waiting' ORDER BY confirmed_at, id.
		 *    The equality columns come first and the sort columns last, so MySQL
		 *    reads rows already in order (no filesort). The same index answers
		 *    "position in line" counts.
		 *  - status_claim (status, claim_expires_at): open claims (soft-reserved
		 *    tickets) and lapsed claims.
		 *  - status_created (status, created_at): unconfirmed entries older than 48 h.
		 *  - show_status (show_id, status): admin filters and the per-show GROUP BY summary.
		 *  - email: privacy export/erase and order-to-entry matching by email.
		 * email is varchar(190): 190 x 4 bytes (utf8mb4) = 760, under InnoDB's
		 * 767-byte index prefix limit on older MySQL/MariaDB row formats.
		 */
		dbDelta(
			"CREATE TABLE {$entries} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			show_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL,
			tier varchar(20) NOT NULL,
			name varchar(100) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(20) NOT NULL DEFAULT '',
			qty tinyint(3) unsigned NOT NULL DEFAULT 1,
			status varchar(25) NOT NULL DEFAULT 'pending_confirmation',
			token_hash char(64) NOT NULL DEFAULT '',
			claim_expires_at datetime NULL DEFAULT NULL,
			created_at datetime NOT NULL,
			confirmed_at datetime NULL DEFAULT NULL,
			notified_at datetime NULL DEFAULT NULL,
			converted_order_id bigint(20) unsigned NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY show_variation_email (show_id,variation_id,email),
			KEY queue (variation_id,status,confirmed_at,id),
			KEY status_claim (status,claim_expires_at),
			KEY status_created (status,created_at),
			KEY show_status (show_id,status),
			KEY email (email)
			) {$charset};"
		);

		// from/to are reserved words in SQL, hence from_status/to_status.
		dbDelta(
			"CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			from_status varchar(25) NOT NULL DEFAULT '',
			to_status varchar(25) NOT NULL,
			actor varchar(60) NOT NULL,
			context varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY entry (entry_id,id)
			) {$charset};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}
}
