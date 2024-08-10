<?php
/**
 * Fields
 *
 * @package Zoninator_REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Zoninator_REST_Field_Declaration
 */
class Zoninator_REST_Field_Declaration {

	/**
	 * Field A field
	 */
	public const FIELD = 'field';

	/**
	 * Meta a meta field
	 */
	public const META = 'meta';

	/**
	 * Derived field kinds get their values from callables. It is also
	 * possible to update their values from callables
	 */
	public const DERIVED = 'derived';
	/**
	 * Map From
	 *
	 * @var null|string
	 */
	private $map_from;
	/**
	 * The field kind
	 *
	 * @var string
	 */
	private $kind;
	/**
	 * Field name
	 *
	 * @var string
	 */
	private $name;
	/**
	 * Is this a primary field?
	 *
	 * @var bool
	 */
	private $primary;
	/**
	 * Is this a required field?
	 *
	 * @var bool
	 */
	private $required;
	/**
	 * Outputs
	 *
	 * @var array
	 */
	private $supported_outputs;
	/**
	 * Description
	 *
	 * @var string
	 */
	private $description;
	/**
	 * Data Transfer Name
	 *
	 * @var null|string
	 */
	private $data_transfer_name;
	/**
	 * Validations
	 *
	 * @var null|array
	 */
	private $validations;
	/**
	 * Default Value
	 *
	 * @var null|mixed
	 */
	private $default_value;
	/**
	 * Field Choices
	 *
	 * @var null|array
	 */
	private $choices;
	/**
	 * Type
	 *
	 * @var null|Zoninator_REST_Interfaces_Type
	 */
	private $type;
	/**
	 * Acceptable field kinds
	 *
	 * @var array
	 */
	private $field_kinds = array(
		self::FIELD,
		self::META,
		self::DERIVED,
	);
	/**
	 * A custom function to call before serialization
	 *
	 * @var null|callable
	 */
	private $serializer;
	/**
	 * A custom function to call before deserialization
	 *
	 * @var null|callable
	 */
	private $deserializer;
	/**
	 * A custom function to use for sanitizing the field value before setting it.
	 * Used when receiving values from untrusted sources (e.g. a web form of a REST API request)
	 *
	 * @var null|callable
	 */
	private $sanitizer;
	/**
	 * A custom filtering callable triggered before setting the field with the value
	 *
	 * @var null|callable
	 */
	private $before_set;
	/**
	 * A custom filtering callable triggered before returning the field value
	 *
	 * @var null|callable
	 */
	private $before_get;
	/**
	 * Used by derived fields: The function to use to get the field value
	 *
	 * @var null|callable
	 */
	private $reader;
	/**
	 * Used by derived fields: The function to use to update the field value
	 *
	 * @var null|callable
	 */
	private $updater;

	/**
	 * Constructor.
	 *
	 * @param array $args The arguments.
	 * @throws Zoninator_REST_Exception When invalid name or kind provided.
	 */
	public function __construct( $args ) {
		if ( ! isset( $args['name'] ) || empty( $args['name'] ) || ! is_string( $args['name'] ) ) {
			throw new Zoninator_REST_Exception( 'every field declaration should have a (non-empty) name string' );
		}
		if ( ! isset( $args['kind'] ) || ! in_array( $args['kind'], $this->field_kinds, true ) ) {
			throw new Zoninator_REST_Exception( 'every field should have a kind (one of ' . implode( ',', $this->field_kinds ) . ')' );
		}

		$this->name        = $args['name'];
		$this->description = $this->value_or_default( $args, 'description', '' );

		$this->kind          = $args['kind'];
		$this->type          = $this->value_or_default( $args, 'type', Zoninator_REST_Type::any() );
		$this->choices       = $this->value_or_default( $args, 'choices', null );
		$this->default_value = $this->value_or_default( $args, 'default_value' );

		$this->map_from           = $this->value_or_default( $args, 'map_from' );
		$this->data_transfer_name = $this->value_or_default( $args, 'data_transfer_name', $this->get_name() );

		$this->primary           = $this->value_or_default( $args, 'primary', false );
		$this->required          = $this->value_or_default( $args, 'required', false );
		$this->supported_outputs = $this->value_or_default( $args, 'supported_outputs', array( 'json' ) );

		$this->sanitizer   = $this->value_or_default( $args, 'sanitizer' );
		$this->validations = $this->value_or_default( $args, 'validations', array() );

		$this->serializer   = $this->value_or_default( $args, 'serializer' );
		$this->deserializer = $this->value_or_default( $args, 'deserializer' );

		$this->before_get = $this->value_or_default( $args, 'before_get' );
		$this->before_set = $this->value_or_default( $args, 'before_set' );

		$this->reader  = $this->value_or_default( $args, 'reader' );
		$this->updater = $this->value_or_default( $args, 'updater' );
	}

	/**
	 * Get possible choices if set
	 *
	 * @return null|array
	 */
	public function get_choices() {
		return $this->choices;
	}

	/**
	 * Get Sanitizer
	 *
	 * @return callable|null
	 */
	public function get_sanitizer() {
		return $this->sanitizer;
	}

	/**
	 * Value or Default
	 *
	 * @param array  $args Args.
	 * @param string $name Name.
	 * @param mixed  $default Default.
	 * @return null
	 */
	private function value_or_default( $args, $name, $default = null ) {
		return $args[ $name ] ?? $default;
	}

	/**
	 * Is Kind
	 *
	 * @param string $kind The kind.
	 * @return bool
	 */
	public function is_kind( $kind ) {
		if ( ! in_array( $kind, $this->field_kinds, true ) ) {
			return false;
		}
		return $this->kind === $kind;
	}

	/**
	 * Get default value
	 *
	 * @return mixed
	 */
	public function get_default_value() {
		if ( isset( $this->default_value ) && ! empty( $this->default_value ) ) {
			return ( is_array( $this->default_value ) && is_callable( $this->default_value ) ) ? call_user_func( $this->default_value ) : $this->default_value;
		}

		return $this->type->default_value();
	}

	/**
	 * Cast a value
	 *
	 * @param mixed $value Val.
	 * @return mixed
	 */
	public function cast_value( $value ) {
		return $this->type->cast( $value );
	}

	/**
	 * Supports this type of output.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public function supports_output_type( $type ) {
		return in_array( $type, $this->supported_outputs, true );
	}

	/**
	 * As Item Schema Property
	 *
	 * @return array
	 */
	public function as_item_schema_property() {
		$schema                = $this->type->schema();
		$schema['context']     = array( 'view', 'edit' );
		$schema['description'] = $this->get_description();

		if ( $this->get_choices() ) {
			$schema['enum'] = (array) $this->get_choices();
		}
		return $schema;
	}

	/**
	 * Get Map From
	 *
	 * @return null
	 */
	public function get_map_from() {
		if ( isset( $this->map_from ) && ! empty( $this->map_from ) ) {
			return $this->map_from;
		}

		return $this->get_name();
	}

	/**
	 * Get Kind
	 *
	 * @return mixed
	 */
	public function get_kind() {
		return $this->kind;
	}

	/**
	 * Get Name
	 *
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Is Primary
	 *
	 * @return bool
	 */
	public function is_primary() {
		return (bool) $this->primary;
	}

	/**
	 * Is Required
	 *
	 * @return bool
	 */
	public function is_required() {
		return (bool) $this->required;
	}

	/**
	 * Get Description
	 *
	 * @return string
	 */
	public function get_description() {
		if ( isset( $this->description ) && ! empty( $this->description ) ) {
			return $this->description;
		}
		$name = ucfirst( str_replace( '_', ' ', $this->get_name() ) );
		return $name;
	}

	/**
	 * Get Dto name
	 *
	 * @return string
	 */
	public function get_data_transfer_name() {
		return $this->data_transfer_name ?? $this->get_name();
	}

	/**
	 * Get Validations
	 *
	 * @return array
	 */
	public function get_validations() {
		return $this->validations;
	}

	/**
	 * Get Before get
	 *
	 * @return callable|null
	 */
	public function before_get() {
		return $this->before_get;
	}

	/**
	 * Get Serializer
	 *
	 * @return callable|null
	 */
	public function get_serializer() {
		return $this->serializer;
	}

	/**
	 * Get Deserializer
	 *
	 * @return callable|null
	 */
	public function get_deserializer() {
		return $this->deserializer;
	}

	/**
	 * Get Type
	 *
	 * @return Zoninator_REST_Interfaces_Type
	 */
	function get_type() {
		return $this->type;
	}

	/**
	 * Before Set
	 *
	 * @return callable|null
	 */
	public function before_set() {
		return $this->before_set;
	}

	/**
	 * Get Reader
	 *
	 * @return callable|null
	 */
	public function get_reader() {
		return $this->reader;
	}

	/**
	 * Get Updater
	 *
	 * @return callable|null
	 */
	public function get_updater() {
		return $this->updater;
	}
}
