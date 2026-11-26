<?php
/**
 * The zoninator/v1 routes for zones.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

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

	use RegistersRoutes;

	/**
	 * Constructor.
	 *
	 * @param Zones              $zones   Zones and the posts in them.
	 * @param GuardedZoneManager $manager Creates, changes and deletes zones for the current user.
	 * @param Permissions $permissions Says who may call each route.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly Zones $zones,
		private readonly GuardedZoneManager $manager,
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
	public function get_zones( $request ): WP_REST_Response|WP_Error {
		$results = $this->zones->get_all();

		// Zones::get_all() returns false, not a WP_Error, when the zones can't be read.
		if ( ! is_array( $results ) ) {
			return $this->errors->zones_read_error();
		}

		$zones = array_map( array( $this->zones, 'get_data' ), $results );

		return rest_ensure_response( $zones );
	}

	/**
	 * Create a Zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_zone( $request ): WP_REST_Response|WP_Error {
		$name        = (string) $request['name'];
		$slug        = empty( $request['slug'] ) ? $name : (string) $request['slug'];
		$description = (string) $request['description'];

		try {
			$zone = $this->manager->create( get_current_user_id(), (string) $slug, (string) $name, array( 'description' => $description ) );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, 0 );
		}

		return new WP_REST_Response( $this->zones->get_data( $this->zones->get( (int) $zone->get_id() ) ), 201 );
	}

	/**
	 * Update zone details
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone( $request ): WP_REST_Response|WP_Error {
		$zone_id = absint( $request['zone_id'] );
		$name    = (string) $request['name'];
		$slug    = (string) $request['slug'];

		$update_params = array();

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

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Delete a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_zone( $request ): WP_REST_Response|WP_Error {
		$zone_id = absint( $request['zone_id'] );

		try {
			$this->manager->remove( get_current_user_id(), $zone_id );
		} catch ( ZoninatorExceptionInterface $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	private function get_create_params(): array {
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

	private function get_update_params(): array {
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
