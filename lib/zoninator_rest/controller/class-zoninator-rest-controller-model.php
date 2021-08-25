<?php
/**
 * A Controller that is related to a Model Declaration.
 *
 * @package Zoninator_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Controller_Model
 * Knows about models
 */
class Zoninator_REST_Controller_Model extends Zoninator_REST_Controller implements Zoninator_REST_Interfaces_Controller {

	/**
	 * The Factory
	 *
	 * @var Zoninator_REST_Model
	 */
	protected $model_prototype;

	/**
	 * The data Store
	 *
	 * @var Zoninator_REST_Interfaces_Data_Store
	 */
	protected $model_data_store;

	/**
	 * Model Definition Name
	 *
	 * @var string
	 */
	private $model_class_name;

	/**
	 * Zoninator_REST_Controller_Model constructor.
	 *
	 * @param string $base The baser.
	 * @param string $model_class_name A Definition or a definition name.
	 */
	public function __construct( $base, $model_class_name ) {
		$this->base             = $base;
		$this->model_class_name = $model_class_name;
	}

	/**
	 * Get our model factory
	 *
	 * @return Zoninator_REST_Model
	 */
	protected function get_model_prototype() {
		return $this->model_prototype;
	}

	/**
	 * Register this controller, initialize model-related object fields.
	 *
	 * @param Zoninator_REST_Controller_Bundle $bundle The bundle to use.
	 * @param Zoninator_REST_Environment       $environment The Environment.
	 *
	 * @throws Zoninator_REST_Exception If an invalid model is provided.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	public function register( $bundle, $environment ) {
		$this->model_prototype  = $environment->model( $this->model_class_name );
		$this->model_data_store = $this->model_prototype->get_data_store();
		return parent::register( $bundle, $environment );
	}

	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * In our case, it gets fields/types from our definition's declared fields.
	 *
	 * @access public
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$model_definition = $this->get_model_prototype();
		$fields           = $model_definition->get_fields();
		$properties       = array();
		$required         = array();
		foreach ( $fields as $field_declaration ) {
			/**
			 * Our declaration
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			$properties[ $field_declaration->get_data_transfer_name() ] = $field_declaration->as_item_schema_property();
			if ( $field_declaration->is_required() ) {
				$required[] = $field_declaration->get_data_transfer_name();
			}
		}
		$schema = array(
			'$schema'    => 'http://json-schema.org/schema#',
			'title'      => $model_definition->get_name(),
			'type'       => 'object',
			'properties' => (array) apply_filters( 'mixtape_rest_api_schema_properties', $properties, $this->get_model_prototype() ),
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $this->add_additional_fields_schema( $schema );
	}

	/**
	 * Get Model DataStore
	 *
	 * @return Zoninator_REST_Interfaces_Data_Store
	 */
	protected function get_model_data_store() {
		return $this->model_data_store;
	}

	/**
	 * Generic Permissions Check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $action One of (index, show, create, update, delete).
	 * @return bool
	 */
	public function permissions_check( $request, $action = 'any' ) {
		return $this->get_model_prototype()->permissions_check( $request, $action );
	}

	/**
	 * Prepare Entity to be a DTO
	 *
	 * @param array|Zoninator_REST_Model_Collection|Zoninator_REST_Interfaces_Model $entity The Entity.
	 * @return array
	 */
	protected function prepare_dto( $entity ) {
		if ( is_a( $entity, 'Zoninator_REST_Model_Collection' ) ) {
			$results = array();
			foreach ( $entity->get_items() as $model ) {
				$results[] = $this->model_to_dto( $model );
			}
			return $results;
		}

		if ( is_a( $entity, 'Zoninator_REST_Interfaces_Model' ) ) {
			return $this->model_to_dto( $entity );
		}

		return $entity;
	}

	/**
	 * Map a model to a Data Transfer Object (plain array)
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The Model.
	 * @return array
	 */
	protected function model_to_dto( $model ) {
		return $model->to_dto();
	}
}
