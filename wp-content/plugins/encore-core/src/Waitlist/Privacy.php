<?php
/**
 * GDPR / UAE PDPL tools: export and erase waitlist data.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers with Tools → Export Personal Data and Tools → Erase Personal Data.
 * WordPress handles the request flow (email confirmation by the person,
 * admin approval, the ZIP download); we only supply the data and the deletion.
 * Both callbacks are paged (WordPress calls them repeatedly until done = true),
 * so a fan with thousands of rows can't time out the request.
 */
final class Privacy implements Service {

	private const PER_PAGE = 100;

	/**
	 * Create.
	 *
	 * @param WaitlistRepository $repo Data.
	 */
	public function __construct( private readonly WaitlistRepository $repo ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exporters.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_exporter( $exporters ): array {
		$exporters['encore-waitlist'] = array(
			'exporter_friendly_name' => __( 'Encore Live waitlists', 'encore-core' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array<string, array<string, mixed>> $erasers Erasers.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_eraser( $erasers ): array {
		$erasers['encore-waitlist'] = array(
			'eraser_friendly_name' => __( 'Encore Live waitlists', 'encore-core' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export one page of entries.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (1-based).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( $email, $page = 1 ): array {
		$rows = $this->repo->by_email( (string) $email, (int) $page, self::PER_PAGE );
		$data = array();
		foreach ( $rows as $row ) {
			$fields = array(
				__( 'Show', 'encore-core' )            => get_the_title( (int) $row['show_id'] ),
				__( 'Tier', 'encore-core' )            => $row['tier'],
				__( 'Name', 'encore-core' )            => $row['name'],
				__( 'Email', 'encore-core' )           => $row['email'],
				__( 'Phone', 'encore-core' )           => $row['phone'],
				__( 'Tickets wanted', 'encore-core' )  => $row['qty'],
				__( 'Status', 'encore-core' )          => $row['status'],
				__( 'Joined (UTC)', 'encore-core' )    => $row['created_at'],
				__( 'Confirmed (UTC)', 'encore-core' ) => (string) $row['confirmed_at'],
			);
			$data[] = array(
				'group_id'    => 'encore-waitlist',
				'group_label' => __( 'Waitlist entries', 'encore-core' ),
				'item_id'     => 'encore-waitlist-' . $row['id'],
				'data'        => array_map(
					static fn( string $name, $value ): array => array(
						'name'  => $name,
						'value' => (string) $value,
					),
					array_keys( $fields ),
					array_values( $fields )
				),
			);
		}
		return array(
			'data' => $data,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Erase one page of entries (and their audit log rows).
	 *
	 * Always page 1: rows deleted in the previous call no longer exist.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (unused, see above).
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( $email, $page = 1 ): array {
		unset( $page );
		$rows    = $this->repo->by_email( (string) $email, 1, self::PER_PAGE );
		$removed = $this->repo->delete( array_column( $rows, 'id' ), 'privacy-eraser' );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $rows ) < self::PER_PAGE,
		);
	}
}
