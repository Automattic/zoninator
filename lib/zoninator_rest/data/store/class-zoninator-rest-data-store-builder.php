<?php
/**
 * Data Store Builder
 *
 * Builder assumes that the datat store class is compatible with Abstract
 *
 * @package Zoninator_REST/Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Data_Store_Builder
 */
class Zoninator_REST_Data_Store_Builder {

	/**
	 * Args.
	 *
	 * @var array
	 */
	private $args = array();

	/**
	 * The Model Class.
	 *
	 * @var string
	 */
	private $store_class = 'Zoninator_REST_Data_Store_CustomPostType';

	/**
	 * Definition
	 *
	 * @var Zoninator_REST_Model_Definition
	 */
	private $model_definition;

	/**
	 * With class
	 *
	 * @param string $data_store_class Class.
	 * @return Zoninator_REST_Data_Store_Builder $this
	 * @throws Zoninator_REST_Exception If Class invalid.
	 */
	public function with_class( $data_store_class ) {
		$implements_data_store = in_array( 'Zoninator_REST_Interfaces_Data_Store', class_implements( $data_store_class ), true );
		Zoninator_REST_Expect::that( $implements_data_store, $data_store_class . ' should be a ' . $data_store_class );
		$this->store_class = $data_store_class;
		return $this;
	}

	/**
	 * Set Args
	 *
	 * @param array $args Args.
	 * @return Zoninator_REST_Data_Store_Builder $this
	 */
	public function with_args( $args ) {
		$this->args = $args;
		return $this;
	}

	/**
	 * Set Model Definition
	 *
	 * @param string|Zoninator_REST_Model_Definition $model_definition Def.
	 * @return Zoninator_REST_Data_Store_Builder $this
	 */
	public function with_model_definition( $model_definition ) {
		$this->model_definition = $model_definition;
		return $this;
	}

	/**
	 * Build
	 *
	 * @return Zoninator_REST_Interfaces_Data_Store
	 */
	public function build() {
		$store_class = $this->store_class;
		return new $store_class( $this->model_definition, $this->args );
	}
}
