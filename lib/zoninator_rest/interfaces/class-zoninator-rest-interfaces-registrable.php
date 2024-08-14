<?php
/**
 * Something that can be registered with an environment
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Zoninator_REST_Interfaces_Registrable
 */

interface Zoninator_REST_Interfaces_Registrable {
	/**
	 * Register This with an environment
	 *
	 * @param Zoninator_REST_Environment $environment The Environment to use.
	 * @return void
	 */
	public function register( $environment );
}
