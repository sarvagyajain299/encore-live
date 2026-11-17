<?php
/**
 * Encore → Settings screen.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Admin;

use EncoreCore\Contracts\Service;
use EncoreCore\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Built on the Settings API: register_setting() + settings_fields() give us
 * the nonce, the capability check (manage_options) and the save handler
 * (options.php) for free. Our only job is the sanitize callback.
 */
final class SettingsPage implements Service {

	public const MENU_SLUG = 'encore';
	private const GROUP    = 'encore_settings_group';

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'settings' ) );
	}

	/**
	 * Top-level "Encore" menu; Settings is its first page. Waitlists join in Phase 5.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'Encore', 'encore-core' ),
			__( 'Encore', 'encore-core' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-tickets',
			56
		);
		add_submenu_page( self::MENU_SLUG, __( 'Encore settings', 'encore-core' ), __( 'Settings', 'encore-core' ), 'manage_options', self::MENU_SLUG, array( $this, 'render' ) );
	}

	/**
	 * Register the option, section and fields.
	 */
	public function settings(): void {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::DEFAULTS,
			)
		);

		add_settings_section( 'encore_tickets', __( 'Tickets', 'encore-core' ), '__return_false', self::MENU_SLUG );
		add_settings_field(
			'max_tickets_per_order',
			__( 'Max tickets per order', 'encore-core' ),
			array( $this, 'number_field' ),
			self::MENU_SLUG,
			'encore_tickets',
			array(
				'label_for' => 'max_tickets_per_order',
				'help'      => __( 'Per show, across all tiers. 1–20.', 'encore-core' ),
			)
		);

		add_settings_section( 'encore_analytics', __( 'Analytics', 'encore-core' ), '__return_false', self::MENU_SLUG );
		add_settings_field(
			'gtm_container_id',
			__( 'Google Tag Manager container ID', 'encore-core' ),
			array( $this, 'text_field' ),
			self::MENU_SLUG,
			'encore_analytics',
			array(
				'label_for'   => 'gtm_container_id',
				'placeholder' => 'GTM-XXXXXXX',
				'help'        => __( 'Leave empty to disable GTM and the dataLayer events.', 'encore-core' ),
			)
		);
	}

	/**
	 * Print the page.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Encore settings', 'encore-core' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::GROUP ); // Nonce + option_page + action fields.
				do_settings_sections( self::MENU_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Number input.
	 *
	 * @param array<string, string> $args Field args.
	 */
	public function number_field( array $args ): void {
		$this->input( $args, 'number', 'min="1" max="20" class="small-text"' );
	}

	/**
	 * Text input.
	 *
	 * @param array<string, string> $args Field args.
	 */
	public function text_field( array $args ): void {
		$this->input( $args, 'text', 'class="regular-text"' );
	}

	/**
	 * Shared input markup.
	 *
	 * @param array<string, string> $args  Field args.
	 * @param string                $type  Input type.
	 * @param string                $extra Fixed extra attributes (trusted, not user input).
	 */
	private function input( array $args, string $type, string $extra ): void {
		$key = $args['label_for'];
		printf(
			'<input type="%1$s" id="%2$s" name="%3$s[%2$s]" value="%4$s" placeholder="%5$s" %6$s>',
			esc_attr( $type ),
			esc_attr( $key ),
			esc_attr( Settings::OPTION ),
			esc_attr( (string) Settings::get( $key ) ),
			esc_attr( $args['placeholder'] ?? '' ),
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constant strings defined in this class.
		);
		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}
}
