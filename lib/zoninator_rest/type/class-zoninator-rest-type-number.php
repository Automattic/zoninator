<?php
/**
 * The Number Type (a floating point type)
 *
 * @package Zoninator_REST/Types
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Type_Number
 */
class Zoninator_REST_Type_Number extends Zoninator_REST_Type {

	/**
	 * Zoninator_REST_Type_Number constructor.
	 */
	public function __construct() {
		parent::__construct( 'number' );
	}

	/**
	 * The default value
	 *
	 * @return float
	 */
	public function default_value() {
		return 0.0;
	}

	/**
	 * Cast
	 *
	 * @param mixed $value The thing to cast.
	 * @return float
	 */
	public function cast( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}
		return floatval( $value );
	}

	/**
	 * Sanitize
	 *
	 * @param mixed $value The value to sanitize.
	 * @return float
	 */
	public function sanitize( $value ) {
		return $this->cast( $value );
	}
}
