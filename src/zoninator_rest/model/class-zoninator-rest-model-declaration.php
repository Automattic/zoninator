<?php
/**
 * Base Class for Creating Declarations
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Model_Declaration
 */
class Zoninator_REST_Model_Declaration implements Zoninator_REST_Interfaces_Model_Declaration {
	/**
	 * The Model Definition
	 *
	 * @var Zoninator_REST_Model_Definition
	 */
	private $model_definition;

	/**
	 * Set the definition
	 *
	 * @param Zoninator_REST_Model_Definition $def The def.
	 *
	 * @return Zoninator_REST_Interfaces_Model_Declaration $this
	 */
	public function set_definition( $def ) {
		$this->model_definition = $def;
		return $this;
	}

	/**
	 * Get definition.
	 *
	 * @return Zoninator_REST_Model_Definition
	 */
	public function definition() {
		return $this->model_definition;
	}

	/**
	 * Declare fields
	 *
	 * @param Zoninator_REST_Environment $env The Environment.
	 *
	 * @return void
	 * @throws Zoninator_REST_Exception Override this.
	 */
	public function declare_fields( $env ) {
		throw new Zoninator_REST_Exception( 'Override me: ' . __FUNCTION__ );
	}

	/**
	 * Get the id
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 *
	 * @return mixed|null
	 */
	public function get_id( $model ) {
		return $model->get( 'id' );
	}

	/**
	 * Set the id
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The model.
	 * @param mixed                           $new_id The new id.
	 *
	 * @return mixed|null
	 */
	public function set_id( $model, $new_id ) {
		return $model->set( 'id', $new_id );
	}

	/**
	 * Call a method.
	 *
	 * @param string $method The method.
	 * @param array  $args The args.
	 *
	 * @return mixed
	 * @throws Zoninator_REST_Exception Throw if method nonexistent.
	 */
	public function call( $method, $args = array() ) {
		if ( is_callable( $method ) ) {
			return $this->perform_call( $method, $args );
		}

		Zoninator_REST_Expect::that( method_exists( $this, $method ), $method . ' does not exist' );
		return $this->perform_call( array( $this, $method ), $args );
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return strtolower( get_class( $this ) );
	}

	/**
	 * Perform call
	 *
	 * @param mixed $a_callable A Callable.
	 * @param array $args The args.
	 *
	 * @return mixed
	 */
	private function perform_call( $a_callable, $args ) {
		return call_user_func_array( $a_callable, $args );
	}
}
