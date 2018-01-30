<?php

class Zoninator_Api_Controller_Test extends WP_UnitTestCase {

	/**
	 * The Mighty Zoninator!
	 *
	 * @var Zoninator
	 */
	private $_zoninator = null;

	/**
	 * REST Server
	 *
	 * @var WP_REST_Server
	 */
	protected $rest_server;

	/**
	 * Post ID
	 *
	 * @var int
	 */
	private $_post_id = 0;

	/**
	 * Admin ID
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Assert Status
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int              $status_code Code.
	 */
	function assert_response_status( $response, $status_code ) {
		$this->assertEquals( $status_code, $response->get_status() );
	}

	/**
	 * As Admin
	 *
	 * @return Zoninator_Api_Controller_Test
	 */
	function login_as_admin() {
		return $this->login_as( $this->admin_id );
	}

	/**
	 * Login as
	 *
	 * @param int $user_id U.
	 * @return Zoninator_Api_Controller_Test $this
	 */
	function login_as( $user_id ) {
		wp_set_current_user( $user_id );
		return $this;
	}

	/**
	 * Assert Status 200
	 *
	 * @param WP_REST_Response $response Response.
	 */
	function assert_http_response_status_success( $response ) {
		$this->assert_response_status( $response, MT_Controller::HTTP_OK );
	}

	/**
	 * Assert Status 201
	 *
	 * @param WP_REST_Response $response Response.
	 */
	function assert_http_response_status_created( $response ) {
		$this->assert_response_status( $response, MT_Controller::HTTP_CREATED );
	}

	/**
	 * Assert Status 404
	 *
	 * @param WP_REST_Response $response Response.
	 */
	function assert_http_response_status_not_found( $response ) {
		$this->assert_response_status( $response, MT_Controller::HTTP_NOT_FOUND );
	}

	/**
	 * Ensure we got a certain response code
	 *
	 * @param WP_REST_Response $response The Response.
	 * @param int              $status_code Expected status code.
	 */
	function assertResponseStatus( $response, $status_code ) {
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( $status_code, $response->get_status() );
	}

	/**
	 * Have WP_REST_Server Dispatch an HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param string $method Http mehod.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function request( $endpoint, $method, $args = array() ) {
		$request = new WP_REST_Request( $method, $endpoint );
		foreach ( $args as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->rest_server->dispatch( $request );
	}

	/**
	 * Have WP_REST_Server Dispatch a GET HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function get( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'GET', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a POST HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function post( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'POST', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a PUT HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function put( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'PUT', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a DELETE HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function delete( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'DELETE', $args );
	}

	/**
	 * Setup
	 */
	function setUp() {
		parent::setUp();
		/**
		 *The global
		 *
		 * @var WP_REST_Server $wp_rest_server
		 */
		global $wp_rest_server;
		$this->rest_server = new Spy_REST_Server;
		$wp_rest_server = $this->rest_server;
		$this->_zoninator = Zoninator();
		$admin = get_user_by( 'email', 'rest_api_admin_user@test.com' );
		if ( false === $admin ) {
			$this->admin_id = wp_create_user(
				'rest_api_admin_user',
				'rest_api_admin_user',
				'rest_api_admin_user@test.com'
			);
			$admin = get_user_by( 'ID', $this->admin_id );
			$admin->set_role( 'administrator' );
		}

		$this->default_user_id = get_current_user_id();
		$this->login_as_admin();
		$this->rest_server = $wp_rest_server;
		do_action( 'rest_api_init' );
		$this->environment = Zoninator()->rest_api->bootstrap->environment();
	}

	/**
	 * T test_create_zone_responds_with_created_when_method_post
	 *
	 * @throws Exception E.
	 */
	function test_create_zone_responds_with_created_when_method_post() {
		$this->login_as_admin();
		$response = $this->post( '/zoninator/v1/zones', array(
			'slug' => 'test-zone',
		) );
		$this->assertResponseStatus( $response, 201 );
	}

	/**
	 * T test_create_zone_fail_if_invalid_data
	 *
	 * @throws Exception E.
	 */
	function test_create_zone_fail_if_invalid_data() {
		$this->login_as_admin();
		$response = $this->post( '/zoninator/v1/zones', array(
			'description' => 'No slug provided.'
		) );
		$this->assertResponseStatus( $response, 400 );
	}

	/**
	 * T test_create_zone_with_special_chars
	 *
	 * @throws Exception E
	 */
	function test_create_zone_with_special_chars() {
		$this->login_as_admin();
		$response = $this->post( '/zoninator/v1/zones', array(
			'name' => '&<>!@#(',
			'slug' => 'test-zone',
			'description' => '&<>!@#('
		) );
		$data = $response->get_data();
		$this->assertResponseStatus( $response, 201 );
		$this->assertEquals( $data['name'], '&amp;&lt;&gt;!@#(' );
		$this->assertEquals( $data['description'], '&amp;&lt;&gt;!@#(' );
	}

	/**
	 * T test_update_zone_responds_with_success_when_method_put
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_responds_with_success_when_method_put() {
		$this->login_as_admin();
		$zone_id = $this->create_a_zone( 'test-update-zone', 'Test Zone' );
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id, array(
			'name' => 'Other test zone',
		) );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * T test_update_zone_responds_with_not_found_if_zone_not_exist
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_responds_with_not_found_if_zone_not_exist() {
		$this->login_as_admin();
		$response = $this->put( '/zoninator/v1/zones/666666', array(
			'name' => 'Other test zone',
		) );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * T test_delete_zone_responds_with_success_when_method_delete
	 *
	 * @throws Exception E.
	 */
	function test_delete_zone_responds_with_success_when_method_delete() {
		$this->login_as_admin();
		$zone_id = $this->create_a_zone( 'test-update-zone', 'Test Zone' );
		$response = $this->delete( '/zoninator/v1/zones/' . $zone_id );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * T test_delete_zone_responds_with_not_found_if_zone_not_exist
	 *
	 * @throws Exception E.
	 */
	function test_delete_zone_responds_with_not_found_if_zone_not_exist() {
		$this->login_as_admin();
		$response = $this->delete( '/zoninator/v1/zones/666666' );
		$this->assertResponseStatus( $response, 404 );				
	}

	/**
	 * T test_update_zone_posts_responds_with_ok_when_method_put
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_posts_responds_with_success_when_method_put() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$zone_id = $this->create_a_zone( 'test-zone', 'Test Zone' );
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_ids' => array( $post_id ),
		) );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * T test_update_zone_posts_responds_with_not_found_if_zone_not_exist
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_posts_responds_with_not_found_if_zone_not_exist() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$response = $this->put( '/zoninator/v1/zones/666666/posts', array(
			'post_ids' => array( $post_id ),
		) );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * T test_update_zone_posts_fails_if_invalid_data_format
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_posts_fails_if_invalid_data() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$zone_id = $this->create_a_zone( 'test-zone', 'Test Zone' );
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts', array() );
		$this->assertResponseStatus( $response, 400 );
	}

	/**
	 * T test_update_zone_posts_fails_if_invalid_post_id
	 *
	 * @throws Exception E.
	 */
	function test_update_zone_posts_fails_if_invalid_post_id() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$zone_id = $this->create_a_zone( 'test-zone', 'Test Zone' );
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_ids' => array( 123456789 ),
		) );
		$this->assertResponseStatus( $response, 400 );
	}

	/**
	 * T test_get_zone_posts_success_when_valid_zone_and_posts
	 *
	 * @throws Exception E.
	 */
	function test_get_zone_posts_success_when_valid_zone_and_posts() {
		$this->login_as_admin();
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		$post = $posts[0];
		$zone_id = $this->add_a_zone();
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_ids' => array( $post->ID ),
		) );
		$this->assertResponseStatus( $response, 200 );
		$response = $this->get( '/zoninator/v1/zones/' . $zone_id . '/posts' );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * Test test_get_zone_posts_success_when_no_posts_in_zone
	 */
	function test_get_zone_posts_success_when_no_posts_in_zone() {
		$term_factory = new WP_UnitTest_Factory_For_Term( null, Zoninator()->zone_taxonomy );
		$zone_id = $term_factory->create_object( array(
			'name' => 'The Zone Add Post one',
			'description' => 'Zone 2',
			'slug' => 'zone-2',
		) );

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id . '/posts' );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * Test test_get_zone_posts_not_found_when_invalid_zone
	 */
	function test_get_zone_posts_not_found_when_invalid_zone() {
		$term_factory = new WP_UnitTest_Factory_For_Term( null, Zoninator()->zone_taxonomy );
		$zone_id = $term_factory->create_object( array(
			'name' => 'The Zone Add Post one',
			'description' => 'Zone 2',
			'slug' => 'zone-2',
		) );

		$response = $this->get( '/zoninator/v1/zones/' . ( $zone_id + 3 ) );
		$this->assertResponseStatus( $response, 404 );
	}

    /**
     * @return int|WP_Error
     */
    private function _insert_a_post() {
        $insert = wp_insert_post( array(
            'post_content' => 'Content For this post ' . rand_str(),
            'post_title' => 'Title ' . rand_str(),
            'post_excerpt' => 'Excerpt ' . rand_str(),
            'post_status' => 'published',
            'post_type' => 'post'
        ) );
		if ( is_wp_error( $insert ) ) {
			throw new Exception( 'Error' );
		}
		return $insert;
    }

	private function create_a_zone( $slug, $title ) {
		$result = Zoninator()->insert_zone( $slug, $title, array( 'description' => rand_str() ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['term_id'] ) ? $result['term_id'] : 0;
	}

	/**
	 * Add A Zone
	 *
	 * @param string $slug Slug.
	 *
	 * @return array|mixed|WP_Error
	 */
	private function add_a_zone( $slug = 'zone-1' ) {
		$term_factory = new WP_UnitTest_Factory_For_Term(null, Zoninator()->zone_taxonomy);
		$zone_id = $term_factory->create_object(array(
			'name' => 'The Zone Add Post one ' . rand_str(),
			'description' => 'Zone ' . rand_str(),
			'slug' => $slug,
		));
		return $zone_id;
	}
}
