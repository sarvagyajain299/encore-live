<?php
/**
 * Single show.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$encore_show = encore_theme_has_core() ? encore_get_show() : null;
	if ( ! $encore_show ) {
		the_content();
		continue;
	}
	$encore_venue = $encore_show->venue;
	?>
	<article class="show" data-show-id="<?php echo esc_attr( (string) $encore_show->id ); ?>">
		<header class="show-hero">
			<div class="show-hero__media">
				<?php
				encore_theme_image(
					$encore_show->id,
					'encore-hero',
					'100vw',
					array(
						'loading'       => 'eager',
						'fetchpriority' => 'high',
						'decoding'      => 'async',
					)
				);
				?>
			</div>
			<div class="container show-hero__content">
				<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
				<?php encore_theme_status_badge( $encore_show ); ?>
				<?php if ( $encore_show->genres ) : ?>
					<p class="eyebrow"><?php echo esc_html( implode( ' · ', $encore_show->genres ) ); ?></p>
				<?php endif; ?>
				<h1 class="show-hero__title"><?php echo esc_html( $encore_show->title ); ?></h1>
				<?php if ( $encore_show->start ) : ?>
					<p class="show-hero__date">
						<time datetime="<?php echo esc_attr( $encore_show->start->format( 'c' ) ); ?>"><?php echo esc_html( encore_format_show_date( $encore_show, 'l j F Y' ) ); ?></time>
					</p>
				<?php endif; ?>
			</div>
		</header>

		<div class="container show-layout">
			<div class="show-layout__main">
				<dl class="facts">
					<?php if ( $encore_venue ) : ?>
						<div class="facts__item">
							<dt><?php esc_html_e( 'Venue', 'encore-theme' ); ?></dt>
							<dd>
								<a href="<?php echo esc_url( $encore_venue['url'] ); ?>"><?php echo esc_html( $encore_venue['title'] ); ?></a>
								<?php if ( $encore_venue['address'] ) : ?>
									<br><span class="muted"><?php echo esc_html( $encore_venue['address'] ); ?></span>
								<?php endif; ?>
							</dd>
						</div>
					<?php endif; ?>
					<?php if ( $encore_show->doors ) : ?>
						<div class="facts__item">
							<dt><?php esc_html_e( 'Doors', 'encore-theme' ); ?></dt>
							<dd><?php echo esc_html( wp_date( 'g:i a', (int) strtotime( '2000-01-01 ' . $encore_show->doors . ' UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></dd>
						</div>
					<?php endif; ?>
					<div class="facts__item">
						<dt><?php esc_html_e( 'Age', 'encore-theme' ); ?></dt>
						<dd><?php echo $encore_show->age ? esc_html( $encore_show->age . '+' ) : esc_html__( 'All ages', 'encore-theme' ); ?></dd>
					</div>
				</dl>

				<?php if ( $encore_show->artists ) : ?>
					<section class="lineup" aria-labelledby="lineup-title">
						<h2 id="lineup-title"><?php esc_html_e( 'Line-up', 'encore-theme' ); ?></h2>
						<ul class="lineup__list">
							<?php foreach ( $encore_show->artists as $encore_i => $encore_artist ) : ?>
								<li>
									<a href="<?php echo esc_url( $encore_artist['url'] ); ?>"><?php echo esc_html( $encore_artist['title'] ); ?></a>
									<?php if ( 0 === $encore_i && count( $encore_show->artists ) > 1 ) : ?>
										<span class="lineup__role"><?php esc_html_e( 'Headliner', 'encore-theme' ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<div class="prose">
					<?php the_content(); ?>
				</div>
			</div>

			<aside class="show-layout__aside" aria-label="<?php esc_attr_e( 'Tickets', 'encore-theme' ); ?>">
				<?php get_template_part( 'template-parts/ticket-cta', null, array( 'show' => $encore_show ) ); ?>
			</aside>
		</div>
	</article>

	<?php get_template_part( 'template-parts/sticky-ticket-bar', null, array( 'show' => $encore_show ) ); ?>
	<?php
endwhile;

get_footer();
