<?php
/**
 * Who may call the zoninator/v1 REST routes.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;
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
	 * Constructor.
	 *
	 * @param Capabilities $capabilities The capability checks.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly Capabilities $capabilities,
		private readonly Errors $errors,
	) {
	}

	/**
	 * Whether the request may list the zones: a user who can edit posts, as the
	 * block editor's zone picker reads the list, or one who can curate zones,
	 * unless a site filters it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function list_zones( $request ): bool {
		$authorized = current_user_can( 'edit_posts' ) || $this->capabilities->can_curate_zones( get_current_user_id() );

		/**
		 * Filters whether a request may list the zones with GET /zoninator/v1/zones.
		 *
		 * @since 0.11.0
		 * @since 1.0.0 $authorized is whether the user can edit posts or curate zones. Before, whether they were logged in.
		 *
		 * @param bool            $authorized Whether the user can edit posts or curate zones.
		 * @param WP_REST_Request $request    The request.
		 */
		return (bool) apply_filters( 'zoninator_rest_get_zones_permissions_check', $authorized, $request );
	}

	/**
	 * Whether the current user may create zones.
	 *
	 * @return bool|WP_Error
	 */
	public function create_zone(): true|WP_Error {
		return $this->allow( $this->capabilities->can_create_zones( get_current_user_id() ) );
	}

	/**
	 * Whether the current user may change or delete the zone in the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool|WP_Error
	 */
	public function edit_zone( $request ): true|WP_Error {
		return $this->allow( $this->capabilities->can_edit_zone( get_current_user_id(), absint( $request->get_param( 'zone_id' ) ) ) );
	}

	/**
	 * Whether the current user may curate zones: change their posts, search for posts and take a lock.
	 *
	 * @return bool|WP_Error
	 */
	public function manage_zones(): true|WP_Error {
		return $this->allow( $this->capabilities->can_curate_zones( get_current_user_id() ) );
	}

	/**
	 * @param bool $allowed Whether the user may.
	 * @return true|WP_Error True, or the error for a user who may not.
	 */
	private function allow( bool $allowed ): true|WP_Error {
		if ( ! $allowed ) {
			return $this->errors->permission_denied();
		}

		return true;
	}
}
