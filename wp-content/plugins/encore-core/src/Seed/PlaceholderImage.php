<?php
/**
 * Generates placeholder artwork with GD.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Seed;

use EncoreCore\Meta\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Draws an abstract "stage lights" image: a dark gradient plus soft coloured
 * spotlights. Colours derive from the title, so each artist gets a stable look.
 * Generated locally: no stock photos, no licences, no credits needed.
 */
final class PlaceholderImage {

	/**
	 * Create the image and add it to the Media Library.
	 *
	 * @param string $title Used for the colours, filename and alt text.
	 * @return int Attachment ID, or 0 when GD is unavailable.
	 */
	public function create_attachment( string $title ): int {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return 0;
		}

		$upload = wp_upload_bits( sanitize_title( $title ) . '.jpg', null, $this->render( $title, 1600, 900 ) );
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		/* translators: %s: artist or show name. */
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf( __( 'Abstract stage-light artwork for %s', 'encore-core' ), $title ) );
		update_post_meta( $attachment_id, Keys::SEEDED, 1 );

		return $attachment_id;
	}

	/**
	 * Render JPEG bytes.
	 *
	 * @param string $seed   Seed text for colours.
	 * @param int    $width  Width in px.
	 * @param int    $height Height in px.
	 */
	private function render( string $seed, int $width, int $height ): string {
		$hash  = md5( $seed );
		$image = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, true );

		// Vertical gradient from near-black to a deep tint.
		$top    = array( 12, 10, 18 );
		$bottom = array( hexdec( $hash[0] ) * 4, hexdec( $hash[1] ) * 2, hexdec( $hash[2] ) * 5 );
		for ( $y = 0; $y < $height; $y++ ) {
			$t     = $y / $height;
			$color = imagecolorallocate(
				$image,
				(int) ( $top[0] + ( $bottom[0] - $top[0] ) * $t ),
				(int) ( $top[1] + ( $bottom[1] - $top[1] ) * $t ),
				(int) ( $top[2] + ( $bottom[2] - $top[2] ) * $t )
			);
			imageline( $image, 0, $y, $width, $y, $color );
		}

		// Soft spotlights: stacked translucent circles.
		for ( $i = 0; $i < 4; $i++ ) {
			$cx  = (int) ( hexdec( substr( $hash, 4 + $i * 4, 2 ) ) / 255 * $width );
			$cy  = (int) ( hexdec( substr( $hash, 6 + $i * 4, 2 ) ) / 255 * $height * 0.7 );
			$rgb = array( 120 + hexdec( $hash[ 20 + $i ] ) * 8, 60 + hexdec( $hash[ 24 + $i ] ) * 6, 140 + hexdec( $hash[ 28 + $i ] ) * 7 );
			for ( $r = 520; $r > 0; $r -= 40 ) {
				$glow = imagecolorallocatealpha( $image, min( 255, $rgb[0] ), min( 255, $rgb[1] ), min( 255, $rgb[2] ), 122 );
				imagefilledellipse( $image, $cx, $cy, $r, $r, $glow );
			}
		}

		ob_start();
		imagejpeg( $image, null, 82 );
		return (string) ob_get_clean();
	}
}
