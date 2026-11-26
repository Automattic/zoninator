<?php
/**
 * The zoninator/v1 routes for zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Lists and creates zones, and changes and deletes one: /zones and /zones/{zone_id}.
 *
 * @since 1.0.0
 * @internal Use the REST API itself, not this class.
 */
final class ZonesController extends WP_REST_Controller {

	use RespondsToRequests;

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Creates, changes and deletes zones for the current user.
	 *
	 * @var GuardedZoneManager
	 */
	private $manager;

	/**
	 * Says who may call each route.
	 *
	 * @var Permissions
	 */
	private $permissions;

	/**
	 * Builds the error for each thing that can go wrong.
	 *
	 * @var Errors
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param Zones              $zones   Zones and the posts in them.
	 * @param GuardedZoneManager $manager Creates, changes and deletes zones for the current user.
	 * @param Permissions $permissions Says who may call each route.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct( Zones $zones, GuardedZoneManager $manager, Permissions $permissions, Errors $errors ) {
		$this->zones       = $zones;
		$this->manager     = $manager;
		$this->permissions = $permissions;
		$this->errors      = $errors;
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::$rest_namespace,
			'/zones',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_zones' ),
					'permission_callback' => array( $this->permissions, 'list_zones' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_zone' ),
					'permission_callback' => array( $this->permissions, 'create_zone' ),
					'args'                => $this->get_create_params(),
				),
			)
		);

		register_rest_route(
			self::$rest_namespace,
			'/zones/(?P<zone_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_zone' ),
					'permission_callback' => array( $this->permissions, 'edit_zone' ),
					'args'                => $this->get_update_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_zone' ),
					'permission_callback' => array( $this->permissions, 'edit_zone' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Get the list of all zones
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_zones( $request ) {
		$results = $this->zones->get_all();

		// Zones::get_all() returns false, not a WP_Error, when the zones can't be read.
		if ( ! is_array( $results ) ) {
			return $this->errors->zones_read_error();
		}

		$zones = array_map( array( $this->zones, 'get_data' ), $results );

		return $this->ok( $zones );
	}

	/**
	 * Create a Zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_zone( $request ) {
		$name        = (string) $request['name'];
		$slug        = empty( $request['slug'] ) ? $name : (string) $request['slug'];
		$description = (string) $request['description'];

		try {
			$zone = $this->manager->create( get_current_user_id(), (string) $slug, (string) $name, array( 'description' => $description ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, 0 );
		}

		return $this->created( $this->zones->get_data( $this->zones->get( (int) $zone->get_id() ) ) );
	}

	/**
	 * Update zone details
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone( $request ) {
		$zone_id = absint( $request['zone_id'] );
		$name    = (string) $request['name'];
		$slug    = (string) $request['slug'];

		$zone          = $this->zones->get( $zone_id );
		$update_params = array();

		if ( ! $zone ) {
			return $this->errors->zone_not_found();
		}

		if ( $name ) {
			$update_params['name'] = $name;
		}

		if ( $slug ) {
			$update_params['slug'] = $slug;
		}

		// An empty description clears it. Leave it out to keep the current one.
		if ( $request->has_param( 'description' ) ) {
			$update_params['details'] = array( 'description' => (string) $request->get_param( 'description' ) );
		}

		try {
			$this->manager->update( get_current_user_id(), $zone_id, $update_params );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Delete a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_zone( $request ) {
		$zone_id = absint( $request['zone_id'] );

		$zone = $this->zones->get( $zone_id );

		if ( ! $zone ) {
			return $this->errors->zone_not_found();
		}

		try {
			$this->manager->remove( get_current_user_id(), $zone_id );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	private function get_create_params() {
		return array(
			'name'        => array(
				'type'     => 'string',
				'default'  => '',
				'required' => false,
			),
			'slug'        => array(
				'type'     => 'string',
				'default'  => '',
				'required' => false,
			),
			'description' => array(
				'type'     => 'string',
				'default'  => '',
				'required' => false,
			),
		);
	}

	private function get_update_params() {
		return array(
			'name'        => array(
				'type'     => 'string',
				'required' => false,
			),
			'slug'        => array(
				'type'     => 'string',
				'required' => false,
			),
			'description' => array(
				'type'     => 'string',
				'required' => false,
			),
		);
	}
}
