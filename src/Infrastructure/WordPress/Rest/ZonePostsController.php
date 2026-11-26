<?php
/**
 * The zoninator/v1 routes for the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
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

	use RespondsToRequests;

	/**
	 * The feed's data, which the read route returns.
	 *
	 * @var Feed
	 */
	private $feed;

	/**
	 * Changes the posts in a zone for the current user.
	 *
	 * @var GuardedZoneCurator
	 */
	private $curator;

	/**
	 * Says who may call each route.
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * Constructor.
	 *
	 * @param Feed               $feed    The feed's data.
	 * @param GuardedZoneCurator $curator Changes the posts in a zone for the current user.
	 * @param Permissions $permissions Says who may call each route.
	 */
	public function __construct( Feed $feed, GuardedZoneCurator $curator, Permissions $permissions ) {
		$this->feed        = $feed;
		$this->curator     = $curator;
		$this->permissions = $permissions;
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::$rest_namespace,
			'/zones/(?P<zone_id>[\d]+)/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_zone_posts' ),
					'permission_callback' => array( $this->permissions, 'read_zone_posts' ),
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
	public function get_zone_posts( $request ) {
		$results = $this->feed->get( $this->get_param( $request, 'zone_id', 0, 'absint' ) );

		if ( is_wp_error( $results ) ) {
			return 'invalid-zone-supplied' === $results->get_error_code() ? $this->zone_not_found() : $this->bad_request( ErrorCodes::ZONE_FEED_ERROR, $results->get_error_message() );
		}

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Sets the posts for a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone_posts( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );

		try {
			$this->curator->set_posts( get_current_user_id(), $zone_id, array_map( 'absint', (array) $this->get_param( $request, 'post_ids', array() ) ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Add a post to the end of a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_zone_post( $request ) {
		$zone_id = (int) $request['zone_id'];
		$post_id = (int) $request['post_id'];

		try {
			$this->curator->add_posts( get_current_user_id(), $zone_id, array( $post_id ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->created( array( 'success' => true ) );
	}

	/**
	 * Remove a post from a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function remove_zone_post( $request ) {
		$zone_id = (int) $request['zone_id'];

		try {
			$this->curator->remove_posts( get_current_user_id(), $zone_id, array( (int) $request['post_id'] ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	public function is_numeric_array( $items ) {
		return is_array( $items ) && count( $items ) === count( array_filter( $items, 'is_numeric' ) );
	}

	private function get_zone_posts_params() {
		$zone_params = $this->get_zone_id_param();
		return array_merge(
			array(
				'post_ids' => array(
					'type'              => 'array',
					'validate_callback' => array( $this, 'is_numeric_array' ),
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
	private function get_zone_post_item_params() {
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
