<?php
/**
 * Encore → Waitlists list table.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Admin;

use EncoreCore\Waitlist\EmailMask;
use EncoreCore\Waitlist\Status;
use EncoreCore\Waitlist\WaitlistRepository;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * WP_List_Table gives the standard admin table: pagination, sortable
 * headers, bulk actions, row actions and screen options, styled like core.
 * Our job: columns, data and the per-row HTML (escaped).
 */
final class WaitlistListTable extends WP_List_Table {

	/**
	 * Create.
	 *
	 * @param WaitlistRepository   $repo    Data.
	 * @param array<string, mixed> $filters Current filters.
	 * @param bool                 $can_manage Whether the user gets full emails and actions.
	 */
	public function __construct(
		private readonly WaitlistRepository $repo,
		private readonly array $filters,
		private readonly bool $can_manage
	) {
		parent::__construct(
			array(
				'singular' => 'entry',
				'plural'   => 'entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		$columns = array(
			'email'        => __( 'Email', 'encore-core' ),
			'name'         => __( 'Name', 'encore-core' ),
			'show'         => __( 'Show', 'encore-core' ),
			'tier'         => __( 'Tier', 'encore-core' ),
			'qty'          => __( 'Qty', 'encore-core' ),
			'position'     => __( 'Position', 'encore-core' ),
			'status'       => __( 'Status', 'encore-core' ),
			'created_at'   => __( 'Joined', 'encore-core' ),
			'confirmed_at' => __( 'Confirmed', 'encore-core' ),
			'notified_at'  => __( 'Notified', 'encore-core' ),
		);
		return $this->can_manage ? array( 'cb' => '<input type="checkbox">' ) + $columns : $columns;
	}

	/**
	 * Sortable columns (keys must match the repository whitelist).
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'created_at'   => array( 'created_at', false ),
			'confirmed_at' => array( 'confirmed_at', false ),
			'qty'          => array( 'qty', false ),
			'status'       => array( 'status', false ),
			'position'     => array( 'position', false ),
		);
	}

	/**
	 * Bulk actions (managers only).
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return $this->can_manage ? array(
			'notify' => __( 'Notify now', 'encore-core' ),
			'remove' => __( 'Remove', 'encore-core' ),
		) : array();
	}

	/**
	 * Load rows.
	 */
	public function prepare_items(): void {
		$per_page = $this->get_items_per_page( 'encore_waitlist_per_page', 20 );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only sorting/paging params, whitelisted in the repository.
		$result = $this->repo->search(
			$this->filters + array(
				'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at',
				'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc',
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		// phpcs:enable

		$this->items           = $result['rows'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Checkbox.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="entry[]" value="%d">', (int) $item['id'] );
	}

	/**
	 * Email + row actions.
	 *
	 * @param array<string, mixed> $item Row.
	 */
	protected function column_email( $item ): string {
		if ( ! $this->can_manage ) {
			return esc_html( EmailMask::mask( (string) $item['email'] ) );
		}

		$actions = array();
		if ( Status::Waiting->value === $item['status'] ) {
			$actions['notify'] = sprintf( '<a href="%s">%s</a>', esc_url( $this->action_url( 'notify', (int) $item['id'] ) ), esc_html__( 'Notify now', 'encore-core' ) );
		}
		$actions['remove'] = sprintf(
			'<a href="%s" class="submitdelete">%s</a>',
			esc_url( $this->action_url( 'remove', (int) $item['id'] ) ),
			esc_html__( 'Remove', 'encore-core' )
		);

		return esc_html( (string) $item['email'] ) . $this->row_actions( $actions );
	}

	/**
	 * Other columns.
	 *
	 * @param array<string, mixed> $item        Row.
	 * @param string               $column_name Column.
	 */
	protected function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'show':
				return sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( (int) $item['show_id'] ) ), esc_html( WaitlistPage::show_label( (int) $item['show_id'] ) ) );
			case 'position':
				$position = $this->repo->position( $item );
				return null === $position ? '&mdash;' : esc_html( '#' . $position );
			case 'status':
				$status = Status::tryFrom( (string) $item['status'] );
				return sprintf( '<span class="encore-wl-status encore-wl-status--%s">%s</span>', esc_attr( (string) $item['status'] ), esc_html( $status ? $status->label() : (string) $item['status'] ) );
			case 'created_at':
			case 'confirmed_at':
			case 'notified_at':
				return $item[ $column_name ] ? esc_html( get_date_from_gmt( (string) $item[ $column_name ], 'j M Y, H:i' ) ) : '&mdash;';
			default:
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
		}
	}

	/**
	 * Empty state.
	 */
	public function no_items(): void {
		esc_html_e( 'No waitlist entries match these filters.', 'encore-core' );
	}

	/**
	 * Nonce-protected row action URL.
	 *
	 * @param string $action notify|remove.
	 * @param int    $id     Entry.
	 */
	private function action_url( string $action, int $id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'   => WaitlistPage::SLUG,
					'action' => $action,
					'entry'  => $id,
				),
				admin_url( 'admin.php' )
			),
			WaitlistPage::NONCE_ROW . $id
		);
	}
}
