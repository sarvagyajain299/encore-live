<?php
/**
 * Home page. front-page.php wins over home.php and page.php in the template
 * hierarchy, whatever "Your homepage displays" is set to.
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! encore_theme_has_core() ) {
	echo '<div class="container section"><p>' . esc_html__( 'Activate the Encore Core plugin to show events.', 'encore-theme' ) . '</p></div>';
	get_footer();
	return;
}

$encore_upcoming = encore_query_shows( array( 'limit' => 7 ) );
$encore_shows    = array_values( array_filter( array_map( 'encore_get_show', $encore_upcoming->posts ) ) );

// Hero = the next show you can actually buy.
$encore_hero = null;
foreach ( $encore_shows as $encore_candidate ) {
	if ( $encore_candidate->is_on_sale() ) {
		$encore_hero = $encore_candidate;
		break;
	}
}
?>

<?php if ( $encore_hero ) : ?>
	<section class="hero" aria-labelledby="hero-title">
		<div class="hero__media">
			<?php
			// The LCP element: load eagerly, fetch with high priority, never lazy.
			encore_theme_image(
				$encore_hero->id,
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
		<div class="container hero__content">
			<p class="eyebrow"><?php esc_html_e( 'Next up', 'encore-theme' ); ?></p>
			<h1 id="hero-title" class="hero__title"><?php echo esc_html( $encore_hero->title ); ?></h1>
			<p class="hero__meta">
				<?php echo esc_html( encore_format_show_date( $encore_hero, 'l j F' ) ); ?>
				<?php if ( $encore_hero->venue ) : ?>
					<span aria-hidden="true">·</span> <?php echo esc_html( $encore_hero->venue['title'] ); ?>
				<?php endif; ?>
			</p>
			<div class="hero__actions">
				<a class="button button--primary button--large" href="<?php echo esc_url( $encore_hero->url . '#tickets' ); ?>">
					<?php esc_html_e( 'Get tickets', 'encore-theme' ); ?>
					<?php if ( null !== $encore_hero->min_price ) : ?>
						<span class="button__sub"><?php echo esc_html( encore_theme_price_from( $encore_hero ) ); ?></span>
					<?php endif; ?>
				</a>
				<a class="button button--ghost button--large" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'All shows', 'encore-theme' ); ?></a>
			</div>
		</div>
	</section>
<?php else : ?>
	<section class="container section">
		<h1><?php bloginfo( 'name' ); ?></h1>
	</section>
<?php endif; ?>

<section class="container section" aria-labelledby="upcoming-title">
	<div class="section__head">
		<h2 id="upcoming-title" class="section__title"><?php esc_html_e( 'On sale now', 'encore-theme' ); ?></h2>
		<a class="section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'See all shows', 'encore-theme' ); ?></a>
	</div>
	<div class="card-grid">
		<?php
		$encore_index = 0;
		foreach ( $encore_shows as $encore_show ) {
			if ( $encore_hero && $encore_show->id === $encore_hero->id ) {
				continue;
			}
			get_template_part(
				'template-parts/show-card',
				null,
				array(
					'show'  => $encore_show,
					'list'  => 'home_upcoming',
					'index' => $encore_index++,
				)
			);
		}
		?>
	</div>
</section>

<?php
$encore_genre_terms = get_terms(
	array(
		'taxonomy'   => 'encore_genre',
		'hide_empty' => true,
	)
);
if ( is_array( $encore_genre_terms ) && $encore_genre_terms ) :
	?>
	<section class="container section" aria-labelledby="genres-title">
		<h2 id="genres-title" class="section__title"><?php esc_html_e( 'Pick your night', 'encore-theme' ); ?></h2>
		<ul class="chip-list">
			<?php foreach ( $encore_genre_terms as $encore_term ) : ?>
				<li><a class="chip" href="<?php echo esc_url( add_query_arg( 'show_genre', $encore_term->slug, (string) get_post_type_archive_link( 'encore_show' ) ) ); ?>"><?php echo esc_html( $encore_term->name ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php
$encore_artists = get_posts(
	array(
		'post_type'      => 'encore_artist',
		'posts_per_page' => 8,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	)
);
if ( $encore_artists ) :
	?>
	<section class="container section" aria-labelledby="artists-title">
		<div class="section__head">
			<h2 id="artists-title" class="section__title"><?php esc_html_e( 'Artists', 'encore-theme' ); ?></h2>
			<a class="section__more" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_artist' ) ); ?>"><?php esc_html_e( 'All artists', 'encore-theme' ); ?></a>
		</div>
		<div class="card-grid card-grid--small">
			<?php
			foreach ( $encore_artists as $encore_artist ) {
				get_template_part( 'template-parts/artist-card', null, array( 'post' => $encore_artist ) );
			}
			?>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
