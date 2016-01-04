<?php


class Zoninator_Rest_Api_Controller
{
    const REST_NAMESPACE = 'zoninator/v1';

    const ZONES = '/zones';
    const ZONE_ITEM_REGEX = '/zones/(?P<zone_id>[\d]+)';
    const ZONE_ITEM_POSTS_REGEX = '/zones/(?P<zone_id>[\d]+)/posts';
    const ZONE_ITEM_POSTS_POST_REGEX = '/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)';

    const INVALID_ZONE_ID = 'invalid-zone-id';
    const INVALID_POST_ID = 'invalid-post-id';
    const ZONE_ID_POST_ID_REQUIRED = 'zone-id-post-id-required';
    const ZONE_ID_POST_IDS_REQUIRED = 'zone-id-post-ids-required';
    const ZONE_ID_REQUIRED = 'zone-id-required';
    const ZONE_FEED_ERROR = 'zone-feed-error';
    const TERM_REQUIRED = 'term-required';

    /**
     * @var Zoninator_Zone_Gateway
     */
    private $_zone_gateway = null;

    /**
     * @var Zoninator_Permissions
     */
    private $_permissions = null;

    /**
     * @var Zoninator_View_Renderer
     */
    private $_renderer = null;

    function __construct( $data_service, $permissions, $renderer ) {
        $this->_zone_gateway = $data_service;
        $this->_permissions = $permissions;
        $this->_renderer = $renderer;
        add_action('rest_api_init', array($this, 'init_restful_resources'));
    }

    function init_restful_resources() {
        register_rest_route(self::REST_NAMESPACE, self::ZONES, array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_zones')
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_REGEX, array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_zone'),
            'args' => $this->_get_zone_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_POSTS_REGEX, array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_zone_feed'),
            'args' => $this->_get_zone_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_POSTS_REGEX, array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'add_post'),
            'permission_callback' => array($this, 'verify_access'),
            'args' => $this->_get_zone_post_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_POSTS_POST_REGEX, array(
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => array($this, 'remove_post'),
            'permission_callback' => array($this, 'verify_access'),
            'args' => $this->_get_zone_post_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_POSTS_REGEX . '/order', array(
            'methods' => 'PUT',
            'callback' => array($this, 'reorder_posts'),
            'permission_callback' => array($this, 'verify_access'),
            'args' => $this->_get_zone_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, self::ZONE_ITEM_REGEX . '/lock', array(
            'methods' => 'PUT',
            'callback' => array($this, 'zone_update_lock'),
            'permission_callback' => array($this, 'verify_access'),
            'args' => $this->_get_zone_rest_route_params()
        ));

        register_rest_route(self::REST_NAMESPACE, '/posts/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'search_posts')
        ));

        register_rest_route(self::REST_NAMESPACE, '/posts/recent', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_recent_posts')
        ));
    }

    function get_zones() {
        return $this->_zone_gateway->get_zones();
    }

    function get_zone(WP_REST_Request $request) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        return $this->_zone_gateway->get_zone( $zone_id );
    }

    function add_post(WP_REST_Request $request) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        $post_id = $this->_get_param( $request, 'post_id', 0, 'absint' );

        $post = get_post( $post_id );

        if ( ! $post ) {
            return $this->_bad_request(self::INVALID_POST_ID, __('invalid post id', 'zonimator'));
        }

        $zone = $this->_zone_gateway->get_zone($zone_id);

        if ( ! $zone ) {
            return $this->_bad_request(self::INVALID_ZONE_ID, __('invalid zone id', 'zonimator'));
        }

        $result = $this->_zone_gateway->add_zone_posts($zone_id, $post, true);

        if ( is_wp_error( $result ) ) {
            $status = 500;
            $content = $result->get_error_message();
        } else {
            ob_start();
            $this->_renderer->admin_page_zone_post($post, $zone);
            $content = ob_get_contents();
            ob_end_clean();
            $status = 200;
        }

        $response = new WP_REST_Response( array(
            'zone_id' => $zone_id,
            'content' => $content,
            'status' => $status) );

        $response->set_status($status);

        return $response;
    }

    function verify_access(WP_REST_Request $request) {
        $zone_id = intval($request->get_param('zone_id'));
        $action = $request->get_method();

        // TODO: should check if zone locked

        switch ($action) {
            case WP_REST_Server::CREATABLE:
                $verify_function = 'insert';
                break;
            case WP_REST_Server::EDITABLE:
            case WP_REST_Server::DELETABLE:
                $verify_function = 'update';
                break;
            default:
                $verify_function = '';
                break;
        }

        if (!$this->_permissions->check($verify_function, $zone_id)) {
            return $this->_bad_request('rest-permission-denied', __('Sorry, you\'re not supposed to do that...', 'zoninator'));
        }
        return true;
    }

    function remove_post(WP_REST_Request $request) {
        $zone_id = intval($request->get_param('zone_id'));
        $post_id = intval($request->get_param('post_id'));

        if (!$zone_id || !$post_id) {
            return $this->_bad_request(self::ZONE_ID_POST_ID_REQUIRED, __('post id and zone id required', 'zonimator'));
        }

        $result = $this->_zone_gateway->remove_zone_posts($zone_id, $post_id);

        if (is_wp_error($result)) {
            $status = 500;
            $content = $result->get_error_message();
        } else {
            $status = 200;
            $content = '';
        }

        return new WP_REST_Response(array(
            'zone_id' => $zone_id,
            'post_id' => $post_id,
            'content' => $content,
            'status' => $status), $status);
    }

    function reorder_posts($request) {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );
        $post_ids = (array) $this->_get_param( $request, 'posts', array(), 'absint' );

        if ( ! $zone_id || empty( $post_ids ) ) {
            return $this->_bad_request( self::ZONE_ID_POST_IDS_REQUIRED, __('post ids and zone id required', 'zonimator') );
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

    function zone_update_lock(WP_REST_Request $request)
    {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        if (!$zone_id) {
            return $this->_bad_request('rest_zone_update_lock-zone-id-required', __('zone id required', 'zonimator'));
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

    function search_posts(WP_REST_Request $request)
    {
        $q = $this->_get_param($request, 'term', '', 'stripslashes');

        if (empty($q)) {
            return $this->_bad_request(self::TERM_REQUIRED, __('parameter term is required', 'zonimator'));
        }

        $filter_cat = $this->_get_param($request, 'cat', '', 'absint');
        $filter_date = $this->_get_param($request, 'date', '', 'striptags');

        $post_types = $this->_zone_gateway->get_supported_post_types();
        $limit = $this->_get_param($request, 'limit', $this->_zone_gateway->posts_per_page);

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

    function get_zone_feed( WP_REST_Request $request )
    {
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        if ( empty( $zone_id ) ) {
            return $this->_bad_request( self::ZONE_ID_REQUIRED, __('zone_id is required', 'zonimator' ) );
        }

        $results = $this->_zone_gateway->get_zone_feed( $zone_id );

        if ( is_wp_error( $results ) ) {
            return $this->_bad_request( self::ZONE_FEED_ERROR, $results->get_error_message() );
        }

        return new WP_REST_Response( $results, 200 );
    }

    function get_recent_posts(WP_REST_Request $request)
    {
        $cat = $this->_get_param( $request, 'cat', '', 'absint' );
        $date = $this->_get_param( $request, 'date', '', 'striptags' );
        $zone_id = $this->_get_param( $request, 'zone_id', 0, 'absint' );

        $limit = $this->_zone_gateway->posts_per_page;
        $post_types = $this->_zone_gateway->get_supported_post_types();
        $zone_posts = $this->_zone_gateway->get_zone_posts($zone_id);
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
            $empty_label = __( 'No results found', 'zoninator' );
        } elseif ( $cat ) {
            $empty_label = sprintf(__('Choose post from %s', 'zoninator'), get_the_category_by_ID($cat));
        } else {
            $empty_label = __('Choose a post', 'zoninator');
        }

        $content = '<option value="">' . esc_html($empty_label) . '</option>' . $content;

        $response = new WP_REST_Response(array(
            'zone_id' => $zone_id,
            'content' => $content,
            'status' => $status));
        $response->set_status($http_status);
        return $response;
    }

    function _get_param(WP_REST_Request $object, $var, $default = '', $sanitize_callback = '') {
        $value = $object->get_param( $var );
        $value = ($value !== null) ? $value : $default;


        if ( is_callable( $sanitize_callback ) ) {
            $value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
        }

        return $value;
    }

    function is_numeric($item) {
        // see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly
        return is_numeric( $item );
    }

    private function _validate_date_filter($date) {
        return preg_match( '/([0-9]{4})-([0-9]{2})-([0-9]{2})/', $date );
    }

    private function _validate_category_filter($cat) {
        return $cat && get_term_by( 'id', $cat, 'category' );
    }

    private function _get_zone_rest_route_params() {
        return array(
            'zone_id' => array(
                'validate_callback' => array($this, 'is_numeric')
            )
        );
    }

    private function _get_zone_post_rest_route_params() {
        $zone_params = $this->_get_zone_rest_route_params();
        return array_merge(array(
            'post_id' => array(
                'validate_callback' => array($this, 'is_numeric')
            )
        ), $zone_params);
    }

    private function _bad_request($code, $message) {
        return new WP_Error( $code, $message, array( 'status' => 400 ) );
    }
}