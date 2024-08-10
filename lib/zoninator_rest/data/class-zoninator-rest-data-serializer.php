<?php
/**
 * Data Mapper
 *
 * @package Zoninator_REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Data_Serializer
 */
class Zoninator_REST_Data_Serializer {
	/**
	 * Declaration
	 *
	 * @var Zoninator_REST_Interfaces_Model_Declaration
	 */
	private $model_declaration;

	/**
	 * Mixtape_Data_Serializer constructor.
	 *
	 * @param Zoninator_REST_Model_Definition $model_definition MD.
	 */
	function __construct( $model_definition ) {
		$this->model_declaration = $model_definition->get_model_declaration();
	}

	/**
	 * Deserialize
	 *
	 * @param Zoninator_REST_Field_Declaration $field_declaration Declaration.
	 * @param mixed                            $value Value.
	 * @return mixed the deserialized value
	 */
	function deserialize( $field_declaration, $value ) {
		$deserializer = $field_declaration->get_deserializer();
		return $deserializer ? $this->model_declaration->call( $deserializer, array( $value ) ) : $value;
	}

	/**
	 * Serialize
	 *
	 * @param  Zoninator_REST_Field_Declaration $field_declaration Declaration.
	 * @param mixed                            $value Value.
	 * @return mixed
	 * @throws Zoninator_REST_Exception If call fails.
	 */
	function serialize( $field_declaration, $value ) {
		$serializer = $field_declaration->get_serializer();
		if ( isset( $serializer ) && ! empty( $serializer ) ) {
			return $this->model_declaration->call( $serializer, array( $value ) );
		}
		return $value;
	}
}
