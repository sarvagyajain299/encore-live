<?php
/**
 * Filters on the /shows/ archive.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Shows;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use EncoreCore\Support\Dates;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Adds ?city=, ?genre=, ?month= and ?when= to /shows/ without a plugin.
 *
 * 1. query_vars: whitelist the parameters so WordPress parses them into the
 *    main query (unknown parameters are ignored, a security feature).
 * 2. pre_get_posts: before the SQL runs, translate them into meta and tax
 *    queries with ShowQueryArgs, the same builder the REST API uses.
 */
final class ArchiveFilters implements Service {

	public const VARS = array(
		'city'  => 'show_city',
		'genre' => 'show_genre',
		'month' => 'show_month',
		'when'  => 'show_when',
	);

	/**
	 * Create with the shared builder.
	 *
	 * @param ShowQueryArgs $query_args Builder.
	 */
	public function __construct( private readonly ShowQueryArgs $query_args = new ShowQueryArgs() ) {}

	/**
	 * Hook in.
	 */
	public function register(): void {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
	}

	/**
	 * Whitelist our query vars.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function query_vars( array $vars ): array {
		return array_merge( $vars, array_values( self::VARS ) );
	}

	/**
	 * Apply filters to the main shows archive query.
	 *
	 * @param WP_Query $query Query about to run.
	 */
	public function apply( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Artists list alphabetically, like the A–Z menu.
		if ( $query->is_post_type_archive( PostTypes::ARTIST ) ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
			$query->set( 'posts_per_page', 24 );
			return;
		}

		if ( ! $query->is_post_type_archive( PostTypes::SHOW ) ) {
			return;
		}

		$filters = array(
			'paginate' => true,
			'limit'    => 12,
			'page'     => max( 1, (int) $query->get( 'paged' ) ),
		);
		foreach ( self::VARS as $filter => $var ) {
			$filters[ $filter ] = (string) $query->get( $var );
		}

		$args = $this->query_args->build( $filters, Dates::now() );
		foreach ( array( 'posts_per_page', 'meta_key', 'orderby', 'order', 'meta_query', 'tax_query' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$query->set( $key, $args[ $key ] );
			}
		}
	}

	/**
	 * Current filter values from the request, for the filter form.
	 *
	 * @return array<string, string>
	 */
	public static function current(): array {
		$values = array();
		foreach ( self::VARS as $filter => $var ) {
			$values[ $filter ] = sanitize_text_field( (string) get_query_var( $var ) );
		}
		return $values;
	}

	/**
	 * Is any filter active? Filtered archives are noindexed in Phase 6.
	 */
	public static function is_filtered(): bool {
		return array() !== array_filter( self::current() );
	}
}
