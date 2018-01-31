<?php
/**
 * Model Declarations
 *
 * Extending Models: the Declaration
 *
 * The preferred way to customise the Behaviour of a Model is to provide it
 * With a class that Implements this Interface.
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface Mixtape_Interfaces_Model_Declaration
 */
interface Zoninator_REST_Interfaces_Model_Declaration {

	/**
	 * Set this Declaration's Definition
	 *
	 * @param Zoninator_REST_Model_Definition $def The definition.
	 * @return mixed
	 */
	function set_definition( $def );

	/**
	 * Get this Declaration's Definition
	 *
	 * @return Zoninator_REST_Model_Definition
	 */
	function definition();

	/**
	 * Declare the fields of our Model.
	 *
	 * @param Zoninator_REST_Environment $environment The Environment.
	 * @return array list of Mixtape_Model_Field_Declaration
	 */
	function declare_fields( $environment );

	/**
	 * Call a method
	 *
	 * @param string $method The method.
	 * @param array  $args The args.
	 * @return mixed
	 */
	function call( $method, $args = array());

	/**
	 * Get this model's unique identifier
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 * @return mixed
	 */
	function get_id( $model );

	/**
	 * Set this model's unique identifier
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 * @param mixed               $id The id.
	 *
	 * @return Zoninator_REST_Interfaces_Model The model.
	 */
	function set_id( $model, $id );

	/**
	 * Get the name
	 *
	 * @return string This declaration's name.
	 */
	function get_name();

}
