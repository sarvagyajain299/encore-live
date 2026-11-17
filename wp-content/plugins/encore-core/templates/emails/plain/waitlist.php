<?php
/**
 * Waitlist email (plain text).
 *
 * @var string               $email_heading
 * @var string[]             $intro
 * @var string               $button_url
 * @var string               $button_label
 * @var array<string,string> $details
 * @var string               $unsubscribe_url
 * @var string               $additional_content
 *
 * @package EncoreCore
 */

defined( 'ABSPATH' ) || exit;

// Plain-text emails are not HTML: escaping with esc_html would show &amp; entities.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

echo '= ' . wp_strip_all_tags( $email_heading ) . " =\n\n";

foreach ( $intro as $encore_paragraph ) {
	echo wp_strip_all_tags( $encore_paragraph ) . "\n\n";
}

foreach ( $details as $encore_label => $encore_value ) {
	echo wp_strip_all_tags( $encore_label ) . ': ' . wp_strip_all_tags( $encore_value ) . "\n";
}

if ( $button_url ) {
	echo "\n" . wp_strip_all_tags( $button_label ) . ': ' . esc_url_raw( $button_url ) . "\n";
}

if ( $additional_content ) {
	echo "\n" . wp_strip_all_tags( wptexturize( $additional_content ) ) . "\n";
}

if ( $unsubscribe_url ) {
	echo "\n----\n" . esc_html__( 'Unsubscribe from this waitlist:', 'encore-core' ) . ' ' . esc_url_raw( $unsubscribe_url ) . "\n";
}

echo "\n" . wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
// phpcs:enable
