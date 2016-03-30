<?php


class Zoninator_Rest_Api_Controller {

    const ZONINATOR_NAMESPACE = 'zoninator';
    const API_VERSION = '1';

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

    /**
     * @var Zoninator_Zone_Gateway
     */
    private $_zone_gateway = null;

    /**
     * @var Zoninator_Permissions
     */
    private $_permissions = null;

    function __construct( $data_service, $permissions ) {
        $this->_zone_gateway = $data_service;
        $this->_permissions = $permissions;
    }

    function register_routes() {
        $full_namespace = self::ZONINATOR_NAMESPACE . '/v' . self::API_VERSION;

        register_rest_route( $full_namespace, self::ZONE_ITEM_POSTS_URL_REGEX, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_zone_posts' ),
                'permission_callback' => array( $this, 'get_zone_posts_permissions_check' ),
                'args'                => $this->_get_zone_id_param()
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'add_post_to_zone'),
                'permission_callback' => array( $this, 'add_post_to_zone_permissions_check' ),
                'args'                => $this->_get_zone_post_rest_route_params()
            )
        ) );

        register_rest_route( $full_namespace, self::ZONE_ITEM_POSTS_POST_REGEX, array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'remove_post_from_zone' ),
            'permission_callback' => array( $this, 'remove_post_from_zone_permissions_check' ),
            'args'                => $this->_get_zone_post_rest_route_params()
        ));

        register_rest_route( $full_namespace, self::ZONE_ITEM_POSTS_URL_REGEX . '/order', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'reorder_posts' ),
            'permission_callback' => array( $this, 'update_zone_permissions_check' ),
            'args'                => $this->_get_zone_id_param()
        ));

        register_rest_route( $full_namespace, self::ZONE_ITEM_URL_REGEX . '/lock', array(
            'methods'             => 'PUT',
            'callback'            => array($this, 'zone_update_lock'),
            'permission_callback' => array( $this, 'update_zone_permissions_check' ),
            'args'                => $this->_get_zone_id_param()
        ));

        register_rest_route( $full_namespace, '/posts/search', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array( $this, 'search_posts' ),
            'args'     => $this->_params_for_search_posts()
        ));

        register_rest_route( $full_namespace, '/posts/recent', array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_recent_posts'),
            'args'                => $this->_params_for_get_recent_posts()
        ));
    }

    /**
     * Add a post to zone
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    function add_post_to_zone($request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        $post_id = $this->_get_param( $request, 'post_id', 0, 'absint' );

        $post = get_post( $post_id );

        if ( ! $post ) {
            return $this->_bad_request(self::INVALID_POST_ID, __( 'invalid post id', self::ZONINATOR_NAMESPACE ));
        }

        $zone = $this->_zone_gateway->get_zone( $zone_id );

        if ( ! $zone ) {
            return $this->_bad_request(self::INVALID_ZONE_ID, __( 'invalid zone id', self::ZONINATOR_NAMESPACE ));
        }

        $result = $this->_zone_gateway->add_zone_posts( $zone_id, $post, true );

        if ( is_wp_error( $result ) ) {
            $status = 500;
            $content = $result->get_error_message();
        } else {
            $content = $this->_zone_gateway->get_admin_zone_post( $post, $zone );
            $status = 200;
        }

        $response = new WP_REST_Response( array(
            'zone_id' => $zone_id,
            'content' => $content,
            'status' => $status) );

        $response->set_status( $status );

        return $response;
    }

    /**
     * Delete one item from the collection.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    function remove_post_from_zone($request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        $post_id = $this->_get_param( $request, 'post_id', 0, 'absint' );

        if (!$zone_id || !$post_id ) {
            return $this->_bad_request( self::ZONE_ID_POST_ID_REQUIRED, __( 'post id and zone id required', self::ZONINATOR_NAMESPACE ) );
        }

        $result = $this->_zone_gateway->remove_zone_posts( $zone_id, $post_id );

        if ( is_wp_error( $result ) ) {
            $status = 500;
            $content = $result->get_error_message();
        } else {
            $status = 200;
            $content = '';
        }

        return new WP_REST_Response( array(
            'zone_id' => $zone_id,
            'post_id' => $post_id,
            'content' => $content,
            'status' => $status ), $status );
    }

    /**
     * Reorder posts for zone.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    function reorder_posts( $request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        $post_ids = (array) $this->_get_param( $request, 'posts', array(), 'absint' );

        if ( ! $zone_id || empty( $post_ids ) ) {
            return $this->_bad_request( self::ZONE_ID_POST_IDS_REQUIRED, __('post ids and zone id required', self::ZONINATOR_NAMESPACE) );
        }

        $result = $this->_zone_gateway->add_zone_posts( $zone_id, $post_ids, false );

        if (is_wp_error($result)) {
            $status = 0;
            $http_status = 500;
            $content = $result->get_error_message();
        } else {
            $status = 1;
            $http_status = 200;
            $content = '';
        }

        return new WP_REST_Response(array(
            'zone_id' => $zone_id,
            'post_ids' => $post_ids,
            'content' => $content,
            'status' => $status), $http_status);
    }

    /**
     * Update the zone's lock
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    function zone_update_lock( $request )
    {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        if (!$zone_id) {
            return $this->_bad_request(self::ZONE_ID_REQUIRED, __('zone id required', self::ZONINATOR_NAMESPACE));
        }

        if (!$this->_zone_gateway->is_zone_locked($zone_id)) {
            $this->_zone_gateway->lock_zone($zone_id);
            return new WP_REST_Response(array(
                'zone_id' => $zone_id,
                'status' => 1), 200);
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
    function search_posts( $request )
    {
        $q = $this->_get_param($request, 'term', '', 'stripslashes');

        if (empty($q)) {
            return $this->_bad_request(self::TERM_REQUIRED, __('parameter term is required', self::ZONINATOR_NAMESPACE));
        }

        $filter_cat = $this->_get_param( $request, 'cat', '', 'absint' );
        $filter_date = $this->_get_param( $request, 'date', '', 'striptags' );

        $post_types = $this->_zone_gateway->get_supported_post_types();
        $limit = $this->_get_param( $request, 'limit', $this->_zone_gateway->posts_per_page );

        if ( 0 >= $limit ) {
            $limit = $this->_zone_gateway->posts_per_page;
        }

        $exclude = (array)$this->_get_param( $request, 'exclude', array(), 'absint' );

        $args = apply_filters('zoninator_search_args', array(
            's' => $q,
            'post__not_in' => $exclude,
            'posts_per_page' => $limit,
            'post_type' => $post_types,
            'post_status' => array('publish', 'future'),
            'order' => 'DESC',
            'orderby' => 'post_date',
            'suppress_filters' => true,
        ));

        if ( $this->_validate_category_filter( $filter_cat ) ) {
            $args['cat'] = $filter_cat;
        }

        if ( $this->_validate_date_filter( $filter_date ) ) {
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
     * Get zone posts
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|WP_REST_Response
     */
    public function get_zone_posts($request )
    {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        if ( empty( $zone_id ) ) {
            return $this->_bad_request( self::ZONE_ID_REQUIRED, __( 'zone_id is required', self::ZONINATOR_NAMESPACE ) );
        }

        $results = $this->_zone_gateway->get_zone_feed( $zone_id );

        if ( is_wp_error( $results ) ) {
            return $this->_bad_request( self::ZONE_FEED_ERROR, $results->get_error_message() );
        }

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Get recent posts, excluding the ones that are already part of the zone provided
     * Recent posts can be filtered by category and date
     *
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function get_recent_posts( $request )
    {
        $cat = $this->_get_param( $request, 'cat', '', 'absint' );
        $date = $this->_get_param( $request, 'date', '', 'striptags' );
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        $limit = $this->_zone_gateway->posts_per_page;
        $post_types = $this->_zone_gateway->get_supported_post_types();
        $zone_posts = $this->_zone_gateway->get_zone_posts( $zone_id );
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

            if ( $this->_validate_category_filter( $cat ) ) {
                $args['cat'] = $cat;
            }

            if ( $this->_validate_date_filter( $date ) ) {
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
            $empty_label = __( 'No results found', self::ZONINATOR_NAMESPACE );
        } elseif ( $cat ) {
            $empty_label = sprintf(__('Choose post from %s', 'zoninator'), get_the_category_by_ID($cat));
        } else {
            $empty_label = __( 'Choose a post', self::ZONINATOR_NAMESPACE );
        }

        $content = '<option value="">' . esc_html($empty_label) . '</option>' . $content;

        $response = new WP_REST_Response(array(
            'zone_id' => $zone_id,
            'content' => $content,
            'status' => $status));
        $response->set_status($http_status);
        return $response;
    }

    /**
     * Check if a given request has access to get zone posts.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function get_zone_posts_permissions_check($request ) {
        return true;
    }

    /**
     * Check if a given request has access to remove a post from zone.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function remove_post_from_zone_permissions_check($request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        return $this->_permissions_check( 'update', $zone_id );
    }

    /**
     * Check if a given request has access to add a post in a zone.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function add_post_to_zone_permissions_check($request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        return $this->_permissions_check( 'insert', $zone_id );
    }

    /**
     * Check if a given request has access to update a zone.
     *
     * @param WP_REST_Request $request Full data about the request.
     * @return WP_Error|bool
     */
    public function update_zone_permissions_check($request ) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        return $this->_permissions_check( 'update', $zone_id );
    }

    public function is_numeric( $item ) {
        // see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly
        return is_numeric( $item );
    }

    public function strip_slashes( $item ) {
        // see https://github.com/WP-API/WP-API/issues/1520 on why we do not use stripslashes directly
        return stripslashes( $item );
    }

    private function _get_param(WP_REST_Request $object, $var, $default = '', $sanitize_callback = '') {
        $value = $object->get_param( $var );
        $value = ( $value !== null ) ? $value : $default;


        if ( is_callable( $sanitize_callback ) ) {
            $value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
        }

        return $value;
    }

    private function _validate_date_filter($date) {
        return preg_match( '/([0-9]{4})-([0-9]{2})-([0-9]{2})/', $date );
    }

    private function _validate_category_filter($cat) {
        return $cat && get_term_by( 'id', $cat, 'category' );
    }

    private function _get_zone_id_param() {
        return array(
            'zone_id' => array(
                'type'              => 'integer',
                'validate_callback' => array($this, 'is_numeric'),
                'sanitize_callback' => 'absint',
                'required'          => true
            )
        );
    }

    private function _get_zone_post_rest_route_params() {
        $zone_params = $this->_get_zone_id_param();
        return array_merge(array(
            'post_id' => array(
                'type'              => 'integer',
                'validate_callback' => array($this, 'is_numeric'),
                'required'          => true
            )
        ), $zone_params);
    }

    private function _params_for_get_recent_posts()
    {
        $zone_params = $this->_get_zone_id_param();
        return array_merge(array(
            'cat' => array(
                'description'       => __( 'only recent posts from this category id', self::ZONINATOR_NAMESPACE ),
                'type'              => 'integer',
                'validate_callback' => array( $this, 'is_numeric' ),
                'sanitize_callback' => 'absint',
                'default'           => 0,
                'required'          => false
            ),
            'date' => array(
                'description'       => __( 'only get posts after this date (format YYYY-mm-dd)', self::ZONINATOR_NAMESPACE ),
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'strip_slashes' ),
                'default'           => '',
                'required'          => false
            )
        ), $zone_params);
    }

    private function _params_for_search_posts()
    {
        return array(
            'term' => array(
                'description'       => __( 'search term', self::ZONINATOR_NAMESPACE ),
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'strip_slashes' ),
                'default'           => '',
                'required'          => true
            ),
            'cat' => array(
                'description'       => __( 'filter by category', self::ZONINATOR_NAMESPACE ),
                'type'              => 'integer',
                'validate_callback' => array( $this, 'is_numeric' ),
                'sanitize_callback' => 'absint',
                'default'           => 0,
                'required'          => false
            ),
            'date' => array(
                'description'       => __( 'only get posts after this date (format YYYY-mm-dd)', self::ZONINATOR_NAMESPACE ),
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'strip_slashes' ),
                'default'           => '',
                'required'          => false
            ),
            'limit' => array(
                'description'       => __( 'limit results', self::ZONINATOR_NAMESPACE ),
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => $this->_zone_gateway->posts_per_page,
                'required'          => false
            ),
            'exclude' => array(
                'description'       => __( 'post_ids to exclude', self::ZONINATOR_NAMESPACE ),
                'required'          => false
            )
        );
    }

    private function _bad_request($code, $message) {
        return new WP_Error( $code, $message, array( 'status' => 400 ) );
    }

    /**
     * @param $zone_id
     * @return bool|WP_Error
     */
    private function _permissions_check($action, $zone_id = null )
    {
        if (!$this->_permissions->check( $action, $zone_id ) ) {
            return $this->_bad_request(self::PERMISSION_DENIED, __('Sorry, you\'re not supposed to do that...', self::ZONINATOR_NAMESPACE));
        }
        return true;
    }
}