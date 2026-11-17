<?php
/**
 * Site footer.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<?php if ( ! encore_theme_elementor_location( 'footer' ) ) : ?>
<footer class="site-footer">
	<div class="container site-footer__inner">
		<div class="site-footer__brand">
			<p class="wordmark wordmark--small"><span class="wordmark__encore">Encore</span><span class="wordmark__live">Live</span></p>
			<p><?php esc_html_e( 'Stand-up comedy and live music across Dubai, Abu Dhabi and Sharjah.', 'encore-theme' ); ?></p>
		</div>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav aria-label="<?php esc_attr_e( 'Footer', 'encore-theme' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'site-footer__links',
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php
		// TODO(sarvagya): add a "Back to top" link that targets #content (the <main> landmark).
		// Hint: <a href="#content">…</a> with esc_html_e() and the 'encore-theme' text domain.
		// Main has tabindex="-1", so keyboard focus moves there too, not just the scroll position.
		?>
		<p class="site-footer__legal">
			<?php
			printf(
				/* translators: %s: current year. */
				esc_html__( '© %s Encore Live. A fictional promoter built as a portfolio project; all artists and venues are invented.', 'encore-theme' ),
				esc_html( wp_date( 'Y' ) )
			);
			?>
		</p>
	</div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
