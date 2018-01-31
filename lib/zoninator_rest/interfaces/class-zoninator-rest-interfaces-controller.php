<?php
/**
 * Our controller Interface
 *
 * @package Mixtape/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Zoninator_REST_Interfaces_Controller
 */
interface Zoninator_REST_Interfaces_Controller {
	/**
	 * Register This Controller
	 *
	 * @param Zoninator_REST_Controller_Bundle $bundle The bundle to register with.
	 * @param Zoninator_REST_Environment       $environment The Environment to use.
	 * @throws Zoninator_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	function register( $bundle, $environment );
}
