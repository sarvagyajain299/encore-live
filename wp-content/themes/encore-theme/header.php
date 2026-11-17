<?php
/**
 * Site header.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#0c0b11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e( 'Skip to content', 'encore-theme' ); ?></a>

<?php if ( ! encore_theme_elementor_location( 'header' ) ) : ?>
<header class="site-header">
	<div class="container site-header__inner">
		<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_kses_post( wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'full', false, array( 'alt' => get_bloginfo( 'name' ) ) ) ); ?>
			<?php else : ?>
				<span class="wordmark__encore">Encore</span><span class="wordmark__live">Live</span>
			<?php endif; ?>
		</a>

		<button class="icon-button nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
			<span class="nav-toggle__bars" aria-hidden="true"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'encore-theme' ); ?></span>
		</button>

		<nav id="site-nav" class="site-nav" aria-label="<?php esc_attr_e( 'Main', 'encore-theme' ); ?>" data-nav>
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'site-nav__list',
						'depth'          => 1,
					)
				);
			} else {
				?>
				<ul class="site-nav__list">
					<li><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'Shows', 'encore-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_artist' ) ); ?>"><?php esc_html_e( 'Artists', 'encore-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_venue' ) ); ?>"><?php esc_html_e( 'Venues', 'encore-theme' ); ?></a></li>
				</ul>
				<?php
			}
			?>
			<button class="az-toggle" type="button" aria-expanded="false" aria-controls="az-panel" data-az-toggle>
				<?php esc_html_e( 'Shows A–Z', 'encore-theme' ); ?>
				<span class="az-toggle__chevron" aria-hidden="true"></span>
			</button>
			<a class="site-nav__search" href="<?php echo esc_url( home_url( '/?s=' ) ); ?>"><?php esc_html_e( 'Search', 'encore-theme' ); ?></a>
		</nav>
	</div>

	<?php get_template_part( 'template-parts/az-menu' ); ?>
</header>
<?php endif; ?>

<main id="content" class="site-main" tabindex="-1">
