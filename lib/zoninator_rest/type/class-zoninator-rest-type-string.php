<?php
/**
 * String type
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Type_String
 */
class Zoninator_REST_Type_String extends Zoninator_REST_Type {
	/**
	 * Zoninator_REST_Type_String constructor.
	 */
	public function __construct() {
		parent::__construct( 'string' );
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	public function sanitize( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Default
	 *
	 * @return string
	 */
	public function default_value() {
		return '';
	}

	/**
	 * Cast
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	public function cast( $value ) {
		if ( is_array( $value ) ) {
			$cast_ones = array();
			foreach ( $value as $v ) {
				$cast_ones[] = $this->cast( $v );
			}
			return '(' . implode( ',', $cast_ones ) . ')';
		}
		return (string) $value;
	}
}
