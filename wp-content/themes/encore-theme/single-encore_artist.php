<?php
/**
 * Single artist with their upcoming and past shows.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$encore_id     = get_the_ID();
	$encore_bio    = (string) get_post_meta( $encore_id, '_encore_bio', true );
	$encore_social = get_post_meta( $encore_id, '_encore_social', true );
	$encore_genres = get_the_terms( $encore_id, 'encore_genre' );
	?>
	<article class="container section artist">
		<?php get_template_part( 'template-parts/breadcrumbs' ); ?>

		<header class="artist__header">
			<div class="artist__photo">
				<?php encore_theme_image( $encore_id, 'encore-card', '(min-width: 900px) 360px, 100vw', array( 'fetchpriority' => 'high' ) ); ?>
			</div>
			<div class="artist__intro">
				<?php if ( is_array( $encore_genres ) && $encore_genres ) : ?>
					<p class="eyebrow"><?php echo esc_html( implode( ' · ', wp_list_pluck( $encore_genres, 'name' ) ) ); ?></p>
				<?php endif; ?>
				<h1 class="artist__name"><?php the_title(); ?></h1>
				<?php if ( $encore_bio ) : ?>
					<p class="artist__bio"><?php echo esc_html( $encore_bio ); ?></p>
				<?php endif; ?>
				<?php if ( is_array( $encore_social ) && $encore_social ) : ?>
					<ul class="social-links">
						<?php foreach ( $encore_social as $encore_network => $encore_url ) : ?>
							<li><a href="<?php echo esc_url( $encore_url ); ?>" rel="noopener" target="_blank"><?php echo esc_html( ucfirst( $encore_network ) ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'encore-theme' ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</header>

		<div class="prose"><?php the_content(); ?></div>

		<?php if ( encore_theme_has_core() ) : ?>
			<?php
			$encore_upcoming = encore_query_shows(
				array(
					'artist' => $encore_id,
					'limit'  => 12,
				)
			);
			?>
			<section class="section" aria-labelledby="artist-upcoming">
				<h2 id="artist-upcoming" class="section__title"><?php esc_html_e( 'Upcoming shows', 'encore-theme' ); ?></h2>
				<?php if ( $encore_upcoming->have_posts() ) : ?>
					<div class="card-grid">
						<?php
						foreach ( $encore_upcoming->posts as $encore_i => $encore_post ) {
							get_template_part(
								'template-parts/show-card',
								null,
								array(
									'show'  => encore_get_show( $encore_post ),
									'list'  => 'artist_upcoming',
									'index' => $encore_i,
								)
							);
						}
						?>
					</div>
				<?php else : ?>
					<p class="muted"><?php esc_html_e( 'No upcoming dates announced. Check back soon.', 'encore-theme' ); ?></p>
				<?php endif; ?>
			</section>

			<?php
			$encore_past = encore_query_shows(
				array(
					'artist' => $encore_id,
					'when'   => 'past',
					'limit'  => 6,
				)
			);
			if ( $encore_past->have_posts() ) :
				?>
				<section class="section" aria-labelledby="artist-past">
					<h2 id="artist-past" class="section__title"><?php esc_html_e( 'Past shows', 'encore-theme' ); ?></h2>
					<ul class="past-list">
						<?php foreach ( $encore_past->posts as $encore_post ) : ?>
							<?php $encore_show = encore_get_show( $encore_post ); ?>
							<li><?php echo esc_html( $encore_show ? encore_format_show_date( $encore_show ) . ' — ' . $encore_show->title : get_the_title( $encore_post ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
