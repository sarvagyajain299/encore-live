<?php
/**
 * Registers waitlist emails with WooCommerce and sends them.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Emails;

use EncoreCore\Contracts\Service;

defined( 'ABSPATH' ) || exit;

/**
 * The woocommerce_email_classes filter runs when WooCommerce builds its
 * mailer, i.e. only when an email is about to be sent or the settings page
 * is open, so the email classes are never loaded on normal page views.
 */
final class EmailRegistry implements Service {

	// IDs live here, not on the email classes: reading ConfirmationEmail::ID would autoload a
	// class that extends WC_Email, which may not be loaded yet (a fatal error found in testing).
	public const CONFIRMATION = 'encore_waitlist_confirmation';
	public const JOINED       = 'encore_waitlist_joined';
	public const TICKETS_BACK = 'encore_waitlist_tickets_back';

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'woocommerce_email_classes', array( $this, 'add' ) );
	}

	/**
	 * Add our emails.
	 *
	 * @param array<string, \WC_Email> $emails Registered emails.
	 * @return array<string, \WC_Email>
	 */
	public function add( $emails ): array {
		// Found in testing: on some front-end requests WooCommerce builds the mailer
		// before WC_Email is loaded, and "extends WC_Email" then fatals.
		if ( ! class_exists( 'WC_Email', false ) ) {
			require_once WC()->plugin_path() . '/includes/emails/class-wc-email.php';
		}

		$emails['Encore_Waitlist_Confirmation'] = new ConfirmationEmail();
		$emails['Encore_Waitlist_Joined']       = new JoinedEmail();
		$emails['Encore_Waitlist_Tickets_Back'] = new TicketsBackEmail();
		return $emails;
	}

	/**
	 * Send one of our emails by ID.
	 *
	 * @param string               $id        Email ID (e.g. ConfirmationEmail::ID).
	 * @param string               $recipient Address.
	 * @param array<string, mixed> $vars      Template data.
	 */
	public static function send( string $id, string $recipient, array $vars ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof WaitlistEmail && $email->id === $id ) {
				return $email->trigger( $recipient, $vars );
			}
		}
		return false;
	}
}
