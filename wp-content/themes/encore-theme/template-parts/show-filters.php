<?php
/**
 * Filter form for the shows archive.
 *
 * A plain GET form: works without JavaScript, every filtered view has a
 * shareable URL, and the browser back button just works. No auto-submit on
 * change (WCAG 3.2.2: changing a select must not trigger navigation by itself).
 *
 * @package EncoreTheme
 */

defined( 'ABSPATH' ) || exit;

$encore_current = encore_archive_filters();
$encore_cities  = get_terms(
	array(
		'taxonomy'   => 'encore_city',
		'hide_empty' => true,
	)
);
$encore_genres  = get_terms(
	array(
		'taxonomy'   => 'encore_genre',
		'hide_empty' => true,
	)
);
$encore_month   = new DateTimeImmutable( 'first day of this month', wp_timezone() );
?>
<form class="filters" method="get" action="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>" role="search" aria-label="<?php esc_attr_e( 'Filter shows', 'encore-theme' ); ?>">
	<div class="filters__field">
		<label for="filter-city"><?php esc_html_e( 'City', 'encore-theme' ); ?></label>
		<select id="filter-city" name="show_city">
			<option value=""><?php esc_html_e( 'All cities', 'encore-theme' ); ?></option>
			<?php foreach ( is_array( $encore_cities ) ? $encore_cities : array() as $encore_term ) : ?>
				<option value="<?php echo esc_attr( $encore_term->slug ); ?>" <?php selected( $encore_current['city'], $encore_term->slug ); ?>><?php echo esc_html( $encore_term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="filters__field">
		<label for="filter-genre"><?php esc_html_e( 'Genre', 'encore-theme' ); ?></label>
		<select id="filter-genre" name="show_genre">
			<option value=""><?php esc_html_e( 'All genres', 'encore-theme' ); ?></option>
			<?php foreach ( is_array( $encore_genres ) ? $encore_genres : array() as $encore_term ) : ?>
				<option value="<?php echo esc_attr( $encore_term->slug ); ?>" <?php selected( $encore_current['genre'], $encore_term->slug ); ?>><?php echo esc_html( $encore_term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="filters__field">
		<label for="filter-month"><?php esc_html_e( 'Month', 'encore-theme' ); ?></label>
		<select id="filter-month" name="show_month">
			<option value=""><?php esc_html_e( 'Any month', 'encore-theme' ); ?></option>
			<?php for ( $encore_i = 0; $encore_i < 12; $encore_i++ ) : ?>
				<?php $encore_m = $encore_month->modify( "+{$encore_i} months" ); ?>
				<option value="<?php echo esc_attr( $encore_m->format( 'Y-m' ) ); ?>" <?php selected( $encore_current['month'], $encore_m->format( 'Y-m' ) ); ?>><?php echo esc_html( wp_date( 'F Y', $encore_m->getTimestamp() ) ); ?></option>
			<?php endfor; ?>
		</select>
	</div>

	<div class="filters__field">
		<label for="filter-when"><?php esc_html_e( 'When', 'encore-theme' ); ?></label>
		<select id="filter-when" name="show_when">
			<option value=""><?php esc_html_e( 'Upcoming', 'encore-theme' ); ?></option>
			<option value="past" <?php selected( $encore_current['when'], 'past' ); ?>><?php esc_html_e( 'Past shows', 'encore-theme' ); ?></option>
		</select>
	</div>

	<div class="filters__actions">
		<button class="button button--primary" type="submit"><?php esc_html_e( 'Apply filters', 'encore-theme' ); ?></button>
		<?php if ( array_filter( $encore_current ) ) : ?>
			<a class="button button--ghost" href="<?php echo esc_url( (string) get_post_type_archive_link( 'encore_show' ) ); ?>"><?php esc_html_e( 'Clear', 'encore-theme' ); ?></a>
		<?php endif; ?>
	</div>
</form>
