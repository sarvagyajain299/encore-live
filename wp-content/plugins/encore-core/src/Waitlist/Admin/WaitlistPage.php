<?php
/**
 * Encore → Waitlists admin screen, actions and CSV export.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Admin;

use EncoreCore\Admin\SettingsPage;
use EncoreCore\Contracts\Service;
use EncoreCore\Domain\Tier;
use EncoreCore\Content\PostTypes;
use EncoreCore\Waitlist\Status;
use EncoreCore\Waitlist\WaitlistNotifier;
use EncoreCore\Waitlist\WaitlistRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Two capability levels:
 *   edit_others_posts  (Editors): view the screen and numbers; emails masked.
 *   manage_woocommerce (Shop managers, admins): full emails, row/bulk actions, CSV export.
 * Every state-changing request checks BOTH a nonce and manage_woocommerce.
 */
final class WaitlistPage implements Service {

	public const SLUG      = 'encore-waitlists';
	public const NONCE_ROW = 'encore_wl_row_';
	private const VIEW_CAP = 'edit_others_posts';
	private const EDIT_CAP = 'manage_woocommerce';
	private const EXPORT   = 'encore_waitlist_export';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Create.
	 *
	 * @param WaitlistRepository $repo     Data.
	 * @param WaitlistNotifier   $notifier For "Notify now".
	 */
	public function __construct(
		private readonly WaitlistRepository $repo,
		private readonly WaitlistNotifier $notifier
	) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 20 );
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
	}

	/**
	 * Submenu under Encore. Editors see Encore → Waitlists only (Settings needs manage_options).
	 */
	public function menu(): void {
		$this->hook = (string) add_submenu_page(
			SettingsPage::MENU_SLUG,
			__( 'Waitlists', 'encore-core' ),
			__( 'Waitlists', 'encore-core' ),
			self::VIEW_CAP,
			self::SLUG,
			array( $this, 'render' )
		);
		// Process actions on load-{hook}: before any output, so we can redirect.
		add_action( 'load-' . $this->hook, array( $this, 'handle_actions' ) );
	}

	/**
	 * Row and bulk actions → redirect with a message (Post/Redirect/Get).
	 */
	public function handle_actions(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- verified below per action type.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '-1' === $action || '' === $action ) {
			$action = isset( $_REQUEST['action2'] ) ? sanitize_key( wp_unslash( $_REQUEST['action2'] ) ) : '';
		}
		if ( ! in_array( $action, array( 'notify', 'remove' ), true ) ) {
			return;
		}
		if ( ! current_user_can( self::EDIT_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change waitlist entries.', 'encore-core' ), 403 );
		}

		if ( isset( $_REQUEST['entry'] ) && ! is_array( $_REQUEST['entry'] ) ) {
			$ids = array( absint( $_REQUEST['entry'] ) );
			check_admin_referer( self::NONCE_ROW . $ids[0] );
		} else {
			check_admin_referer( 'bulk-entries' ); // WP_List_Table's bulk nonce: "bulk-{plural}".
			$ids = isset( $_REQUEST['entry'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['entry'] ) ) : array();
		}
		// phpcs:enable

		$actor = 'user:' . get_current_user_id();
		$done  = 0;
		if ( 'remove' === $action ) {
			$done = $this->repo->delete( $ids, $actor );
		} else {
			foreach ( $ids as $id ) {
				$done += $this->notifier->notify( $id, $actor, 'admin notify now' ) ? 1 : 0;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'  => self::SLUG,
					'done'  => $action,
					'count' => $done,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::VIEW_CAP ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

		$filters    = $this->filters();
		$can_manage = current_user_can( self::EDIT_CAP );
		$table      = new WaitlistListTable( $this->repo, $filters, $can_manage );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Waitlists', 'encore-core' ); ?></h1>
			<?php if ( $can_manage ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( $this->export_url( $filters ) ); ?>"><?php esc_html_e( 'Export CSV', 'encore-core' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php $this->notice(); ?>
			<?php $this->summary(); ?>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php $this->filter_controls( $filters ); ?>
				<?php $table->search_box( __( 'Search email', 'encore-core' ), 'encore-wl-search' ); ?>
			</form>

			<form method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Stream a CSV of the filtered entries, in chunks.
	 *
	 * Streaming (fputcsv straight to php://output, 500 rows at a time) keeps
	 * memory flat however big the list gets; building one giant string would not.
	 */
	public function export(): void {
		if ( ! current_user_can( self::EDIT_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export waitlists.', 'encore-core' ), 403 );
		}
		check_admin_referer( self::EXPORT );

		$filters = $this->filters();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="encore-waitlist-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming to the response, not the filesystem.
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel reads Arabic names correctly.
		fputcsv( $out, array( 'id', 'show', 'tier', 'name', 'email', 'phone', 'qty', 'status', 'joined_utc', 'confirmed_utc', 'notified_utc', 'order_id' ) );

		$last = 0;
		do {
			$rows = $this->repo->export_chunk( $filters, $last, 500 );
			foreach ( $rows as $row ) {
				fputcsv(
					$out,
					array_map(
						array( self::class, 'csv_safe' ),
						array( $row['id'], get_the_title( (int) $row['show_id'] ), $row['tier'], $row['name'], $row['email'], $row['phone'], $row['qty'], $row['status'], $row['created_at'], $row['confirmed_at'], $row['notified_at'], $row['converted_order_id'] )
					)
				);
				$last = (int) $row['id'];
			}
			flush();
			$fetched = count( $rows );
		} while ( 500 === $fetched );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralise CSV/formula injection: a "name" like =HYPERLINK(...) would
	 * otherwise execute when the file is opened in Excel.
	 *
	 * @param mixed $value Cell.
	 */
	public static function csv_safe( mixed $value ): string {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ? "'" . $value : $value;
	}

	/**
	 * Per-show summary panel (one GROUP BY query).
	 */
	private function summary(): void {
		$rows = $this->repo->summary();
		if ( ! $rows ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'By show', 'encore-core' ); ?></h2>
		<table class="widefat striped" style="max-width:900px;margin-bottom:1.5em">
			<thead><tr>
				<th><?php esc_html_e( 'Show', 'encore-core' ); ?></th>
				<th><?php esc_html_e( 'Waiting', 'encore-core' ); ?></th>
				<th><?php esc_html_e( 'Notified', 'encore-core' ); ?></th>
				<th><?php esc_html_e( 'Converted', 'encore-core' ); ?></th>
				<th><?php esc_html_e( 'Conversion rate', 'encore-core' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php $rate = (int) $row['ever_notified'] > 0 ? round( 100 * (int) $row['converted'] / (int) $row['ever_notified'] ) . '%' : '—'; ?>
				<tr>
					<td><a href="
					<?php
					echo esc_url(
						add_query_arg(
							array(
								'page'    => self::SLUG,
								'show_id' => (int) $row['show_id'],
							),
							admin_url( 'admin.php' )
						)
					);
					?>
									"><?php echo esc_html( get_the_title( (int) $row['show_id'] ) ); ?></a></td>
					<td><?php echo esc_html( (string) (int) $row['waiting'] ); ?></td>
					<td><?php echo esc_html( (string) (int) $row['notified'] ); ?></td>
					<td><?php echo esc_html( (string) (int) $row['converted'] ); ?></td>
					<td title="<?php esc_attr_e( 'Converted ÷ everyone ever notified', 'encore-core' ); ?>"><?php echo esc_html( $rate ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Show / tier / status dropdowns.
	 *
	 * @param array<string, mixed> $filters Current.
	 */
	private function filter_controls( array $filters ): void {
		$shows = get_posts(
			array(
				'post_type'      => PostTypes::SHOW,
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- admin filter list.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		echo '<div class="alignleft actions" style="margin-bottom:8px">';
		echo '<label class="screen-reader-text" for="encore-wl-show">' . esc_html__( 'Show', 'encore-core' ) . '</label><select id="encore-wl-show" name="show_id"><option value="">' . esc_html__( 'All shows', 'encore-core' ) . '</option>';
		foreach ( $shows as $show ) {
			printf( '<option value="%d"%s>%s</option>', (int) $show->ID, selected( (int) $filters['show_id'], (int) $show->ID, false ), esc_html( get_the_title( $show ) ) );
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="encore-wl-tier">' . esc_html__( 'Tier', 'encore-core' ) . '</label><select id="encore-wl-tier" name="tier"><option value="">' . esc_html__( 'All tiers', 'encore-core' ) . '</option>';
		foreach ( Tier::values() as $tier ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $tier ), selected( $filters['tier'], $tier, false ) );
		}
		echo '</select> ';
		echo '<label class="screen-reader-text" for="encore-wl-status">' . esc_html__( 'Status', 'encore-core' ) . '</label><select id="encore-wl-status" name="status"><option value="">' . esc_html__( 'All statuses', 'encore-core' ) . '</option>';
		foreach ( Status::cases() as $status ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $status->value ), selected( $filters['status'], $status->value, false ), esc_html( $status->label() ) );
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'encore-core' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Admin notice after an action.
	 */
	private function notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$done  = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		// phpcs:enable
		if ( '' === $done ) {
			return;
		}
		$text = 'remove' === $done
			/* translators: %d: number of entries. */
			? sprintf( _n( '%d entry removed.', '%d entries removed.', $count, 'encore-core' ), $count )
			/* translators: %d: number of entries. */
			: sprintf( _n( '%d fan notified.', '%d fans notified.', $count, 'encore-core' ), $count );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/**
	 * Current filters from the query string (validated).
	 *
	 * @return array{show_id: int, tier: string, status: string, search: string}
	 */
	private function filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$tier    = isset( $_GET['tier'] ) ? sanitize_text_field( wp_unslash( $_GET['tier'] ) ) : '';
		$status  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$filters = array(
			'show_id' => isset( $_GET['show_id'] ) ? absint( $_GET['show_id'] ) : 0,
			'tier'    => in_array( $tier, Tier::values(), true ) ? $tier : '',
			'status'  => Status::tryFrom( $status ) ? $status : '',
			'search'  => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		// phpcs:enable
		return $filters;
	}

	/**
	 * Export link carrying the current filters and a nonce.
	 *
	 * @param array<string, mixed> $filters Filters.
	 */
	private function export_url( array $filters ): string {
		return wp_nonce_url(
			add_query_arg(
				array_filter( $filters ) + array( 'action' => self::EXPORT ),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT
		);
	}
}
