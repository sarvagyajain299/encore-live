<?php
/**
 * Single venue with its upcoming shows.
 *
 * The map is a plain link, not an embedded iframe: an embedded map adds
 * hundreds of KB of third-party JS to every venue page (see Phase 7).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$encore_id       = get_the_ID();
	$encore_address  = (string) get_post_meta( $encore_id, '_encore_address', true );
	$encore_capacity = (int) get_post_meta( $encore_id, '_encore_capacity', true );
	$encore_map      = (string) get_post_meta( $encore_id, '_encore_map_url', true );
	$encore_cities   = get_the_terms( $encore_id, 'encore_city' );
	?>
	<article class="container section venue">
		<?php get_template_part( 'template-parts/breadcrumbs' ); ?>
		<header class="page-header">
			<?php if ( is_array( $encore_cities ) && $encore_cities ) : ?>
				<p class="eyebrow"><?php echo esc_html( $encore_cities[0]->name ); ?></p>
			<?php endif; ?>
			<h1 class="page-title"><?php the_title(); ?></h1>
		</header>

		<dl class="facts">
			<?php if ( $encore_address ) : ?>
				<div class="facts__item">
					<dt><?php esc_html_e( 'Address', 'encore-theme' ); ?></dt>
					<dd>
						<?php echo esc_html( $encore_address ); ?>
						<?php if ( $encore_map ) : ?>
							<br><a href="<?php echo esc_url( $encore_map ); ?>" rel="noopener" target="_blank"><?php esc_html_e( 'Open map', 'encore-theme' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'encore-theme' ); ?></span></a>
						<?php endif; ?>
					</dd>
				</div>
			<?php endif; ?>
			<?php if ( $encore_capacity ) : ?>
				<div class="facts__item">
					<dt><?php esc_html_e( 'Capacity', 'encore-theme' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $encore_capacity ) ); ?></dd>
				</div>
			<?php endif; ?>
		</dl>

		<div class="prose"><?php the_content(); ?></div>

		<?php if ( encore_theme_has_core() ) : ?>
			<?php
			$encore_shows = encore_query_shows(
				array(
					'venue' => $encore_id,
					'limit' => 12,
				)
			);
			?>
			<section class="section" aria-labelledby="venue-upcoming">
				<h2 id="venue-upcoming" class="section__title"><?php esc_html_e( 'Upcoming at this venue', 'encore-theme' ); ?></h2>
				<?php if ( $encore_shows->have_posts() ) : ?>
					<div class="card-grid">
						<?php
						foreach ( $encore_shows->posts as $encore_i => $encore_post ) {
							get_template_part(
								'template-parts/show-card',
								null,
								array(
									'show'  => encore_get_show( $encore_post ),
									'list'  => 'venue_upcoming',
									'index' => $encore_i,
								)
							);
						}
						?>
					</div>
				<?php else : ?>
					<p class="muted"><?php esc_html_e( 'Nothing scheduled here right now.', 'encore-theme' ); ?></p>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
