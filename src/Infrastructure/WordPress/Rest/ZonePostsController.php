<?php
/**
 * The zoninator/v1 routes for the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Reads and sets a zone's posts, and adds and removes one: /zones/{zone_id}/posts
 * and /zones/{zone_id}/posts/{post_id}.
 *
 * @since 1.0.0
 * @internal Use the REST API itself, not this class.
 */
final class ZonePostsController extends WP_REST_Controller {

	use RegistersRoutes;

	/**
	 * Constructor.
	 *
	 * @param Feed               $feed    The feed's data.
	 * @param GuardedZoneCurator $curator Changes the posts in a zone for the current user.
	 * @param Permissions $permissions Says who may call each route.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly Feed $feed,
		private readonly GuardedZoneCurator $curator,
		private readonly Permissions $permissions,
		private readonly Errors $errors,
	) {
	}

	/**
	 * Register the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::$rest_namespace,
			'/zones/(?P<zone_id>[\d]+)/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_zone_posts' ),
					// The JSON feed lists the same posts to anyone, so this does too.
					'permission_callback' => '__return_true',
					'args'                => $this->get_zone_id_param(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_zone_posts' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => $this->get_zone_posts_params(),
				),
			)
		);

		register_rest_route(
			self::$rest_namespace,
			'/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_zone_post' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => $this->get_zone_post_item_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_zone_post' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => $this->get_zone_post_item_params(),
				),
			)
		);
	}

	/**
	 * Get zone posts
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_zone_posts( $request ): WP_REST_Response|WP_Error {
		$results = $this->feed->get( (int) $request['zone_id'] );

		if ( null === $results ) {
			return $this->errors->zone_not_found();
		}

		if ( is_wp_error( $results ) ) {
			return $this->errors->zone_feed_error( $results->get_error_message() );
		}

		return rest_ensure_response( $results );
	}

	/**
	 * Sets the posts for a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone_posts( $request ): WP_REST_Response|WP_Error {
		$zone_id = (int) $request['zone_id'];

		try {
			$this->curator->set_posts( get_current_user_id(), $zone_id, array_map( 'absint', (array) $request['post_ids'] ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Add a post to the end of a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_zone_post( $request ): WP_REST_Response|WP_Error {
		$zone_id = (int) $request['zone_id'];
		$post_id = (int) $request['post_id'];

		try {
			$this->curator->add_posts( get_current_user_id(), $zone_id, array( $post_id ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return new WP_REST_Response( array( 'success' => true ), 201 );
	}

	/**
	 * Remove a post from a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function remove_zone_post( $request ): WP_REST_Response|WP_Error {
		$zone_id = (int) $request['zone_id'];

		try {
			$this->curator->remove_posts( get_current_user_id(), $zone_id, array( (int) $request['post_id'] ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Whether a value is a list of post IDs. Core's own check for an array arg
	 * also takes a comma-separated string, which this API never has.
	 *
	 * @param mixed $items The post_ids param.
	 * @return bool
	 */
	public function is_list_of_ids( $items ): bool {
		return is_array( $items ) && count( $items ) === count( array_filter( $items, 'is_numeric' ) );
	}

	private function get_zone_posts_params(): array {
		$zone_params = $this->get_zone_id_param();
		return array_merge(
			array(
				'post_ids' => array(
					'type'              => 'array',
					'validate_callback' => array( $this, 'is_list_of_ids' ),
					'required'          => true,
					'items'             => array( 'type' => 'integer' ),
				),
			),
			$zone_params
		);
	}

	/**
	 * Args for a single post in a zone.
	 *
	 * @return array
	 */
	private function get_zone_post_item_params(): array {
		return array_merge(
			$this->get_zone_id_param(),
			array(
				'post_id' => array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'required'          => true,
				),
			)
		);
	}
}
