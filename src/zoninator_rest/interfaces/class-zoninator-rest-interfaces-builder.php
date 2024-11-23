<?php
/**
 * Build Stuff
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Zoninator_REST_Interfaces_Builder
 */
interface Zoninator_REST_Interfaces_Builder {
	/**
	 * Build something
	 *
	 * @return mixed
	 */
	public function build();
}
