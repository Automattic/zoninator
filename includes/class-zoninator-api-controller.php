<?php
/**
 * @package Zoninator/Rest
 */

/**
 * Class Zoninator_Api_Controller
 */
class Zoninator_Api_Controller extends Zoninator_REST_Controller {
	const ZONE_ITEM_URL_REGEX = '/zones/(?P<zone_id>[\d]+)';
	const ZONE_ITEM_POSTS_URL_REGEX = '/zones/(?P<zone_id>[\d]+)/posts';
	const ZONE_ITEM_POSTS_POST_REGEX = '/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)';

	const INVALID_ZONE_ID = 'invalid-zone-id';
	const INVALID_POST_ID = 'invalid-post-id';
	const ZONE_ID_POST_ID_REQUIRED = 'zone-id-post-id-required';
	const ZONE_ID_POST_IDS_REQUIRED = 'zone-id-post-ids-required';
	const ZONE_ID_REQUIRED = 'zone-id-required';
	const ZONE_FEED_ERROR = 'zone-feed-error';
	const TERM_REQUIRED = 'term-required';
	const PERMISSION_DENIED = 'permission-denied';
	const ZONE_NOT_FOUND = 'zone-not-found';
	const POST_NOT_FOUND = 'post-not-found';
	const INVALID_ZONE_SETTINGS = 'invalid-zone-settings';
	/**
	 * Instance
	 *
	 * @var Zoninator
	 */
	private $instance;
	/**
	 * Key Value Translation array
	 *
	 * @var array
	 */
	private $translations;

	/**
	 * Zoninator_Api_Controller constructor.
	 *
	 * @param string    $base Base.
	 * @param Zoninator $instance Instance.
	 */
	function __construct( $instance ) {
		$this->instance = $instance;
		$this->base = '/';
	}

	/**
	 * Set up this controller
	 */
	function setup() {
		$this->translations = array(
			self::ZONE_NOT_FOUND           => __( 'Zone not found', 'zoninator' ),
			self::INVALID_POST_ID          => __( 'Invalid post id', 'zoninator' ),
			self::INVALID_ZONE_ID          => __( 'Zone not found', 'zoninator' ),
			self::ZONE_ID_POST_ID_REQUIRED => __( 'post id and zone id required', 'zoninator' ),
		);

		$this->add_route( 'zones' )
			->add_action(
				$this->action( 'index', 'get_zones' )
					->permissions( 'get_zones_permissions_check' )
			)
			->add_action(
				$this->action( 'create', 'create_zone' )
					->permissions( 'add_zone_permissions_check' )
					->args( '_params_for_create_zone' )
			);

		$this->add_route( 'zones/(?P<zone_id>[\d]+)' )
			->add_action(
				$this->action( 'update', 'update_zone' )
					->permissions( 'update_zone_permissions_check' )
					->args( '_params_for_update_zone' )
			)
			->add_action(
				$this->action( 'delete', 'delete_zone' )
					->permissions( 'update_zone_permissions_check' )
			);

		$this->add_route( 'zones/(?P<zone_id>[\d]+)/posts' )
			->add_action(
				$this->action( 'index', 'get_zone_posts' )
					->permissions( 'get_zone_posts_permissions_check' )
					->args( '_get_zone_id_param' )
			)
			->add_action(
				$this->action( 'update', 'update_zone_posts' )
					->permissions( 'update_zone_permissions_check' )
					->args( '_get_zone_post_rest_route_params' )
			);

		$this->add_route( 'zones/(?P<zone_id>[\d]+)/lock' )
			->add_action(
				$this->action( 'update', 'zone_update_lock' )
					->permissions( 'update_zone_permissions_check' )
					->args( '_get_zone_id_param' )
			);
	}

	/**
	 * Get the list of all zones
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function get_zones( $request ) {
		$results = $this->instance->get_zones();

		if ( is_wp_error( $results ) ) {
			return $this->bad_request( array(
				'message' => $results->get_error_message(),
			) );
		}

		$zones = array_map( array( $this, '_filter_zone_properties' ), $results );

		return $this->ok( $zones );
	}

	/**
	 * Create a Zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function create_zone( $request ) {
		$name = $this->_get_param( $request, 'name', '' );
		$slug = $this->_get_param( $request, 'slug', $name );
		$description = $this->_get_param( $request, 'description', '' );

		$result = $this->instance->insert_zone( $slug, $name, array(
			'description' => $description,
		) );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( array(
				'message' => $result->get_error_message(),
			) );
		}

		$zone = $this->instance->get_zone( $result[ 'term_id' ] );

		return $this->created( $this->_filter_zone_properties( $zone ) );
	}

	/**
	 * Update zone details
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function update_zone( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
		$name = $this->_get_param( $request, 'name', '' );
		$slug = $this->_get_param( $request, 'slug', '' );
		$description = $this->_get_param( $request, 'description', '', 'strip_tags' );

		$zone = $this->instance->get_zone( $zone_id );
		$update_params = array();

		if ( ! $zone ) {
			return $this->not_found( $this->translations[ self::INVALID_ZONE_ID ] );
		}

		if ( $name ) {
			$update_params[ 'name' ] = $name;
		}

		if ( $slug ) {
			$update_params[ 'slug' ] = $slug;
		}

		if ( $description ) {
			$update_params[ 'details' ] = array( 'description' => $description );
		}

		$result = $this->instance->update_zone( $zone, $update_params );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( array(
				'message' => $result->get_error_message(),
			) );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Delete a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function delete_zone( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

		$zone = $this->instance->get_zone( $zone_id );

		if ( ! $zone ) {
			return $this->not_found( $this->translations[ self::INVALID_ZONE_ID ] );
		}

		$result = $this->instance->delete_zone( $zone );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( array(
				'message' => $result->get_error_message(),
			) );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Get zone posts
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_zone_posts( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

		if ( empty( $zone_id ) || false === $this->instance->get_zone( $zone_id ) ) {
			return $this->not_found( $this->translations[ self::INVALID_ZONE_ID ] );
		}

		$results = apply_filters( 'zoninator_get_zone_posts_api' , $this->instance->get_zone_feed( $zone_id ) );

		if ( is_wp_error( $results ) ) {
			return $this->_bad_request( self::ZONE_FEED_ERROR, $results->get_error_message() );
		}

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Sets the posts for a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.]
	 * @return WP_Error|WP_REST_Response
	 */
	function update_zone_posts( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
		$post_ids = $this->_get_param( $request, 'post_ids', array() );

		if ( ! $this->instance->get_zone( $zone_id ) ) {
			return $this->not_found( $this->translations[ self::INVALID_ZONE_ID ] );
		}

		$posts = array_map( 'get_post', $post_ids );

		if ( count( $posts ) !== count( array_filter( $posts ) ) ) {
			return $this->bad_request( array(
				'message' => $this->translations[ self::INVALID_POST_ID ],
			) );
		}

		$result = $this->instance->add_zone_posts( $zone_id, $posts );

		if ( is_wp_error( $result ) ) {
			return $this->respond( $result, 500 );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Update the zone's lock
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function zone_update_lock( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
		if ( ! $zone_id ) {
			return $this->_bad_request(self::ZONE_ID_REQUIRED, __('zone id required', 'zoninator'));
		}

		$zone = $this->instance->get_zone( $zone_id );
		if ( ! $zone ) {
			return $this->not_found( $this->translations[ self::INVALID_ZONE_ID ] );
		}

		$zone_locked = $this->instance->is_zone_locked( $zone );
		if ( $zone_locked ) {
			$locking_user = get_userdata( $zone_locked );
			return new WP_REST_Response( array(
				'zone_id' => $this->instance->get_zone_id( $zone ),
				'blocked' => true,
			), 400);
		}

		$this->instance->lock_zone( $zone_id );
		return new WP_REST_Response( array(
			'zone_id' => $this->instance->get_zone_id( $zone ),
			'timeout' => $this->instance->zone_lock_period,
			'max_lock_period' => $this->instance->zone_max_lock_period,
		), 200 );
	}

	/**
	 * Check if a given request has access to the zones index.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_zones_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access to add new zones.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function add_zone_permissions_check( $request ) {
		return $this->_permissions_check( 'insert' );
	}

	/**
	 * Check if a given request has access to get zone posts.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_zone_posts_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access to update a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_zone_permissions_check( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
		return $this->_permissions_check( 'update', $zone_id );
	}

	public function is_numeric( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly.
		return is_numeric( $item );
	}

	public function is_numeric_array( $items ) {
		return count( $items ) === count( array_filter( $items, 'is_numeric') );
	}

	public function sanitize_string( $item ) {
		return htmlentities( stripslashes( $item ) );
	}

	/**
	 * @param WP_REST_Request $object
	 * @param $var
	 * @param string $default
	 * @param string $sanitize_callback
	 * @return array|mixed|null|string
	 */
	private function _get_param( $object, $var, $default = '', $sanitize_callback = '' ) {
		$value = $object->get_param( $var );
		$value = empty( $value ) ? $default : $value;


		if ( is_callable( $sanitize_callback ) ) {
			$value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	public function _params_for_create_zone() {
		return array(
			'name' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default' => '',
				'required' => false
			),
			'slug' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default' => '',
				'required' => false,
			),
			'description' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default' => '',
				'required' => false,
			)
		);
	}

	public function _params_for_update_zone() {
		return array(
			'name' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required' => false
			),
			'slug' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required' => false,
			),
			'description' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required' => false,
			)
		);
	}

	public function _get_zone_id_param() {
		return array(
			'zone_id' => array(
				'type'              => 'integer',
				'validate_callback' => array( $this, 'is_numeric' ),
				'sanitize_callback' => 'absint',
				'required'          => true
			)
		);
	}

	public function _get_zone_post_rest_route_params() {
		$zone_params = $this->_get_zone_id_param();
		return array_merge( array(
			'post_ids' => array(
				'type'              => 'array',
				'validate_callback' => array( $this, 'is_numeric_array' ),
				'required'          => true,
				'items'				=> array( 'type' => 'integer' ),
			),
		), $zone_params );
	}

	public function _filter_zone_properties( $zone ) {
		$data = $zone->to_array();

		return array(
			'term_id'		=> $data[ 'term_id' ],
			'slug'			=> $data[ 'slug' ],
			'name'			=> $data[ 'name' ],
			'description'	=> $data[ 'description' ],
		);
	}

	private function _bad_request($code, $message) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * @param $zone_id
	 * @return bool|WP_Error
	 */
	private function _permissions_check($action, $zone_id = null ) {
		if ( ! $this->instance->check( $action, $zone_id ) ) {
			return new WP_Error( self::PERMISSION_DENIED, __('Sorry, you\'re not supposed to do that...', 'zoninator' ) );
		}
		return true;
	}
}