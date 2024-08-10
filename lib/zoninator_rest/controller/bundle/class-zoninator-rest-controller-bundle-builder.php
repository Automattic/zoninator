<?php
/**
 * Build a Bundle
 *
 * @package Zoninator_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Controller_Bundle_Builder
 */
class Zoninator_REST_Controller_Bundle_Builder implements Zoninator_REST_Interfaces_Builder {

	/**
	 * Prefix.
	 *
	 * @var string
	 */
	private $bundle_prefix;
	/**
	 * Endpoint Builders.
	 *
	 * @var array
	 */
	private $endpoint_builders = array();
	/**
	 * Bundle.
	 *
	 * @var Zoninator_REST_Controller_Bundle|null
	 */
	private $bundle;

	/**
	 * Zoninator_REST_Controller_Bundle_Builder constructor.
	 *
	 * @param Zoninator_REST_Interfaces_Controller_Bundle|null $bundle Bundle.
	 */
	function __construct( $bundle = null ) {
		$this->bundle = $bundle;
	}

	/**
	 * Build it
	 *
	 * @return Zoninator_REST_Interfaces_Controller_Bundle
	 */
	public function build() {
		if ( is_a( $this->bundle, 'Zoninator_REST_Interfaces_Controller_Bundle' ) ) {
			return $this->bundle;
		}
		return new Zoninator_REST_Controller_Bundle( $this->bundle_prefix, $this->endpoint_builders );
	}

	/**
	 * Prefix.
	 *
	 * @param string $bundle_prefix Prefix.
	 * @return Zoninator_REST_Controller_Bundle_Builder $this
	 */
	public function with_prefix( $bundle_prefix ) {
		$this->bundle_prefix = $bundle_prefix;
		return $this;
	}

	/**
	 * Env.
	 *
	 * @param Zoninator_REST_Environment $env Env.
	 * @return Zoninator_REST_Controller_Bundle_Builder $this
	 */
	public function with_environment( $env ) {
		return $this;
	}

	/**
	 * Endpoint.
	 *
	 * Adds a new Zoninator_REST_Controller_Builder to our builders and returns it for further setup.
	 *
	 * @param null|Zoninator_REST_Interfaces_Controller $controller_object The (optional) controller object.
	 * @return Zoninator_REST_Controller_Bundle_Builder $this
	 */
	public function add_endpoint( $controller_object = null ) {
		Zoninator_REST_Expect::is_a( $controller_object, 'Zoninator_REST_Interfaces_Controller' );
		$this->endpoint_builders[] = $controller_object;
		return $this;
	}
}
