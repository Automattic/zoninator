<?php
/**
 * A Route that is part of a controller.
 *
 * @package Zoninator_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Controller_Route
 */
class Zoninator_REST_Controller_Route {

	/**
	 * Our pattern
	 *
	 * @var string
	 */
	private $pattern;

	/**
	 * Our Handlers
	 *
	 * @var array
	 */
	private $actions = array();

	/**
	 * Zoninator_REST_Controller_Route constructor.
	 *
	 * @param Zoninator_REST_Controller $controller A Controller.
	 * @param string                    $pattern Pattern.
	 */
	public function __construct( $controller, $pattern ) {
		$this->pattern = $pattern;
	}

	/**
	 * Add/Get an action
	 *
	 * @param Zoninator_REST_Controller_Action $action Action.
	 *
	 * @return Zoninator_REST_Controller_Route
	 */
	public function add_action( $action ) {
		$this->actions[ $action->name() ] = $action;
		return $this;
	}

	/**
	 * Gets Route info to use in Register rest route.
	 *
	 * @throws Zoninator_REST_Exception If invalid callable.
	 * @return array
	 */
	public function as_array() {
		$result            = array();
		$result['pattern'] = $this->pattern;
		$result['actions'] = array();
		foreach ( $this->actions as $route_action ) {
			/**
			 * The route action.
			 *
			 * @var Zoninator_REST_Controller_Action $route_action
			 */
			$result['actions'][] = $route_action->as_array();
		}

		return $result;
	}
}
