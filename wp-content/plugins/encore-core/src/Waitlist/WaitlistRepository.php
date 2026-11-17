<?php
/**
 * All SQL for the waitlist.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

// Custom tables have no WordPress API and must be queried directly; every
// query goes through $wpdb->prepare(). Table names come from Schema (never input).
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Times are stored in UTC ('Y-m-d H:i:s'), like WordPress's *_gmt columns.
 * Converting only at display time avoids daylight-saving surprises and keeps
 * comparisons with UTC_TIMESTAMP() correct.
 */
final class WaitlistRepository {

	/**
	 * One entry as an associative array.
	 *
	 * @param int $id Entry ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;
		$table = Schema::entries();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Find by the unique key (show, tier, email).
	 *
	 * @param int    $show_id      Show.
	 * @param int    $variation_id Tier variation.
	 * @param string $email        Lower-cased email.
	 * @return array<string, mixed>|null
	 */
	public function find_unique( int $show_id, int $variation_id, string $email ): ?array {
		global $wpdb;
		$table = Schema::entries();
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE show_id = %d AND variation_id = %d AND email = %s", $show_id, $variation_id, $email ),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Insert a new pending entry and its first log row, atomically.
	 *
	 * @param JoinRequest $request    Validated request.
	 * @param string      $tier       Tier label.
	 * @param string      $token_hash Hash of the confirmation token.
	 * @return int New ID, or 0 if a concurrent request inserted the same fan first.
	 */
	public function insert( JoinRequest $request, string $tier, string $token_hash ): int {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );

		$table = Schema::entries();
		$wpdb->query( 'START TRANSACTION' );
		// INSERT IGNORE + UNIQUE KEY: if two submits race, MySQL lets exactly one win.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (show_id, variation_id, tier, name, email, phone, qty, status, token_hash, created_at) VALUES (%d, %d, %s, %s, %s, %s, %d, %s, %s, %s)",
				$request->show_id,
				$request->variation_id,
				$tier,
				$request->name,
				$request->email,
				$request->phone,
				$request->qty,
				Status::PendingConfirmation->value,
				$token_hash,
				$now
			)
		);
		if ( 1 !== $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		$this->write_log( $id, '', Status::PendingConfirmation->value, 'fan', 'joined', $now );
		$wpdb->query( 'COMMIT' );

		return $id;
	}

	/**
	 * Move an entry to a new status, if the state machine allows it.
	 *
	 * Runs in a transaction with SELECT … FOR UPDATE: the row is locked from
	 * the moment we read its status until COMMIT, so two workers (say a
	 * "notify now" click and the scheduled job) can't both act on it.
	 * The status change and its audit row commit together or not at all.
	 * This is why the tables must be InnoDB (row locks + transactions);
	 * MyISAM would silently ignore START TRANSACTION.
	 *
	 * @param int                  $id      Entry ID.
	 * @param Status               $to      Target status.
	 * @param string               $actor   Who: 'system', 'fan', 'user:12'.
	 * @param string               $context Why, for the audit log.
	 * @param array<string, mixed> $fields  Extra columns to set (e.g. claim_expires_at).
	 * @param Status|null          $expect  Only transition if currently this status.
	 * @return bool True when the transition happened.
	 */
	public function transition( int $id, Status $to, string $actor, string $context = '', array $fields = array(), ?Status $expect = null ): bool {
		global $wpdb;
		$table = Schema::entries();

		$wpdb->query( 'START TRANSACTION' );
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d FOR UPDATE", $id ) );
		$from    = is_string( $current ) ? Status::tryFrom( $current ) : null;

		if ( ! $from || ! $from->can_transition_to( $to ) || ( $expect && $expect !== $from ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$allowed = array( 'claim_expires_at', 'confirmed_at', 'notified_at', 'converted_order_id', 'token_hash', 'created_at', 'name', 'phone', 'qty' );
		$data    = array_intersect_key( $fields, array_flip( $allowed ) );
		$data   += array( 'status' => $to->value );

		$updated = $wpdb->update( $table, $data, array( 'id' => $id ) );
		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$this->write_log( $id, $from->value, $to->value, $actor, $context, gmdate( 'Y-m-d H:i:s' ) );
		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Clear the confirmation token (single use) without a status change.
	 *
	 * @param int    $id   Entry ID.
	 * @param string $hash New hash ('' = none).
	 */
	public function set_token_hash( int $id, string $hash ): void {
		global $wpdb;
		$wpdb->update( Schema::entries(), array( 'token_hash' => $hash ), array( 'id' => $id ) );
	}

	/**
	 * Waiting entries for a tier, FIFO (served by the `queue` index).
	 *
	 * @param int $variation_id Tier variation.
	 * @param int $limit        Max rows.
	 * @return array<int, array{id:int, qty:int}>
	 */
	public function queue( int $variation_id, int $limit = 200 ): array {
		global $wpdb;
		$table = Schema::entries();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, qty FROM {$table} WHERE variation_id = %d AND status = %s ORDER BY confirmed_at ASC, id ASC LIMIT %d",
				$variation_id,
				Status::Waiting->value,
				$limit
			),
			ARRAY_A
		);
		return array_map(
			static fn( array $r ): array => array(
				'id'  => (int) $r['id'],
				'qty' => (int) $r['qty'],
			),
			$rows
		);
	}

	/**
	 * How many waiting entries a tier has.
	 *
	 * @param int $variation_id Tier variation.
	 */
	public function waiting_count( int $variation_id ): int {
		global $wpdb;
		$table = Schema::entries();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE variation_id = %d AND status = %s", $variation_id, Status::Waiting->value ) );
	}

	/**
	 * 1-based place in line for a waiting entry (null when not waiting).
	 *
	 * @param array<string, mixed> $entry Entry row.
	 */
	public function position( array $entry ): ?int {
		if ( Status::Waiting->value !== $entry['status'] ) {
			return null;
		}
		global $wpdb;
		$table = Schema::entries();
		$ahead = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE variation_id = %d AND status = %s AND (confirmed_at < %s OR (confirmed_at = %s AND id < %d))",
				(int) $entry['variation_id'],
				Status::Waiting->value,
				$entry['confirmed_at'],
				$entry['confirmed_at'],
				(int) $entry['id']
			)
		);
		return $ahead + 1;
	}

	/**
	 * Tickets "softly reserved" by notified fans whose claim window is still open.
	 *
	 * @param int $variation_id Tier variation.
	 */
	public function open_claims_qty( int $variation_id ): int {
		global $wpdb;
		$table = Schema::entries();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(qty), 0) FROM {$table} WHERE variation_id = %d AND status = %s AND claim_expires_at > UTC_TIMESTAMP()",
				$variation_id,
				Status::Notified->value
			)
		);
	}

	/**
	 * Active entries matching an order line, for conversion tracking.
	 *
	 * @param string $email        Billing email.
	 * @param int    $variation_id Variation bought.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_convertible( string $email, int $variation_id ): array {
		global $wpdb;
		$table = Schema::entries();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE email = %s AND variation_id = %d AND status IN (%s, %s)",
				strtolower( $email ),
				$variation_id,
				Status::Waiting->value,
				Status::Notified->value
			),
			ARRAY_A
		);
	}

	/**
	 * Per-show summary in ONE query (GROUP BY), for the admin panel.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function summary(): array {
		global $wpdb;
		$table = Schema::entries();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT show_id,
					SUM(status = %s) AS waiting,
					SUM(status = %s) AS notified,
					SUM(status = %s) AS converted,
					SUM(status IN (%s, %s)) AS ever_notified
				FROM {$table}
				GROUP BY show_id
				ORDER BY waiting DESC",
				Status::Waiting->value,
				Status::Notified->value,
				Status::Converted->value,
				Status::Notified->value,
				Status::Converted->value
			),
			ARRAY_A
		);
	}

	/**
	 * Admin list query with filters, search, sorting and paging.
	 *
	 * @param array<string, mixed> $args show_id, tier, status, search, orderby, order, per_page, page.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public function search( array $args ): array {
		global $wpdb;
		$table              = Schema::entries();
		[ $where, $params ] = $this->where( $args );

		// ORDER BY can't take placeholders, so it comes from a whitelist, never from raw input.
		$columns = array(
			'created_at'   => 'created_at',
			'confirmed_at' => 'confirmed_at',
			'qty'          => 'qty',
			'status'       => 'status',
			'position'     => 'confirmed_at',
		);
		$orderby = $columns[ $args['orderby'] ?? '' ] ?? 'created_at';
		$order   = 'DESC' === strtoupper( (string) ( $args['order'] ?? 'ASC' ) ) ? 'DESC' : 'ASC';

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- placeholders are built in where().
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- placeholders are built in where().
			ARRAY_A
		);

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Rows for CSV export, in keyset-paginated chunks ("id > last seen"),
	 * which stays fast however deep the export goes, unlike OFFSET.
	 *
	 * @param array<string, mixed> $args     Same filters as search().
	 * @param int                  $after_id Last ID already exported.
	 * @param int                  $limit    Chunk size.
	 * @return array<int, array<string, mixed>>
	 */
	public function export_chunk( array $args, int $after_id, int $limit = 500 ): array {
		global $wpdb;
		$table              = Schema::entries();
		[ $where, $params ] = $this->where( $args );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} AND id > %d ORDER BY id ASC LIMIT %d", array_merge( $params, array( $after_id, $limit ) ) ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- placeholders are built in where().
			ARRAY_A
		);
	}

	/**
	 * Entries whose claim window has lapsed.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function lapsed_claims(): array {
		global $wpdb;
		$table = Schema::entries();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT id, variation_id FROM {$table} WHERE status = %s AND claim_expires_at <= UTC_TIMESTAMP() LIMIT 500", Status::Notified->value ),
			ARRAY_A
		);
	}

	/**
	 * Unconfirmed entries older than $hours.
	 *
	 * @param int $hours Age.
	 * @return int[]
	 */
	public function stale_pending( int $hours ): array {
		global $wpdb;
		$table = Schema::entries();
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE status = %s AND created_at <= UTC_TIMESTAMP() - INTERVAL %d HOUR LIMIT 500", Status::PendingConfirmation->value, $hours )
			)
		);
	}

	/**
	 * All entries for an email (privacy exporter), paged.
	 *
	 * @param string $email Email.
	 * @param int    $page  1-based page.
	 * @param int    $per   Page size.
	 * @return array<int, array<string, mixed>>
	 */
	public function by_email( string $email, int $page, int $per = 100 ): array {
		global $wpdb;
		$table = Schema::entries();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT %d OFFSET %d", strtolower( $email ), $per, ( max( 1, $page ) - 1 ) * $per ),
			ARRAY_A
		);
	}

	/**
	 * Delete entries and their log rows (admin "Remove", privacy eraser).
	 *
	 * @param int[]  $ids   Entry IDs.
	 * @param string $actor Who deleted (kept in PHP error log only; the log rows go too).
	 * @return int Rows deleted.
	 */
	public function delete( array $ids, string $actor ): int {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		unset( $actor );
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$wpdb->query( 'START TRANSACTION' );
		$log     = Schema::log();
		$entries = Schema::entries();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$log} WHERE entry_id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- $in is a list of %d.
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$entries} WHERE id IN ({$in})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders -- $in is a list of %d.
		$wpdb->query( 'COMMIT' );

		return $deleted;
	}

	/**
	 * Audit log for one entry, oldest first.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function log_for( int $entry_id ): array {
		global $wpdb;
		$table = Schema::log();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE entry_id = %d ORDER BY id ASC", $entry_id ), ARRAY_A );
	}

	/**
	 * WHERE clause + params from admin filters.
	 *
	 * @param array<string, mixed> $args Filters.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function where( array $args ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['show_id'] ) ) {
			$where[]  = 'show_id = %d';
			$params[] = (int) $args['show_id'];
		}
		if ( ! empty( $args['tier'] ) ) {
			$where[]  = 'tier = %s';
			$params[] = (string) $args['tier'];
		}
		if ( ! empty( $args['status'] ) && Status::tryFrom( (string) $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'email LIKE %s';
			$params[] = '%' . $wpdb->esc_like( strtolower( (string) $args['search'] ) ) . '%';
		}

		return array( implode( ' AND ', $where ), $params );
	}

	/**
	 * Insert an audit row (inside the caller's transaction).
	 *
	 * @param int    $entry_id Entry.
	 * @param string $from     From status ('' for creation).
	 * @param string $to       To status.
	 * @param string $actor    Who.
	 * @param string $context  Why.
	 * @param string $now      UTC timestamp.
	 */
	private function write_log( int $entry_id, string $from, string $to, string $actor, string $context, string $now ): void {
		global $wpdb;
		$wpdb->insert(
			Schema::log(),
			array(
				'entry_id'    => $entry_id,
				'from_status' => $from,
				'to_status'   => $to,
				'actor'       => substr( $actor, 0, 60 ),
				'context'     => substr( $context, 0, 255 ),
				'created_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
// phpcs:enable
