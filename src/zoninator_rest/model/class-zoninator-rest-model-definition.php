<?php
/**
 * The model definition
 *
 * @package Zoninator_REST/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Model_Definition
 */
class Zoninator_REST_Model_Definition implements Zoninator_REST_Interfaces_Permissions_Provider {

	/**
	 * Environment
	 *
	 * @var Zoninator_REST_Environment
	 */
	private $environment;

	/**
	 * Field Declarations
	 *
	 * @var array
	 */
	private $field_declarations;

	/**
	 * Model class
	 *
	 * @var string
	 */
	private $model_class;

	/**
	 * Data Store
	 *
	 * @var Zoninator_REST_Interfaces_Data_Store
	 */
	private $data_store;

	/**
	 * Model Declaration
	 *
	 * @var Zoninator_REST_Interfaces_Model_Declaration
	 */
	private $model_declaration;

	/**
	 * Name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Permissions Provider
	 *
	 * @var Zoninator_REST_Interfaces_Permissions_Provider
	 */
	private $permissions_provider;

	/**
	 * Mixtape_Model_Definition constructor.
	 *
	 * @param Zoninator_REST_Environment                                             $environment The Environment.
	 * @param Zoninator_REST_Interfaces_Model_Declaration                            $model_declaration Declaration.
	 * @param Zoninator_REST_Interfaces_Data_Store|Zoninator_REST_Data_Store_Builder $data_store Store.
	 * @param Zoninator_REST_Interfaces_Permissions_Provider                         $permissions_provider Provider.
	 *
	 * @throws Zoninator_REST_Exception Throws if wrong types or null args provided.
	 */
	public function __construct( $environment, $model_declaration, $data_store, $permissions_provider ) {
		Zoninator_REST_Expect::that( null !== $environment, '$environment cannot be null' );
		Zoninator_REST_Expect::that( null !== $model_declaration, '$model_declaration cannot be null' );
		Zoninator_REST_Expect::that( null !== $data_store, '$data_store cannot be null' );
		Zoninator_REST_Expect::that( null !== $permissions_provider, '$permissions_provider cannot be null' );
		// Fail if provided with inappropriate types.
		Zoninator_REST_Expect::is_a( $environment, 'Zoninator_REST_Environment' );
		Zoninator_REST_Expect::is_a( $model_declaration, 'Zoninator_REST_Interfaces_Model_Declaration' );
		Zoninator_REST_Expect::is_a( $permissions_provider, 'Zoninator_REST_Interfaces_Permissions_Provider' );
		$this->environment          = $environment;
		$this->model_declaration    = $model_declaration;
		$this->model_class          = get_class( $model_declaration );
		$this->permissions_provider = $permissions_provider;
		$this->name                 = strtolower( $this->model_class );

		$this->set_data_store( $data_store );
	}

	/**
	 * Get Model Class
	 *
	 * @return string
	 */
	public function get_model_class() {
		return $this->model_class;
	}

	/**
	 * Get Data Store
	 *
	 * @return Zoninator_REST_Interfaces_Data_Store
	 */
	public function get_data_store() {
		return $this->data_store;
	}

	/**
	 * Set the Data Store
	 *
	 * @param Zoninator_REST_Interfaces_Data_Store|Zoninator_REST_Data_Store_Builder $data_store A builder or a Data store.
	 * @return $this
	 * @throws Zoninator_REST_Exception Throws when Data Store Invalid.
	 */
	public function set_data_store( $data_store ) {
		if ( is_a( $data_store, 'Zoninator_REST_Data_Store_Builder' ) ) {
			$this->data_store = $data_store
				->with_model_definition( $this )
				->build();
		} else {
			$this->data_store = $data_store;
		}

		// at this point we should have a data store.
		Zoninator_REST_Expect::is_a( $this->data_store, 'Zoninator_REST_Interfaces_Data_Store' );

		return $this;
	}

	/**
	 * Environment
	 *
	 * @return Zoninator_REST_Environment
	 */
	public function environment() {
		return $this->environment;
	}

	/**
	 * Get this Definition's Field Declarations
	 *
	 * @param null|string $filter_by_type The type to filter with.
	 *
	 * @return array|null
	 */
	public function get_field_declarations( $filter_by_type = null ) {
		$model_declaration = $this->get_model_declaration()->set_definition( $this );

		Zoninator_REST_Expect::is_a( $model_declaration, 'Zoninator_REST_Interfaces_Model_Declaration' );

		if ( null === $this->field_declarations ) {
			$fields = $model_declaration->declare_fields( $this->environment() );

			$this->field_declarations = $this->initialize_field_map( $fields );
		}

		if ( null === $filter_by_type ) {
			return $this->field_declarations;
		}

		$filtered = array();

		foreach ( $this->field_declarations as $field_declaration ) {
			/**
			 * The field declaration.
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			if ( $field_declaration->get_kind() === $filter_by_type ) {
				$filtered[] = $field_declaration;
			}
		}

		return $filtered;
	}

	/**
	 * Create a new Model Instance
	 *
	 * @param array $data The data.
	 *
	 * @return Zoninator_REST_Model
	 * @throws Zoninator_REST_Exception Throws if data not an array.
	 */
	public function create_instance( $data ) {
		if ( is_array( $data ) ) {
			return new Zoninator_REST_Model( $this, $data );
		}

		throw new Zoninator_REST_Exception( 'does not understand entity' );
	}

	/**
	 * * Merge values from array with current values.
	 * Note: Values change in place.
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 * @param array                           $data The data (key-value assumed).
	 * @param bool                            $updating Is this an update?.
	 *
	 * @return Zoninator_REST_Interfaces_Model|WP_Error
	 * @throws Zoninator_REST_Exception Throws.
	 */
	public function update_model_from_array( $model, $data, $updating = false ) {
		$mapped_data = $this->map_data( $data, $updating );
		foreach ( $mapped_data as $name => $value ) {
			$model->set( $name, $value );
		}

		return $model->sanitize();
	}

	/**
	 * Get Model Declaration
	 *
	 * @return Zoninator_REST_Interfaces_Model_Declaration
	 */
	public function get_model_declaration() {
		return $this->model_declaration;
	}

	/**
	 * Creates a new Model From a Request
	 *
	 * @param array $data The request.
	 * @return Zoninator_REST_Model|WP_Error
	 */
	public function new_from_array( $data ) {
		$field_data = $this->map_data( $data, false );
		return $this->create_instance( $field_data )->sanitize();
	}

	/**
	 * Get field DTO Mappings
	 *
	 * @return array
	 */
	public function get_dto_field_mappings() {
		$mappings = array();
		foreach ( $this->get_field_declarations() as $field_declaration ) {
			/**
			 * Declaration
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			if ( ! $field_declaration->supports_output_type( 'json' ) ) {
				continue;
			}

			$mappings[ $field_declaration->get_data_transfer_name() ] = $field_declaration->get_name();
		}

		return $mappings;
	}

	/**
	 * Prepare the Model for Data Transfer
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 *
	 * @return array
	 */
	public function model_to_dto( $model ) {
		$result = array();
		foreach ( $this->get_dto_field_mappings() as $mapping_name => $field_name ) {
			$value                   = $model->get( $field_name );
			$result[ $mapping_name ] = $value;
		}

		return $result;
	}

	/**
	 * Get Name
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Check permissions
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $action The action.
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		return $this->permissions_provider->permissions_check( $request, $action );
	}

	/**
	 * Map data names
	 *
	 * @param array $data The data to map.
	 * @param bool  $updating Are we Updating.
	 * @return array
	 */
	private function map_data( $data, $updating = false ) {
		$request_data = array();
		$fields       = $this->get_field_declarations();
		foreach ( $fields as $field ) {
			/**
			 * Field
			 *
			 * @var Zoninator_REST_Field_Declaration $field Field.
			 */
			if ( $field->is_kind( Zoninator_REST_Field_Declaration::DERIVED ) ) {
				continue;
			}

			$dto_name   = $field->get_data_transfer_name();
			$field_name = $field->get_name();
			if ( isset( $data[ $dto_name ] ) && ! ( $updating && $field->is_primary() ) ) {
				$value                       = $data[ $dto_name ];
				$request_data[ $field_name ] = $value;
			}
		}

		return $request_data;
	}

	/**
	 * Initialize_field_map
	 *
	 * @param array $declared_field_builders Array<Mixtape_Model_Field_Declaration_Builder>.
	 *
	 * @return array
	 */
	private function initialize_field_map( $declared_field_builders ) {
		$fields = array();
		foreach ( $declared_field_builders as $field_builder ) {
			/**
			 * Builder
			 *
			 * @var Zoninator_REST_Field_Declaration $field Field Builder.
			 */
			$field                        = $field_builder->build();
			$fields[ $field->get_name() ] = $field;
		}

		return $fields;
	}
}
