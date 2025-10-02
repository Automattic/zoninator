<?php
/**
 * Controller Bundle
 *
 * A collection of Zoninator_REST_Rest_Api_Controller, sharing a common prefix.
 *
 * @package Mixtape/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Zoninator_REST_Interfaces_Rest_Api_Controller_Bundle
 */
interface Zoninator_REST_Interfaces_Controller_Bundle extends Zoninator_REST_Interfaces_Registrable {

	/**
	 * Get the Prefix
	 *
	 * @return string
	 */
	public function get_prefix();
}
