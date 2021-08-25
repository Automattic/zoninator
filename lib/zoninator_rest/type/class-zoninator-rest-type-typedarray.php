<?php
/**
 * Typed Array
 *
 * A container types
 *
 * @package Mixtape/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Type_TypedArray
 */
class Zoninator_REST_Type_TypedArray extends Zoninator_REST_Type {

	/**
	 * The type this array contains
	 *
	 * @var Zoninator_REST_Interfaces_Type
	 */
	private $item_type_definition;

	/**
	 * Mixtape_TypeDefinition_TypedArray constructor.
	 *
	 * @param Zoninator_REST_Interfaces_Type $item_type_definition The type.
	 */
	public function __construct( $item_type_definition ) {
		parent::__construct( 'array:' . $item_type_definition->name() );
		$this->item_type_definition = $item_type_definition;
	}

	/**
	 * Get the default value
	 *
	 * @return array
	 */
	public function default_value() {
		return array();
	}

	/**
	 * Cast the value to be a typed array
	 *
	 * @param mixed $value an array of mixed.
	 * @return array
	 */
	public function cast( $value ) {
		$new_value = array();

		foreach ( $value as $v ) {
			$new_value[] = $this->item_type_definition->cast( $v );
		}
		return (array) $new_value;
	}

	/**
	 * Get this type's JSON Schema
	 *
	 * @return array
	 */
	public function schema() {
		$schema          = parent::schema();
		$schema['type']  = 'array';
		$schema['items'] = $this->item_type_definition->schema();
		return $schema;
	}
}
