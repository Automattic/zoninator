<?php
/**
 * Who may call the zoninator/v1 REST routes.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use WP_Error;
use WP_REST_Request;

/**
 * The permission callbacks for the zoninator/v1 routes. A refused request
 * gets 401 when logged out and 403 when logged in.
 *
 * @since 1.0.0
 * @internal Use the REST API itself, not this class.
 */
final class Permissions {

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param Capabilities $capabilities The capability checks.
	 */
	public function __construct( Capabilities $capabilities ) {
		$this->capabilities = $capabilities;
	}

	/**
	 * Whether the request may list the zones: any logged-in user, unless a site filters it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function list_zones( $request ) {
		return (bool) apply_filters( 'zoninator_rest_get_zones_permissions_check', is_user_logged_in(), $request );
	}

	/**
	 * Whether the request may read a zone's posts. The feed is public, so anyone may.
	 *
	 * @return bool
	 */
	public function read_zone_posts() {
		return true;
	}

	/**
	 * Whether the current user may create zones.
	 *
	 * @return bool|WP_Error
	 */
	public function create_zone() {
		return $this->allow( $this->capabilities->can_create_zones( get_current_user_id() ) );
	}

	/**
	 * Whether the current user may change or delete the zone in the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function edit_zone( $request ) {
		return $this->allow( $this->capabilities->can_edit_zone( get_current_user_id(), absint( $request->get_param( 'zone_id' ) ) ) );
	}

	/**
	 * Whether the current user may curate zones: change their posts, search for posts and take a lock.
	 *
	 * @return bool|WP_Error
	 */
	public function manage_zones() {
		return $this->allow( $this->capabilities->can_curate_zones( get_current_user_id() ) );
	}

	/**
	 * @param bool $allowed Whether the user may.
	 * @return true|WP_Error True, or the error for a user who may not.
	 */
	private function allow( bool $allowed ) {
		if ( ! $allowed ) {
			return new WP_Error( ErrorCodes::PERMISSION_DENIED, __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
