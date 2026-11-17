<?php
/**
 * Base class for the three waitlist emails.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist\Emails;

use WC_Email;

defined( 'ABSPATH' ) || exit;

/**
 * Extending WC_Email (instead of calling wp_mail directly) gives us, for free:
 *  - a screen under WooCommerce → Settings → Emails to enable/disable each email
 *    and edit its subject, heading and extra content
 *  - the store's email header/footer, colours and logo
 *  - theme-overridable templates (yourtheme/woocommerce/emails/waitlist.php)
 *  - HTML/plain-text/multipart support and correct From headers
 *
 * One shared template renders all three; subclasses supply the words.
 */
abstract class WaitlistEmail extends WC_Email {

	/**
	 * Data for the current send.
	 *
	 * @var array<string, mixed>
	 */
	protected array $vars = array();

	/**
	 * Set up shared properties, then let WC_Email load saved settings.
	 */
	public function __construct() {
		$this->customer_email = true;
		$this->template_base  = ENCORE_CORE_DIR . '/templates/';
		$this->template_html  = 'emails/waitlist.php';
		$this->template_plain = 'emails/plain/waitlist.php';
		$this->placeholders   = array( '{show_title}' => '' );

		parent::__construct();
	}

	/**
	 * Send to one fan.
	 *
	 * @param string               $recipient Email address.
	 * @param array<string, mixed> $vars      Template data (show_title, show_url, tier, qty, name, links).
	 */
	public function trigger( string $recipient, array $vars ): bool {
		$this->setup_locale();
		$this->recipient                    = $recipient;
		$this->vars                         = $vars;
		$this->placeholders['{show_title}'] = (string) ( $vars['show_title'] ?? '' );

		$sent = false;
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
		return $sent;
	}

	/**
	 * Add RFC 8058 one-click unsubscribe headers (Gmail and Yahoo require them for bulk senders).
	 */
	public function get_headers() {
		$headers = parent::get_headers();
		if ( ! empty( $this->vars['unsubscribe_url'] ) ) {
			$headers .= 'List-Unsubscribe: <' . esc_url_raw( $this->vars['unsubscribe_url'] ) . ">\r\n";
			$headers .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
		}
		return $headers;
	}

	/**
	 * HTML body.
	 */
	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	/**
	 * Plain-text body.
	 */
	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	/**
	 * Words for this email: intro paragraphs, button URL and label.
	 *
	 * @param array<string, mixed> $vars Send data.
	 * @return array{intro: string[], button_url: string, button_label: string}
	 */
	abstract protected function body( array $vars ): array;

	/**
	 * Variables for the template.
	 *
	 * @param bool $plain Whether this is the plain-text version.
	 * @return array<string, mixed>
	 */
	private function template_args( bool $plain ): array {
		$body = $this->body( $this->vars );
		return array(
			'email'              => $this,
			'email_heading'      => $this->get_heading(),
			'intro'              => $body['intro'],
			'button_url'         => $body['button_url'],
			'button_label'       => $body['button_label'],
			'details'            => array_filter(
				array(
					__( 'Show', 'encore-core' )    => (string) ( $this->vars['show_title'] ?? '' ),
					__( 'Date', 'encore-core' )    => (string) ( $this->vars['show_date'] ?? '' ),
					__( 'Tier', 'encore-core' )    => (string) ( $this->vars['tier'] ?? '' ),
					__( 'Tickets', 'encore-core' ) => (string) ( $this->vars['qty'] ?? '' ),
				)
			),
			'unsubscribe_url'    => (string) ( $this->vars['unsubscribe_url'] ?? '' ),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'      => false,
			'plain_text'         => $plain,
		);
	}
}
