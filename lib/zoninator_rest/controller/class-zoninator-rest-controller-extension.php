<?php
/**
 * Controller-like class used for extending existing object types (e.g. post)
 * With custom fields. Needs a model definition that will provide the extra fields.
 *
 * @package Zoninator_REST/Controller
 */

/**
 * Class Zoninator_REST_Controller_Extension
 */
class Zoninator_REST_Controller_Extension implements Zoninator_REST_Interfaces_Registrable {
	/**
	 * Environment
	 *
	 * @var Zoninator_REST_Environment
	 */
	private $environment;
	/**
	 * Object to extend
	 *
	 * @var string
	 */
	private $object_to_extend;
	/**
	 * Model def.
	 *
	 * @var Zoninator_REST_Model_Definition
	 */
	private $model_definition;
	/**
	 * Model Definition name, This should be a valid Model definition at registration time, otherwise register will throw
	 *
	 * @var string
	 */
	private $model_definition_name;

	/**
	 * Zoninator_REST_Controller_Extension constructor.
	 *
	 * @param string $object_to_extend Post type.
	 * @param string $model_definition_name Model Definition name.
	 */
	function __construct( $object_to_extend, $model_definition_name ) {
		$this->model_definition_name = $model_definition_name;
		$this->object_to_extend = $object_to_extend;
	}

	/**
	 * Register This Controller
	 *
	 * @param Zoninator_REST_Environment $environment The Environment to use.
	 * @throws Zoninator_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	function register( $environment ) {
		$this->environment = $environment;
		$this->model_definition = $this->environment->model( $this->model_definition_name );
		if ( ! $this->model_definition ) {
			return new WP_Error( 'model-not-found' );
		}
		$fields = $this->model_definition->get_fields();
		foreach ( $fields as $field ) {
			$this->register_field( $field );
		}

		return true;
	}

	/**
	 * Register Field
	 *
	 * @param Zoninator_REST_Field_Declaration $field Field.
	 */
	private function register_field( $field ) {
		register_rest_field( $this->object_to_extend, $field->get_data_transfer_name(), array(
			'get_callback' => $field->get_reader(),
			'update_callback' => $field->get_updater(),
			'schema' => $field->as_item_schema_property(),
		) );
	}
}
