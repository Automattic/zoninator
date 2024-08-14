<?php
/**
 * The default Mixtape_Interfaces_Type Implementation
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Type
 */
class Zoninator_REST_Type implements Zoninator_REST_Interfaces_Type {
	/**
	 * The unique identifier of this type.
	 *
	 * @var string
	 */
	protected $identifier;
	/**
	 * Mixtape_Type constructor.
	 *
	 * @param string $identifier The identifier.
	 */
	public function __construct( $identifier ) {
		$this->identifier = $identifier;
	}

	/**
	 * The name
	 *
	 * @return string
	 */
	public function name() {
		return $this->identifier;
	}

	/**
	 * The default value
	 */
	public function default_value() {
		return null;
	}

	/**
	 * Cast value to be Type
	 *
	 * @param mixed $value The value that needs casting.
	 *
	 * @return mixed
	 */
	public function cast( $value ) {
		return $value;
	}

	/**
	 * Sanitize this value
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return mixed
	 */
	public function sanitize( $value ) {
		return $value;
	}

	/**
	 * Get this type's JSON Schema.
	 *
	 * @return array
	 */
	public function schema() {
		return array(
			'type' => $this->name(),
		);
	}

	/**
	 * Get our "Any" type
	 *
	 * @return Zoninator_REST_Type
	 */
	public static function any() {
		return new self( 'any' );
	}
}
