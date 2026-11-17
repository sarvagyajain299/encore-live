<?php
/**
 * "Shows A–Z" mega-menu panel.
 *
 * Hidden by default; main.js toggles it. Without JS the panel stays hidden
 * and the Artists link still leads to the full alphabetical list.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_az = encore_theme_az_index();
if ( ! $encore_az ) {
	return;
}
?>
<div id="az-panel" class="az-panel" hidden data-az-panel>
	<div class="container">
		<p class="az-panel__title" id="az-panel-title"><?php esc_html_e( 'Find a show by artist', 'encore-theme' ); ?></p>
		<nav class="az-panel__letters" aria-labelledby="az-panel-title">
			<?php foreach ( range( 'A', 'Z' ) as $encore_letter ) : ?>
				<?php if ( isset( $encore_az[ $encore_letter ] ) ) : ?>
					<a href="#az-<?php echo esc_attr( $encore_letter ); ?>"><?php echo esc_html( $encore_letter ); ?></a>
				<?php else : ?>
					<span aria-hidden="true"><?php echo esc_html( $encore_letter ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
		<div class="az-panel__groups">
			<?php foreach ( $encore_az as $encore_letter => $encore_artists ) : ?>
				<section class="az-group" id="az-<?php echo esc_attr( $encore_letter ); ?>" aria-label="<?php echo esc_attr( $encore_letter ); ?>">
					<h2 class="az-group__letter" aria-hidden="true"><?php echo esc_html( $encore_letter ); ?></h2>
					<ul>
						<?php foreach ( $encore_artists as $encore_artist ) : ?>
							<li><a href="<?php echo esc_url( $encore_artist['url'] ); ?>"><?php echo esc_html( $encore_artist['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</div>
