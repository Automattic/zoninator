<?php
/**
 * Type Registry
 *
 * @package Zoninator_REST/Type
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Type_Registry
 */
class Zoninator_REST_Type_Registry {
	/**
	 * Container Types (types that contain other types)
	 *
	 * @var array
	 */
	private $container_types = array(
		'array',
		'nullable',
	);

	/**
	 * Our registered types
	 *
	 * @var null|array
	 */
	private $types;

	/**
	 * Define a new type
	 *
	 * @param string                         $identifier The Identifier.
	 * @param Zoninator_REST_Interfaces_Type $instance The type instance.
	 *
	 * @return Zoninator_REST_Type_Registry $this
	 *
	 * @throws Zoninator_REST_Exception When $instance not a Zoninator_REST_Interfaces_Type.
	 */
	public function define( $identifier, $instance ) {
		Zoninator_REST_Expect::is_a( $instance, 'Zoninator_REST_Interfaces_Type' );
		$this->types[ $identifier ] = $instance;
		return $this;
	}

	/**
	 * Get a type definition
	 *
	 * @param string $type The type name.
	 * @return Zoninator_REST_Interfaces_Type
	 *
	 * @throws Zoninator_REST_Exception In case of type name not confirming to syntax.
	 */
	function definition( $type ) {
		$types = $this->get_types();

		if ( ! isset( $types[ $type ] ) ) {
			// maybe lazy-register missing compound type.
			$parts = explode( ':', $type );
			if ( count( $parts ) > 1 ) {
				$container_type = $parts[0];
				if ( ! in_array( $container_type, $this->container_types, true ) ) {
					throw new Zoninator_REST_Exception( $container_type . ' is not a known container type' );
				}

				$item_type = $parts[1];
				if ( empty( $item_type ) ) {
					throw new Zoninator_REST_Exception( $type . ': invalid syntax' );
				}
				$item_type_definition = $this->definition( $item_type );

				if ( 'array' === $container_type ) {
					$this->define( $type, new Zoninator_REST_Type_TypedArray( $item_type_definition ) );
					$types = $this->get_types();
				}

				if ( 'nullable' === $container_type ) {
					$this->define( $type, new Zoninator_REST_Type_Nullable( $item_type_definition ) );
					$types = $this->get_types();
				}
			}
		}

		if ( ! isset( $types[ $type ] ) ) {
			throw new Zoninator_REST_Exception();
		}
		return $types[ $type ];
	}

	/**
	 * Get Types
	 *
	 * @return array
	 */
	private function get_types() {
		return (array) apply_filters( 'mixtape_type_registry_get_types', $this->types, $this );
	}

	/**
	 * Initialize the type registry
	 *
	 * @param Zoninator_REST_Environment $environment The Environment.
	 */
	public function initialize( $environment ) {
		if ( null !== $this->types ) {
			return;
		}

		$this->types = apply_filters(
			'mixtape_type_registry_register_types',
			array(
				'any'     => new Zoninator_REST_Type( 'any' ),
				'string'  => new Zoninator_REST_Type_String(),
				'integer' => new Zoninator_REST_Type_Integer(),
				'int'     => new Zoninator_REST_Type_Integer(),
				'uint'    => new Zoninator_REST_Type_Integer( true ),
				'number'  => new Zoninator_REST_Type_Number(),
				'float'   => new Zoninator_REST_Type_Number(),
				'boolean' => new Zoninator_REST_Type_Boolean(),
				'array'   => new Zoninator_REST_Type_Array(),
			),
			$this,
			$environment 
		);
	}
}
