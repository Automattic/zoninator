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
	 * T test_add_post_to_zone_responds_with_created_when_method_post
	 *
	 * @throws Exception E.
	 */
	function test_add_post_to_zone_responds_with_created_when_method_post() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$zone_id = $this->create_a_zone( 'the-zone-add-post-1', 'The Zone Add Post one' );
		$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_id' => $post_id,
		) );
		$this->assertResponseStatus( $response, 201 );
	}

	/**
	 * T test_add_post_to_zone_responds_with_created_when_method_put
	 *
	 * @throws Exception E.
	 */
	function test_add_post_to_zone_responds_with_success_when_method_put() {
		$this->login_as_admin();
		$post_id = $this->_insert_a_post();
		$zone_id = $this->create_a_zone( 'the-zone-add-post-1', 'The Zone Add Post one' );
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_id' => $post_id,
		) );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * T test_add_post_to_zone_respond_not_found_if_zone_not_exists
	 *
	 * @throws Exception E.
	 */
	function test_add_post_to_zone_respond_not_found_if_zone_not_exists() {
		$post_id = $this->_insert_a_post();
		$response = $this->put( '/zoninator/v1/zones/666666/posts', array(
			'post_id' => $post_id,
		) );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * Test test_add_post_to_zone_fail_if_invalid_post
	 */
	function test_add_post_to_zone_fail_if_invalid_post() {
		$zone_id = $this->add_a_zone( 'zone-test_add_post_to_zone_fail_if_invalid_post' );
		$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_id' => 666666,
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
		$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
			'post_id' => $post->ID,
		) );
		$this->assertResponseStatus( $response, 201 );
		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
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
	 * Test test_get_zone_posts_fail_when_no_posts_in_zone
	 */
	function test_get_zone_posts_fail_when_no_posts_in_zone() {
		$term_factory = new WP_UnitTest_Factory_For_Term( null, Zoninator()->zone_taxonomy );
		$zone_id = $term_factory->create_object( array(
			'name' => 'The Zone Add Post one',
			'description' => 'Zone 2',
			'slug' => 'zone-2',
		) );

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$this->assertResponseStatus( $response, 400 );
	}

	/**
	 * Test test_remove_post_from_zone_bad_request_if_invalid_post_id
	 */
	function test_remove_post_from_zone_bad_request_if_invalid_post_id() {
		$zone_id = $this->add_a_zone( 'zone-test_remove_post_from_zone_bad_request_if_invalid_post_id' );
		$response = $this->delete( '/zoninator/v1/zones/' . $zone_id . '/posts/0' );
		$this->assertResponseStatus( $response, 400 );
	}

	/**
	 * Test test_remove_post_from_zone_not_found_if_no_zone_id
	 */
	function test_remove_post_from_zone_not_found_if_no_zone_id() {
		$response = $this->delete( '/zoninator/v1/zones/121212/posts/' );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * Test test_remove_post_from_zone_succeed_if_successful
	 */
    function test_remove_post_from_zone_succeed_if_successful() {
		$this->login_as_admin();
		$zone_id = $this->add_a_zone( 'zone-test_remove_post_from_zone_succeed_if_successful' );
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		foreach ( $posts as $post ) {
			$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
				'post_id' => $post->ID,
			) );
			$this->assertResponseStatus( $response, 201 );
		}

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertSame( count( $posts ), count( $data ) );
		$first_post = $data[0];
		$response = $this->delete( '/zoninator/v1/zones/' . $zone_id . '/posts/' . $first_post->ID );
		$this->assertResponseStatus( $response, 200 );
		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertSame( count( $posts ) - 1, count( $data ) );
		$ids = wp_list_pluck( $data, 'ID' );
		$this->assertTrue( ! in_array( $first_post->ID, $ids, true ) );
    }

	/**
	 * Test test_reorder_posts_on_zone_return_WP_Error_if_post_ids_not_present
	 */
    function test_reorder_posts_on_zone_return_WP_Error_if_post_ids_not_present() {
		$this->login_as_admin();
		$zone_id = $this->add_a_zone( 'zone-test_reorder_posts_on_zone_return_WP_Error_if_post_ids_not_present' );
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		foreach ( $posts as $post ) {
			$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
				'post_id' => $post->ID,
			) );
			$this->assertResponseStatus( $response, 201 );
		}

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$data = $response->get_data();
		$ids = wp_list_pluck( $data, 'ID' );
		shuffle( $ids );
		$request_data = array();
		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts/order', $request_data );
		$this->assertResponseStatus( $response, 400 );
    }

	/**
	 * Test test_reorder_posts_on_zone_success
	 */
	function test_reorder_posts_on_zone_success() {
		$this->login_as_admin();
		$zone_id = $this->add_a_zone( 'zone-test_reorder_posts_on_zone_success' );
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		foreach ( $posts as $post ) {
			$response = $this->post( '/zoninator/v1/zones/' . $zone_id . '/posts', array(
				'post_id' => $post->ID,
			) );
			$this->assertResponseStatus( $response, 201 );
		}

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$data = $response->get_data();
		$ids = wp_list_pluck( $data, 'ID' );
		shuffle( $ids );
		$request_data = array(
			'posts' => $ids,
		);

		$response = $this->put( '/zoninator/v1/zones/' . $zone_id . '/posts/order', $request_data );
		$this->assertResponseStatus( $response, 200 );

		$response = $this->get( '/zoninator/v1/zones/' . $zone_id );
		$data = $response->get_data();
		$reordered_ids = wp_list_pluck( $data, 'ID' );

		$this->assertEquals( $ids, $reordered_ids );
	}

	/**
	 * Test test_reorder_posts_on_zone_return_WP_Error_if_zone_id_not_present
	 */
	function test_reorder_posts_on_zone_return_WP_Error_if_zone_id_not_present() {
		$response = $this->put( '/zoninator/v1/zones/123123/posts/order', array() );
		$this->assertResponseStatus( $response, 404 );
	}
//
//    function test_zone_update_lock_200()
//    {
//        $this->_zoninator->method( 'is_zone_locked' )->willReturn( false );
//        $request = $this->_create_request( array( 'zone_id' => 3 ) );
//        $response = $this->_controller->zone_update_lock( $request );
//        $this->_assert_response_status($response, 200);
//    }
//
//    function test_zone_update_lock_400_if_zone_already_locked()
//    {
//        $this->_zoninator->method( 'is_zone_locked' )->willReturn( true );
//        $request = $this->_create_request( array( 'zone_id' => 3 ) );
//        $response = $this->_controller->zone_update_lock( $request );
//        $this->_assert_response_status($response, 400);
//    }
//
//    function test_zone_update_error_if_no_zone_id()
//    {
//        $this->_zoninator->method( 'is_zone_locked' )->willReturn( false );
//        $request = $this->_create_request( array( ) );
//        $response = $this->_controller->zone_update_lock( $request );
//        $this->assertInstanceOf( 'WP_Error', $response );
//    }
//
	/**
	 * Test test_search_posts_error_if_empty_term
	 */
    function test_search_posts_return_results() {
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		$first = $posts[0];
        $data = array( 'term' => $first->post_title );
		$response = $this->get( '/zoninator/v1/posts/search', $data );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$first_result = $data[0];
		$this->assertEquals( $first->ID, $first_result['post_id'] );
    }

	/**
	 * Test test_search_posts_error_if_empty_term
	 */
	function test_search_posts_error_if_empty_term() {
		self::factory()->post->create_many( 5 );
		$query = new WP_Query();
		$posts = $query->query( array() );
		$data = array( 'term' => '' );
		$response = $this->get( '/zoninator/v1/posts/search', $data );
		$this->assertResponseStatus( $response, 400 );
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

    /**
     * @param array $params
     * @return WP_REST_Request
     */
    private function _create_request(array $params = array())
    {
        $request = new WP_REST_Request(
            WP_REST_Server::CREATABLE,
            ''
        );

        foreach ($params as $key => $value) {
            $request->set_param( $key, $value );
        }
        return $request;
    }

    /**
     * @param $response
     * @param int $status
     */
    private function _assert_response_status($response, $status = 200) {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals($status, $response->get_status());
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
