<?php
/**
 * Masks email addresses for low-privilege viewers.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Waitlist;

defined( 'ABSPATH' ) || exit;

/**
 * "layla.fan@example.com" -> "l********@e******.com"
 * Enough to recognise a familiar address, not enough to harvest it.
 */
final class EmailMask {

	/**
	 * Mask an address.
	 *
	 * @param string $email Email address.
	 */
	public static function mask( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return str_repeat( '*', max( 3, strlen( $email ) ) );
		}

		$local  = substr( $email, 0, $at );
		$domain = substr( $email, $at + 1 );
		$dot    = strrpos( $domain, '.' );
		$host   = false === $dot ? $domain : substr( $domain, 0, $dot );
		$tld    = false === $dot ? '' : substr( $domain, $dot );

		return self::keep_first( $local ) . '@' . self::keep_first( $host ) . $tld;
	}

	// TODO(sarvagya): add mask_phone( string $phone ): string so '+971501234567' becomes
	// '+97150*****67' (keep the first 6 and last 2 characters). Return '' for ''.
	// Add tests to tests/Unit/Waitlist/JoinRequestTest.php, then show the masked phone
	// in WaitlistListTable for users without manage_woocommerce (add a 'phone' column).

	/**
	 * Keep the first character, star the rest.
	 *
	 * @param string $part Part to mask.
	 */
	private static function keep_first( string $part ): string {
		return '' === $part ? '' : mb_substr( $part, 0, 1 ) . str_repeat( '*', max( 1, mb_strlen( $part ) - 1 ) );
	}
}
