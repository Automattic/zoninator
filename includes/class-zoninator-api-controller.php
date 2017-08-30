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

		$this->add_route( 'zones/(?P<zone_id>[\d]+)/posts' )
			->add_action( $this->action( 'index', 'get_zone_posts' )
				->permissions( 'get_zone_posts_permissions_check' )
				->args( '_get_zone_id_param' )
			)
			->add_action( $this->action( 'update', 'update_zone_posts' )
				->permissions( 'update_zone_permissions_check' )
				->args( '_get_zone_post_rest_route_params' )
			);

		$this->add_route( 'zones/(?P<zone_id>[\d]+)/lock' )
			->add_action( $this->action( 'update', 'zone_update_lock' )
				->permissions( 'update_zone_permissions_check' )
				->args( '_get_zone_id_param' ) );

		$this->add_route( 'posts/search' )
			->add_action( $this->action( 'index', 'search_posts' )
				->args( '_params_for_search_posts' ) );

		$this->add_route( 'posts/recent' )
			->add_action( $this->action( 'index', 'get_recent_posts' )
				->args( '_params_for_get_recent_posts' ) );
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
		$slug = $this->_get_param( $request, 'slug', '' );
		$description = $this->_get_param( $request, 'description', '', 'strip_tags' );

		$result = $this->instance->insert_zone( $slug, $name, array(
			'description' => $description,
		) );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( array(
				'message' => $result->get_error_message(),
			) );
		}

		$zone = $this->instance->get_zone( $result[ 'term_id' ] );

		return $this->created( $zone );
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

		$results = $this->instance->get_zone_feed( $zone_id );

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

		return $this->ok();
	}

	/**
	 * Update the zone's lock
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function zone_update_lock( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

		if (! $zone_id ) {
			return $this->_bad_request(self::ZONE_ID_REQUIRED, __('zone id required', 'zoninator'));
		}

		if ( ! $this->instance->is_zone_locked( $zone_id ) ) {
			$this->instance->lock_zone( $zone_id );
			return new WP_REST_Response(array(
				'zone_id' => $zone_id,
				'status' => 1,
			), 200);
		}

		return new WP_REST_Response(array(
			'zone_id' => $zone_id,
			'status' => 0), 400);
	}

	/**
	 * Search posts for "term"
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	function search_posts( $request ) {
		$search_filter_definition = $this->environment()->model( 'Zoninator_Api_Filter_Search' );
		$search_filter = $search_filter_definition->new_from_array( $request->get_params() );

		$validation_error = $search_filter->validate();
		if ( is_wp_error( $validation_error ) ) {
			return $this->respond( $validation_error, 400 );
		}

		$filter_cat = $search_filter->get( 'cat' );
		$filter_date = $search_filter->get( 'date' );

		$post_types = $this->instance->get_supported_post_types();
		$limit = $this->_get_param( $request, 'limit', $this->instance->posts_per_page );

		if ( 0 >= $limit ) {
			$limit = $this->instance->posts_per_page;
		}

		$exclude = (array)$search_filter->get( 'exclude' );

		$args = apply_filters('zoninator_search_args', array(
			's' => $search_filter->get( 'term' ),
			'post__not_in' => $exclude,
			'posts_per_page' => $limit,
			'post_type' => $post_types,
			'post_status' => array('publish', 'future'),
			'order' => 'DESC',
			'orderby' => 'post_date',
			'suppress_filters' => true,
		));

		if ( $this->instance->_validate_category_filter( $filter_cat ) ) {
			$args['cat'] = $filter_cat;
		}

		if ( $this->instance->_validate_date_filter( $filter_date ) ) {
			$filter_date_parts = explode( '-', $filter_date );
			$args['year'] = $filter_date_parts[0];
			$args['monthnum'] = $filter_date_parts[1];
			$args['day'] = $filter_date_parts[2];
		}

		$query = new WP_Query($args);

		$stripped_posts = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$stripped_posts[] = apply_filters( 'zoninator_search_results_post', array(
					'title' => !empty( $post->post_title ) ? $post->post_title : __( '(no title)', 'zoninator' ),
					'post_id' => $post->ID,
					'date' => get_the_time( get_option( 'date_format' ), $post ),
					'post_type' => $post->post_type,
					'post_status' => $post->post_status,
				), $post );
			}
		}

		return new WP_REST_Response( $stripped_posts, 200 );
	}

	/**
	 * Get recent posts, excluding the ones that are already part of the zone provided
	 * Recent posts can be filtered by category and date
	 *
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_recent_posts( $request ) {
		$cat = $this->_get_param( $request, 'cat', '', 'absint' );
		$date = $this->_get_param( $request, 'date', '', 'striptags' );
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

		$limit = $this->instance->posts_per_page;
		$post_types = $this->instance->get_supported_post_types();
		$zone_posts = $this->instance->get_zone_posts( $zone_id );
		$zone_post_ids = wp_list_pluck( $zone_posts, 'ID' );

		$http_status = 200;

		if ( is_wp_error( $zone_posts ) ) {
			$status = 0;
			$content = $zone_posts->get_error_message();
			$http_status = 500;
		} else {
			$args = apply_filters( 'zoninator_recent_posts_args', array(
				'posts_per_page' => $limit,
				'order' => 'DESC',
				'orderby' => 'post_date',
				'post_type' => $post_types,
				'ignore_sticky_posts' => true,
				'post_status' => array( 'publish', 'future' ),
				'post__not_in' => $zone_post_ids,
			) );

			if ( $this->instance->_validate_category_filter( $cat ) ) {
				$args['cat'] = $cat;
			}

			if ( $this->instance->_validate_date_filter( $date ) ) {
				$filter_date_parts = explode( '-', $date );
				$args['year'] = $filter_date_parts[0];
				$args['monthnum'] = $filter_date_parts[1];
				$args['day'] = $filter_date_parts[2];
			}

			$content = '';
			$recent_posts = get_posts( $args );
			foreach ( $recent_posts as $post ) {
				$content .= sprintf('<option value="%d">%s</option>', $post->ID, get_the_title($post->ID) . ' (' . $post->post_status . ')');
			}

			wp_reset_postdata();
			$status = 1;
		}

		if ( ! $content ) {
			$empty_label = __( 'No results found', 'zoninator' );
		} elseif ( $cat ) {
			$empty_label = sprintf(__('Choose post from %s', 'zoninator'), get_the_category_by_ID($cat));
		} else {
			$empty_label = __( 'Choose a post', 'zoninator' );
		}

		$content = '<option value="">' . esc_html( $empty_label ) . '</option>' . $content;

		$response = new WP_REST_Response( array(
			'zone_id' => $zone_id,
			'content' => $content,
			'status' => $status ) );
		$response->set_status( $http_status );
		return $response;
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
	 * Check if a given request has access to remove a post from zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function remove_post_from_zone_permissions_check( $request ) {
		$zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
		return $this->_permissions_check( 'update', $zone_id );
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
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly
		return is_numeric( $item );
	}

	public function is_numeric_array( $items ) {
		return count( $items ) === count( array_filter( $items, 'is_numeric') );
	}

	public function strip_slashes( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use stripslashes directly
		return stripslashes( $item );
	}

	public function strip_tags( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use strip_tags directly
		return strip_tags( $item );
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
		$value = ( $value !== null ) ? $value : $default;


		if ( is_callable( $sanitize_callback ) ) {
			$value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	public function _params_for_create_zone() {
		return array(
			'name' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'strip_slashes' ),
				'default' => '',
				'required' => false
			),
			'slug' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'strip_slashes' ),
				'required' => true,
			),
			'description' => array(
				'type' => 'string',
				'sanitize_callback' => array( $this, 'strip_tags' ),
				'default' => '',
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
				'required'          => true
			),
		), $zone_params );
	}

	public function _params_for_get_recent_posts()
	{
		$zone_params = $this->_get_zone_id_param();
		return array_merge(array(
			'cat' => array(
				'description'       => __( 'only recent posts from this category id', 'zoninator' ),
				'type'              => 'integer',
				'validate_callback' => array( $this, 'is_numeric' ),
				'sanitize_callback' => 'absint',
				'default'           => 0,
				'required'          => false
			),
			'date' => array(
				'description'       => __( 'only get posts after this date (format YYYY-mm-dd)', 'zoninator' ),
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'strip_slashes' ),
				'default'           => '',
				'required'          => false
			)
		), $zone_params);
	}

	public function _params_for_search_posts() {
		$search_filter = $this->environment()->model( 'Zoninator_Api_Filter_Search' );
		$schema_converter = new Zoninator_Api_Schema_Converter();
		return $schema_converter->as_args( $search_filter );
//		return array(
//			'term' => array(
//				'description'       => __( 'search term', 'zoninator' ),
//				'type'              => 'string',
//				'sanitize_callback' => array( $this, 'strip_slashes' ),
//				'default'           => '',
//				'required'          => true
//			),
//			'cat' => array(
//				'description'       => __( 'filter by category', 'zoninator' ),
//				'type'              => 'integer',
//				'validate_callback' => array( $this, 'is_numeric' ),
//				'sanitize_callback' => 'absint',
//				'default'           => 0,
//				'required'          => false
//			),
//			'date' => array(
//				'description'       => __( 'only get posts after this date (format YYYY-mm-dd)', 'zoninator' ),
//				'type'              => 'string',
//				'sanitize_callback' => array( $this, 'strip_slashes' ),
//				'default'           => '',
//				'required'          => false
//			),
//			'limit' => array(
//				'description'       => __( 'limit results', 'zoninator' ),
//				'type'              => 'integer',
//				'sanitize_callback' => 'absint',
//				'default'           => $this->instance->posts_per_page,
//				'required'          => false
//			),
//			'exclude' => array(
//				'description'       => __( 'post_ids to exclude', 'zoninator' ),
//				'required'          => false
//			)
//		);
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