<?php
/**
 * Definition Builder
 *
 * @package Zoninator_REST/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Model_Definition_Builder
 */
class Zoninator_REST_Model_Definition_Builder implements Zoninator_REST_Interfaces_Builder {
	/**
	 * Declaration
	 *
	 * @var Zoninator_REST_Interfaces_Model_Declaration
	 */
	private $declaration;
	/**
	 * Data Store
	 *
	 * @var Zoninator_REST_Interfaces_Data_Store
	 */
	private $data_store;
	/**
	 * Environment
	 *
	 * @var Zoninator_REST_Environment
	 */
	private $environment;
	/**
	 * Permissions Provider
	 *
	 * @var Zoninator_REST_Interfaces_Permissions_Provider
	 */
	private $permissions_provider;

	/**
	 * Zoninator_REST_Model_Definition_Builder constructor.
	 */
	function __construct() {
		$this->with_data_store( new Zoninator_REST_Data_Store_Nil() )
			->with_permissions_provider( new Zoninator_REST_Permissions_Any() );
	}

	/**
	 * With Declaration
	 *
	 * @param Zoninator_REST_Interfaces_Model_Declaration|Zoninator_REST_Interfaces_Permissions_Provider $declaration D.
	 * @return Zoninator_REST_Model_Definition_Builder
	 */
	function with_declaration( $declaration ) {
		if ( is_string( $declaration ) && class_exists( $declaration ) ) {
			$declaration = new $declaration();
		}
		Zoninator_REST_Expect::is_a( $declaration, 'Zoninator_REST_Interfaces_Model_Declaration' );
		$this->declaration = $declaration;
		if ( is_a( $declaration, 'Zoninator_REST_Interfaces_Permissions_Provider' ) ) {
			$this->with_permissions_provider( $declaration );
		}
		return $this;
	}

	/**
	 * With Data Store
	 *
	 * @param null|Zoninator_REST_Interfaces_Builder $data_store Data Store.
	 *
	 * @return Zoninator_REST_Model_Definition_Builder $this
	 */
	function with_data_store( $data_store = null ) {
		$this->data_store = $data_store;
		return $this;
	}

	/**
	 * With Permissions Provider
	 *
	 * @param Zoninator_REST_Interfaces_Permissions_Provider $permissions_provider Provider.
	 */
	function with_permissions_provider( $permissions_provider ) {
		$this->permissions_provider = $permissions_provider;
	}

	/**
	 * With Environment
	 *
	 * @param Zoninator_REST_Environment $environment Environment.
	 *
	 * @return Zoninator_REST_Model_Definition_Builder $this
	 */
	function with_environment( $environment ) {
		$this->environment = $environment;
		return $this;
	}

	/**
	 * Build
	 *
	 * @return Zoninator_REST_Model_Definition
	 */
	function build() {
		return new Zoninator_REST_Model_Definition( $this->environment, $this->declaration, $this->data_store, $this->permissions_provider );
	}
}
