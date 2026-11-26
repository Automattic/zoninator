<?php
/**
 * The zoninator/v1 route that takes a zone's lock.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Takes or renews the current user's lock on a zone: /zones/{zone_id}/lock.
 *
 * @since 1.0.0
 * @internal Use the REST API itself, not this class.
 */
final class LockController extends WP_REST_Controller {

	use RegistersRoutes;

	/**
	 * Constructor.
	 *
	 * @param ZoneLocker  $locker      Zone locks.
	 * @param Settings    $settings    Zoninator's settings.
	 * @param Permissions $permissions Says who may call each route.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly ZoneLocker $locker,
		private readonly Settings $settings,
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
			'/zones/(?P<zone_id>[\d]+)/lock',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'zone_update_lock' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => $this->get_zone_id_param(),
				),
			)
		);
	}

	/**
	 * Take the zone's lock for the current user, or renew it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function zone_update_lock( $request ): WP_REST_Response|WP_Error {
		$zone_id = (int) $request['zone_id'];

		if ( ! $zone_id ) {
			return $this->errors->zone_id_required();
		}

		try {
			$this->locker->lock( $zone_id, get_current_user_id() );
		} catch ( ZoneNotFoundException | ZoneLockedException $exception ) {
			return $this->errors->from_exception( $exception, $zone_id );
		}

		return new WP_REST_Response(
			array(
				'zone_id'         => $zone_id,
				'timeout'         => $this->settings->get_lock_period(),
				'max_lock_period' => $this->settings->get_max_lock_period(),
			),
			200
		);
	}
}
