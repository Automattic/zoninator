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
	 * Env.
	 *
	 * @var Zoninator_REST_Environment
	 */
	private $environment;
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
	private $bundle = null;

	/**
	 * Zoninator_REST_Controller_Bundle_Builder constructor.
	 *
	 * @param Zoninator_REST_Interfaces_Controller_Bundle|null $bundle Bundle.
	 */
	public function __construct( $bundle = null ) {
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
		$this->environment = $env;
		return $this;
	}

	/**
	 * Endpoint.
	 *
	 * Adds a new Zoninator_REST_Controller_Builder to our builders and returns it for further setup.
	 *
	 * @param null|Zoninator_REST_Interfaces_Controller $controller_object The (optional) controller object.
	 * @return Zoninator_REST_Controller_Bundle_Builder $this
	 * @throws Zoninator_REST_Exception Exception.
	 */
	public function add_endpoint( $controller_object = null ) {
		Zoninator_REST_Expect::is_a( $controller_object, 'Zoninator_REST_Interfaces_Controller' );
		$this->endpoint_builders[] = $controller_object;
		return $this;
	}
}
