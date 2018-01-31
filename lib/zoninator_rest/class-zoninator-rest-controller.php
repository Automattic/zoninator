<?php
/**
 * Controller
 *
 * @package Mixtape/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Controller
 */
class Zoninator_REST_Controller extends WP_REST_Controller implements Zoninator_REST_Interfaces_Controller {
	const HTTP_CREATED     = 201;
	const HTTP_OK          = 200;
	const HTTP_BAD_REQUEST = 400;
	const HTTP_NOT_FOUND   = 404;

	/**
	 * The bundle this belongs to.
	 *
	 * @var Zoninator_REST_Controller_Bundle
	 */
	protected $controller_bundle;

	/**
	 * The endpoint base (e.g. /users). Override in subclasses.
	 *
	 * @var string
	 */
	protected $base = null;
	/**
	 * Our Handlers
	 *
	 * @var array
	 */
	private $routes = array();

	/**
	 * Optional, an enviromnent.
	 *
	 * @var null|Zoninator_REST_Environment
	 */
	protected $environment = null;

	/**
	 * Zoninator_REST_Rest_Api_Controller constructor.
	 */
	public function __construct() {
	}

	/**
	 * Set Controller Bundle
	 *
	 * @param Zoninator_REST_Controller_Bundle $controller_bundle Controller Bundle this belongs to.
	 *
	 * @return Zoninator_REST_Controller $this
	 */
	public function set_controller_bundle( $controller_bundle ) {
		$this->controller_bundle = $controller_bundle;
		return $this;
	}

	/**
	 * Set the Environment for this Controller.
	 *
	 * @param Zoninator_REST_Environment|null $environment The Environment.
	 * @return Zoninator_REST_Controller
	 */
	public function set_environment( $environment ) {
		$this->environment = $environment;
		return $this;
	}

	/**
	 * Register This Controller
	 *
	 * @param Zoninator_REST_Controller_Bundle $bundle The bundle to register with.
	 * @param Zoninator_REST_Environment       $environment The Environment to use.
	 * @throws Zoninator_REST_Exception Throws.
	 *
	 * @return bool|WP_Error true if valid otherwise error.
	 */
	public function register( $bundle, $environment ) {
		$this->set_controller_bundle( $bundle );
		$this->set_environment( $environment );
		$this->setup();
		Zoninator_REST_Expect::that( ! empty( $this->base ), 'Need to put a string with a backslash in $base' );
		$prefix = $this->controller_bundle->get_prefix();
		foreach ( $this->routes as $pattern => $route ) {
			/**
			 * The route we want to register.
			 *
			 * @var Zoninator_REST_Controller_Route $route
			 */
			$params = $route->as_array();
			$result = register_rest_route( $prefix, $this->base . $params['pattern'], $params['actions'] );
			if ( false === $result ) {
				// For now we throw on error, maybe we just need to warn though.
				throw new Zoninator_REST_Exception( 'Registration failed' );
			}
		}

		return true;
	}

	/**
	 * Create Action
	 *
	 * @param string                     $action_name Action Name.
	 * @param null|string|array|callable $callback Callback.
	 * @return Zoninator_REST_Controller_Action
	 */
	public function action( $action_name, $callback = null ) {
		$route_action = new Zoninator_REST_Controller_Action( $this, $action_name );
		if ( null !== $callback ) {
			$route_action->callback( $callback );
		}

		return $route_action;
	}

	/**
	 * Do any additional Configuration. Runs inside register before any register_rest_route
	 *
	 * This is a good place for overriding classes to define routes and handlers
	 */
	protected function setup() {
	}

	/**
	 * Succeed
	 *
	 * @param array $data The dto.
	 *
	 * @return WP_REST_Response
	 */
	public function ok( $data ) {
		return $this->respond( $data, self::HTTP_OK );
	}

	/**
	 * Created
	 *
	 * @param array $data The dto.
	 *
	 * @return WP_REST_Response
	 */
	public function created( $data ) {
		return $this->respond( $data, self::HTTP_CREATED );
	}

	/**
	 * Bad request
	 *
	 * @param array|WP_Error $data The dto.
	 *
	 * @return WP_REST_Response
	 */
	public function bad_request( $data ) {
		return $this->respond( $data, self::HTTP_BAD_REQUEST );
	}

	/**
	 * Not Found (404)
	 *
	 * @param string $message The message.
	 *
	 * @return WP_REST_Response
	 */
	public function not_found( $message ) {
		return $this->respond( array(
			'message' => $message,
		), self::HTTP_NOT_FOUND );
	}

	/**
	 * Respond
	 *
	 * @param array|WP_REST_Response|WP_Error|mixed $data The thing.
	 * @param int                                   $status The Status.
	 *
	 * @return mixed|WP_REST_Response
	 */
	public function respond( $data, $status ) {
		if ( is_a( $data, 'WP_REST_Response' ) ) {
			return $data;
		}

		return new WP_REST_Response( $data, $status );
	}

	/**
	 * Permissions for get_items
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function index_permissions_check( $request ) {
		return $this->permissions_check( $request, 'index' );
	}

	/**
	 * Permissions for get_item
	 *
	 * @param WP_REST_Request $request The request.
	 * @return bool
	 */
	public function show_permissions_check( $request ) {
		return $this->permissions_check( $request, 'show' );
	}

	/**
	 * Permissions for create_item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function create_permissions_check( $request ) {
		return $this->permissions_check( $request, 'create' );
	}

	/**
	 * Permissions for update_item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function update_permissions_check( $request ) {
		return $this->permissions_check( $request, 'update' );
	}

	/**
	 * Permissions for delete
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function delete_permissions_check( $request ) {
		return $this->permissions_check( $request, 'delete' );
	}

	/**
	 * Generic Permissions Check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $action One of (index, show, create, update, delete, any).
	 * @return bool
	 */
	function permissions_check( $request, $action = 'any' ) {
		return true;
	}

	/**
	 * Add a route
	 *
	 * @param string $pattern The route pattern (e.g. '/').
	 * @return Zoninator_REST_Controller_Route
	 */
	function add_route( $pattern = '' ) {
		$route = new Zoninator_REST_Controller_Route( $this, $pattern );
		$this->routes[ $pattern ] = $route;
		return $this->routes[ $pattern ];
	}

	/**
	 * Get Environment
	 *
	 * @return Zoninator_REST_Environment
	 */
	protected function environment() {
		return $this->environment;
	}

	/**
	 * Get base url
	 *
	 * @return string
	 */
	function get_base() {
		return rest_url( $this->controller_bundle->get_prefix() . $this->base );
	}
}
