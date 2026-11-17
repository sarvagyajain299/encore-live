<?php
/**
 * GET /wp-json/encore/v1/shows and /shows/{id}.
 *
 * @package EncoreCore
 */

namespace EncoreCore\Rest;

use EncoreCore\Content\PostTypes;
use EncoreCore\Contracts\Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * A small read-only API for partners (e.g. a ticket aggregator or a mobile app).
 *
 * The `args` schema does the input work before our callback runs. WordPress
 * type-checks, applies defaults, validates enums, ranges and patterns, then
 * sanitizes. Invalid requests get a 400 with a precise message, and our
 * callback only ever sees clean values.
 */
final class ShowsController implements Service {

	public const NAMESPACE = 'encore/v1';

	/**
	 * Create with the serializer.
	 *
	 * @param ShowSerializer $serializer Response shaper.
	 */
	public function __construct( private readonly ShowSerializer $serializer = new ShowSerializer() ) {}

	/**
	 * Hook route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/shows',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'index' ),
				'permission_callback' => array( $this, 'public_read' ),
				'args'                => $this->collection_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/shows/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'show' ),
				'permission_callback' => array( $this, 'public_read' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'minimum'           => 1,
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Published shows are public information, so anyone may read them.
	 *
	 * An explicit permission_callback is still required: since WP 5.5 a route
	 * without one triggers a _doing_it_wrong notice. Returning true here is a
	 * documented decision, not an omission.
	 */
	public function public_read(): bool {
		return true;
	}

	/**
	 * List shows with filters and pagination headers.
	 *
	 * @param WP_REST_Request $request Request (already validated by args).
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		// TODO(sarvagya): support ?artist=<id> (e.g. a partner app showing one comedian's tour).
		// Hint: add an 'artist' entry to collection_args() (type integer, minimum 1) and pass
		// 'artist' => $request['artist'] below. ShowQueryArgs already understands 'artist'.
		// Then add a check to scripts/smoke-api.ps1: /shows?artist=<Nova Saleh's ID> returns 2.
		$query = encore_query_shows(
			array(
				'city'     => $request['city'],
				'genre'    => $request['genre'],
				'from'     => $request['from'],
				'to'       => $request['to'],
				'when'     => $request['when'],
				'page'     => $request['page'],
				'limit'    => $request['per_page'],
				'paginate' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$show = encore_get_show( $post );
			if ( $show ) {
				$items[] = $this->serialize( $show );
			}
		}

		$response = new WP_REST_Response( $items );
		// Same pagination headers as core's /wp/v2 endpoints, so existing clients just work.
		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );
		return $response;
	}

	/**
	 * One show.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post = get_post( (int) $request['id'] );
		$show = ( $post && PostTypes::SHOW === $post->post_type && 'publish' === $post->post_status ) ? encore_get_show( $post ) : null;

		if ( ! $show ) {
			return new WP_Error( 'encore_show_not_found', __( 'Show not found.', 'encore-core' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( $this->serialize( $show ) );
	}

	/**
	 * Serialize with site context (currency, image URL).
	 *
	 * @param \EncoreCore\Shows\Show $show Show.
	 * @return array<string, mixed>
	 */
	private function serialize( $show ): array {
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'AED';
		$image    = (string) get_the_post_thumbnail_url( $show->id, 'encore-card' );
		return $this->serializer->to_array( $show, $currency, $image );
	}

	/**
	 * Query parameters for /shows.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function collection_args(): array {
		/*
		 * Gotcha found in testing: once an arg has its own sanitize_callback,
		 * WordPress no longer runs schema validation (type/pattern/enum) for it
		 * unless validate_callback is set too. Without this line, ?from=01-12-2026
		 * was silently accepted. rest_validate_request_arg = "validate against
		 * this arg's schema".
		 */
		$date = array(
			'type'              => 'string',
			'pattern'           => '^\d{4}-\d{2}-\d{2}$',
			'description'       => __( 'Date in YYYY-MM-DD format (inclusive).', 'encore-core' ),
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
		);
		$slug = array(
			'type'              => 'string',
			'pattern'           => '^[a-z0-9-]*$',
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_title',
			'default'           => '',
		);

		return array(
			'city'     => $slug + array( 'description' => __( 'City slug, e.g. dubai.', 'encore-core' ) ),
			'genre'    => $slug + array( 'description' => __( 'Genre slug, e.g. comedy.', 'encore-core' ) ),
			// No default: with validate_callback set, a '' default would itself fail the pattern.
			'from'     => $date,
			'to'       => $date,
			'when'     => array(
				'type'    => 'string',
				'enum'    => array( 'upcoming', 'past', 'all' ),
				'default' => 'upcoming',
			),
			'page'     => array(
				'type'    => 'integer',
				'minimum' => 1,
				'default' => 1,
			),
			'per_page' => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 50,
				'default' => 10,
			),
		);
	}
}
