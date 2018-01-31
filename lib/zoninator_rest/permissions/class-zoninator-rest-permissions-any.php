<?php
/**
 * Any Permission
 *
 * @package Zoninator_REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Permissions_Any
 */
class Zoninator_REST_Permissions_Any implements Zoninator_REST_Interfaces_Permissions_Provider {

	/**
	 * Handle Permissions for a REST Controller Action
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $action The action (e.g. index, create update etc).
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		return true;
	}
}
