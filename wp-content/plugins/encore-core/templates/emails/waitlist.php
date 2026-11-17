<?php
/**
 * Waitlist email (HTML). Shared by all three waitlist emails.
 *
 * Override in a theme: yourtheme/woocommerce/emails/waitlist.php
 *
 * @var WC_Email             $email
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

// We fire WooCommerce's own email hooks so the store header/footer render around our content.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

do_action( 'woocommerce_email_header', $email_heading, $email );

foreach ( $intro as $encore_paragraph ) {
	echo '<p>' . esc_html( $encore_paragraph ) . '</p>';
}

if ( $details ) :
	?>
	<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
		<?php foreach ( $details as $encore_label => $encore_value ) : ?>
			<tr>
				<th scope="row" style="text-align:left;"><?php echo esc_html( $encore_label ); ?></th>
				<td><?php echo esc_html( $encore_value ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
endif;

if ( $button_url ) :
	?>
	<p style="margin:24px 0;">
		<a href="<?php echo esc_url( $button_url ); ?>" style="background:#ff5b3a;color:#160805;padding:14px 24px;border-radius:999px;font-weight:bold;text-decoration:none;display:inline-block;"><?php echo esc_html( $button_label ); ?></a>
	</p>
	<p style="font-size:12px;"><?php esc_html_e( 'Button not working? Copy this link into your browser:', 'encore-core' ); ?><br><?php echo esc_html( $button_url ); ?></p>
	<?php
endif;

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

if ( $unsubscribe_url ) :
	?>
	<p style="font-size:12px;color:#777;">
		<?php esc_html_e( 'You are receiving this because you joined a waitlist on Encore Live.', 'encore-core' ); ?>
		<a href="<?php echo esc_url( $unsubscribe_url ); ?>"><?php esc_html_e( 'Unsubscribe from this waitlist', 'encore-core' ); ?></a>
	</p>
	<?php
endif;

do_action( 'woocommerce_email_footer', $email );
