<?php
/**
 * Field Declaration Builder
 *
 * @package Zoninator_REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Mixtape_Model_Field_Declaration_Builder
 * Builds a Mixtape_Model_Field_Declaration
 */
class Zoninator_REST_Field_Declaration_Builder {

	/**
	 * Constructor.
	 */
	function __construct() {
		$this->args = array(
			'name'               => '',
			'kind'               => Zoninator_REST_Field_Declaration::FIELD,
			'type'               => Zoninator_REST_Type::any(),
			'required'           => false,
			'map_from'           => null,

			'sanitizer'          => null,

			'serializer'         => null,
			'deserializer'       => null,

			'default_value'      => null,
			'data_transfer_name' => null,
			'supported_outputs'  => array( 'json' ),
			'description'        => null,
			'validations'        => array(),
			'choices'            => null,
			'contexts'           => array( 'view', 'edit' ),
			'before_set'         => null,
			'before_get'         => null,
			'reader'             => null,
			'updater'            => null,
		);
	}

	/**
	 * Build it
	 *
	 * @return Zoninator_REST_Field_Declaration
	 */
	public function build() {
		return new Zoninator_REST_Field_Declaration( $this->args );
	}

	/**
	 * Default Value.
	 *
	 * @param mixed $default_value Default.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_default( $default_value ) {
		return $this->with( 'default_value', $default_value );
	}

	/**
	 * With Name
	 *
	 * @param string $name Name.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_name( $name ) {
		return $this->with( 'name', $name );
	}

	/**
	 * With Kind
	 *
	 * @param string $kind Kind.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_kind( $kind ) {
		return $this->with( 'kind', $kind );
	}

	/**
	 * With Map From
	 *
	 * @param string $mapped_from Mapped From.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_map_from( $mapped_from ) {
		return $this->with( 'map_from', $mapped_from );
	}

	/**
	 * With Sanitizer
	 *
	 * @param callable $sanitizer Sanitizer.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_sanitizer( $sanitizer ) {
		$this->expect_is_callable( $sanitizer, __METHOD__ );
		return $this->with( 'sanitizer', $sanitizer );
	}

	/**
	 * With Serializer
	 *
	 * @param callable $serializer Serializer.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_serializer( $serializer ) {
		return $this->with( 'serializer', $serializer );
	}

	/**
	 * With Deserializer
	 *
	 * @param callable $deserializer Deserializer.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_deserializer( $deserializer ) {
		return $this->with( 'deserializer', $deserializer );
	}

	/**
	 * With Required
	 *
	 * @param bool $required Req.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_required( $required = true ) {
		return $this->with( 'required', $required );

	}

	/**
	 * With Supported Outputs
	 *
	 * @param array $supported_outputs Outputs.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_supported_outputs( $supported_outputs = array() ) {
		return $this->with( 'supported_outputs', (array) $supported_outputs );
	}

	/**
	 * Set the type definition of this field declaration
	 *
	 * @param Zoninator_REST_Interfaces_Type $value_type Type.
	 * @return Zoninator_REST_Field_Declaration_Builder $this
	 *
	 * @throws Zoninator_REST_Exception When not a type.
	 */
	public function with_type( $value_type ) {
		if ( ! is_a( $value_type, 'Zoninator_REST_Interfaces_Type' ) ) {
			throw new Zoninator_REST_Exception( get_class( $value_type ) . ' is not a Mixtape_Interfaces_Type' );
		}
		return $this->with( 'type', $value_type );
	}

	/**
	 * With Dto Name
	 *
	 * @param string $dto_name Dto Name.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_dto_name( $dto_name ) {
		return $this->with( 'data_transfer_name', $dto_name );
	}

	/**
	 * With Description
	 *
	 * @param string $description Description.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_description( $description ) {
		return $this->with( 'description', $description );
	}

	/**
	 * With Validations
	 *
	 * @param array|mixed $validations Validations.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_validations( $validations ) {
		if ( is_callable( $validations ) || ! is_array( $validations ) ) {
			$validations = array( $validations );
		}
		return $this->with( 'validations', $validations );
	}

	/**
	 * Before Set
	 *
	 * @param callable $before_set Before set.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_before_set( $before_set ) {
		return $this->with( 'before_set', $before_set );
	}

	/**
	 * Before Get
	 *
	 * @param callable $before_get Before get.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_before_get( $before_get ) {
		return $this->with( 'before_get', $before_get );
	}

	/**
	 * Choices.
	 *
	 * @param array|mixed $choices Choices.
	 *
	 * @return $this|Zoninator_REST_Field_Declaration_Builder
	 */
	public function with_choices( $choices ) {
		if ( empty( $choices ) ) {
			return $this;
		}
		return $this->with( 'choices', is_array( $choices ) ? $choices : array( $choices ) );
	}

	/**
	 * Set
	 *
	 * @param string $name Name.
	 * @param mixed  $value Value.
	 * @return Zoninator_REST_Field_Declaration_Builder $this
	 */
	private function with( $name, $value ) {
		$this->args[ $name ] = $value;
		return $this;
	}

	/**
	 * Derived Field
	 *
	 * @param callable $func The func.
	 *
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	public function derived( $func = null ) {
		if ( $func ) {
			$this->with_map_from( $func );
		}
		return $this->with_kind( Zoninator_REST_Field_Declaration::DERIVED );
	}

	/**
	 * Set Updater
	 *
	 * @param callable $func Func.
	 * @return Zoninator_REST_Field_Declaration_Builder $this
	 * @throws Zoninator_REST_Exception When no callable.
	 */
	public function with_updater( $func ) {
		return $this->with( 'updater', $func );
	}

	/**
	 * Set reader
	 *
	 * @param callable $func Func.
	 * @return Zoninator_REST_Field_Declaration_Builder $this
	 * @throws Zoninator_REST_Exception When no callable.
	 */
	public function with_reader( $func ) {
		return $this->with( 'reader', $func );
	}

	/**
	 * Callable test
	 *
	 * @param callable|mixed $thing Thing to test.
	 * @param string         $func The caller.
	 *
	 * @throws Zoninator_REST_Exception If not callable.
	 */
	private function expect_is_callable( $thing, $func ) {
		Zoninator_REST_Expect::that( is_callable( $thing ), $func . ' Expected a callable' );
	}
}
