<?php
/**
 * Model
 *
 * This is the model.
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Mixtape_Interfaces_Model
 */
interface Zoninator_REST_Interfaces_Model {
	/**
	 * Get this model's unique identifier
	 *
	 * @return mixed a unique identifier
	 */
	public function get_id();


	/**
	 * Set this model's unique identifier
	 *
	 * @param mixed $new_id The new Id.
	 * @return Zoninator_REST_Interfaces_Model $model This model.
	 */
	public function set_id( $new_id );

	/**
	 * Get a field for this model
	 *
	 * @param string $field_name The field name.
	 * @param array  $args The args.
	 *
	 * @return mixed|null
	 */
	public function get( $field_name, $args = array() );

	/**
	 * Set a field for this model
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The value.
	 *
	 * @return Zoninator_REST_Interfaces_Model $this;
	 */
	public function set( $field, $value );

	/**
	 * Check if this model has a field
	 *
	 * @param string $field The field name.
	 *
	 * @return bool
	 */
	public function has( $field );

	/**
	 * Validate this Model instance.
	 *
	 * @throws Zoninator_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	public function validate();

	/**
	 * Sanitize this Model's field values
	 *
	 * @throws Zoninator_REST_Exception Throws.
	 *
	 * @return Zoninator_REST_Interfaces_Model
	 */
	public function sanitize();

	/**
	 * Get this model class fields
	 *
	 * @param null|string $filter_by_type The field type.
	 * @return array
	 */
	public function get_fields( $filter_by_type = null );

	/**
	 * Get this model's data store
	 *
	 * @return array
	 */
	public function get_data_store();

	/**
	 * Set this model's data store (statically, all models of that class get the same one)
	 *
	 * @param Zoninator_REST_Interfaces_Data_Store $data_store A builder or a Data store.
	 * @throws Zoninator_REST_Exception Throws when Data Store Invalid.
	 */
	public function with_data_store( $data_store );

	/**
	 * Get this model's environment
	 *
	 * @return array
	 */
	public function get_environment();

	/**
	 * Set this model's environment
	 *
	 * @param Zoninator_REST_Environment $environment The Environment.
	 * @throws Zoninator_REST_Exception If an Zoninator_REST_Environment is not provided.
	 */
	public function with_environment( $environment );

	/**
	 * Declare the fields of our Model.
	 *
	 * @return array list of Zoninator_REST_Field_Declaration
	 */
	public function declare_fields();

	/**
	 * Prepare this for data transfer
	 *
	 * @return mixed
	 */
	public function to_dto();

	/**
	 * Update from array
	 *
	 * @param array $data The Data.
	 * @param bool  $updating Is this an update.
	 *
	 * @return mixed
	 */
	public function update_from_array( $data, $updating = false );

	/**
	 * Transform Model to raw data array
	 *
	 * @param null|string $field_type Type.
	 *
	 * @return array
	 */
	public function serialize( $field_type = null );
}
