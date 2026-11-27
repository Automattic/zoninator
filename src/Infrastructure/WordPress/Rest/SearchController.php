<?php
/**
 * The zoninator/v1 route that searches for posts.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Infrastructure\WordPress\Search;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Searches for posts to add to a zone: /search.
 *
 * @since 1.0.0
 * @internal Use the REST API itself, not this class.
 */
final class SearchController extends WP_REST_Controller {

	use RegistersRoutes;

	/**
	 * Constructor.
	 *
	 * @param Search      $search      Finds posts to offer for a zone.
	 * @param Permissions $permissions Says who may call each route.
	 */
	public function __construct(
		private readonly Search $search,
		private readonly Permissions $permissions,
	) {
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::$rest_namespace,
			'/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_posts' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => $this->get_search_params(),
				),
			)
		);
	}

	/**
	 * Search for posts to add to a zone, or list the latest content when there's no term.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function search_posts( $request ): WP_REST_Response|WP_Error {
		return rest_ensure_response(
			$this->search->find_posts(
				(string) $request['term'],
				(int) $request['cat'],
				(string) $request['date'],
				(array) $request['exclude'],
				(int) $request['limit'],
				(int) $request['zone_id']
			)
		);
	}

	/**
	 * Args for the post search. They keep the names the old admin-ajax action
	 * used, so the Zones screen's filters, and any a site adds, keep working.
	 *
	 * @return array
	 */
	private function get_search_params(): array {
		return array(
			'term'    => array(
				'type'    => 'string',
				'default' => '',
			),
			// Not typed as an integer: a site may filter the category dropdown to send
			// something else, which the search ignores rather than rejects.
			'cat'     => array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'date'    => array(
				'type'    => 'string',
				'default' => '',
			),
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A route arg, not a query arg. get_posts_excluding() drops these in PHP.
			'exclude' => array(
				'type'    => 'array',
				'items'   => array( 'type' => 'integer' ),
				'default' => array(),
			),
			'limit'   => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			// The zone being edited, for sites that focus the search on it. Not checked
			// against the zones: it only reaches the query args filters.
			'zone_id' => array(
				'type'    => 'integer',
				'minimum' => 0,
				'default' => 0,
			),
		);
	}
}
