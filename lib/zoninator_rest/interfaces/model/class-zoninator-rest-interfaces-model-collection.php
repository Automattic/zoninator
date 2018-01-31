<?php
/**
 * A Collection of Mixtape_Interfaces_Model
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Zoninator_REST_Interfaces_Model_Collection
 */
interface Zoninator_REST_Interfaces_Model_Collection {
	/**
	 * Get all the collection's Items
	 *
	 * @return Iterator
	 */
	function get_items();
}
