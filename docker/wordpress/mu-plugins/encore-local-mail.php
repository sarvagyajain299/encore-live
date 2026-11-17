<?php
/**
 * Plugin Name: Encore Local Mail (Mailpit)
 * Description: Local and development only. Routes every wp_mail() through Mailpit's SMTP server so no real email ever leaves the machine.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * Why a must-use plugin: mu-plugins load automatically, before normal plugins,
 * and cannot be deactivated from the admin. That suits environment plumbing.
 *
 * @package EncoreLive
 */

defined( 'ABSPATH' ) || exit;

if ( ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	return;
}

add_action(
	'phpmailer_init',
	static function ( $phpmailer ): void {
		$host = getenv( 'ENCORE_SMTP_HOST' );
		$port = getenv( 'ENCORE_SMTP_PORT' );

		// PHPMailer uses its own property names, so WPCS naming rules don't apply here.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->isSMTP();
		$phpmailer->Host        = $host ? $host : 'mailpit';
		$phpmailer->Port        = $port ? (int) $port : 1025;
		$phpmailer->SMTPAuth    = false;
		$phpmailer->SMTPAutoTLS = false;
		// phpcs:enable
	}
);

/*
 * WordPress builds the default sender as "wordpress@<site host>". Locally the
 * host is "localhost", which has no TLD, so PHPMailer rejects it as an invalid
 * address and wp_mail() silently returns false. Swap in a valid local domain.
 */
add_filter(
	'wp_mail_from',
	static function ( string $from ): string {
		return str_ends_with( $from, '@localhost' ) ? 'wordpress@encore.test' : $from;
	}
);
