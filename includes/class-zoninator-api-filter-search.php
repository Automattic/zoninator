<?php
/**
 * Declaration of our Status Filters (will be used in GET requests)
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Filters_Status
 */
class Zoninator_Api_Filter_Search extends Zoninator_REST_Model {

	/**
	 * Declare our fields
	 *
	 * @return array
	 * @throws Zoninator_REST_Exception Exc.
	 */
	public function declare_fields() {
		$env = $this->get_environment();
		return array(
			$env->field( 'term', __( 'search term', 'zoninator' ) )
				->with_type( $env->type( 'string' ) )
				->with_before_set( array( $this, 'strip_slashes' ) )
				->with_required( true )
				->with_default( '' ),
			$env->field( 'cat', __( 'filter by category', 'zoninator' ) )
				->with_type( $env->type( 'uint' ) )
				->with_validations( array( $this, 'is_numeric' ) )
				->with_default( 0 ),
			$env->field( 'date', __( 'only get posts after this date (format YYYY-mm-dd)', 'zoninator' ) )
				->with_type( $env->type( 'string' ) )
				->with_before_set( array( $this, 'date_before_set' ) )
				->with_default( '' ),
			$env->field( 'limit', __( 'limit results', 'zoninator' ) )
				->with_type( $env->type( 'uint' ) )
				->with_before_set( array( $this, 'strip_slashes' ) )
				->with_default( Zoninator()->posts_per_page ),
			$env->field( 'exclude', __( 'post_ids to exclude', 'zoninator' ) )
				->with_type( $env->type( 'array:uint' ) ),
		);
	}

	/**
	 * Is Numeric
	 *
	 * @param mixed $item The item.
	 * @return bool
	 */
	public function is_numeric( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly.
		return is_numeric( $item );
	}

	/**
	 * Strip slashes
	 *
	 * @param mixed $item Item.
	 * @return string
	 */
	public function strip_slashes( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use stripslashes directly.
		return stripslashes( $item );
	}

	public function strip_tags( $item ) {
		return strip_tags( $item );
	}

	function date_before_set( $model, $item ) {
		return $this->strip_tags( $this->strip_slashes( $item ) );
	}
}
