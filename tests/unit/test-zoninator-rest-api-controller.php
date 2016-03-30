<?php

class Zoninator_Rest_Api_Controller_Test extends WP_UnitTestCase
{
    /**
     * @var Zoninator_Zone_Gateway
     */
    private $_zone_gateway = null;

    /**
     * @var Zoninator_Permissions
     */
    private $_permissions = null;

    /**
     * @var Zoninator_Rest_Api_Controller
     */
    private $_controller = null;

    /**
     * @var int
     */
    private $_post_id = 0;

    function setUp()
    {
        parent::setUp();

        $this->_zone_gateway = $this->getMockBuilder( 'Zoninator_Zone_Gateway' )
            ->disableOriginalConstructor()
            ->getMock();

        $this->_permissions = $this->getMockBuilder( 'Zoninator_Permissions' )
            ->disableOriginalConstructor()
            ->getMock();

        $this->_controller = new Zoninator_Rest_Api_Controller( $this->_zone_gateway, $this->_permissions );
        $this->_post_id = $this->_insert_a_post();
    }

    function test_add_post_to_zone_return_WP_Error_if_no_zone()
    {
        $post_id = $this->_insert_a_post();
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );

        $this->_zone_gateway->method( 'get_zone' )->willReturn(null);
        $response = $this->_controller->add_post_to_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::INVALID_ZONE_ID, $response->get_error_code() );
    }

    function test_add_post_to_zone_return_WP_Error_if_no_post()
    {
        $request = $this->_create_request( array( 'post_id' => 0, 'zone_id' => 3 ) );

        $response = $this->_controller->add_post_to_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::INVALID_POST_ID,  $response->get_error_code() );
    }

    function test_add_post_to_zone_500_if_add_zone_posts_Fails()
    {
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );

        $this->_zone_gateway->method( 'get_zone' )->willReturn( new stdClass() );
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( new WP_Error( 'an-error' ) );
        $response = $this->_controller->add_post_to_zone( $request );
        $this->assertInstanceOf( 'WP_REST_Response', $response );
        $this->assertEquals( 500, $response->get_status() );
    }

    function test_add_post_to_zone_200_if_add_zone_posts_succeeds()
    {
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );
        $this->_zone_gateway->method( 'get_zone' )->willReturn( new stdClass() );
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( null );
        $response = $this->_controller->add_post_to_zone( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_remove_post_from_zone_return_WP_Error_if_no_post_id()
    {
        $request = $this->_create_request( array( 'post_id' => 0 ) );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::ZONE_ID_POST_ID_REQUIRED,  $response->get_error_code() );
    }

    function test_remove_post_from_zone_return_WP_Error_if_no_zone_id()
    {
        $request = $this->_create_request( array( 'post_id' => $this->_post_id ) );

        $this->_zone_gateway->method( 'get_zone' )->willReturn( null );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::ZONE_ID_POST_ID_REQUIRED,  $response->get_error_code() );
    }

    function test_remove_post_from_zone_return_WP_REST_Response_200_if_successful()
    {
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );
        $this->_zone_gateway->method( 'remove_zone_posts' )->willReturn( null );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_reorder_posts_on_zone_return_WP_Error_if_post_ids_not_present()
    {
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::ZONE_ID_POST_IDS_REQUIRED,  $response->get_error_code() );
    }

    function test_reorder_posts_on_zone_return_WP_Error_if_zone_id_not_present()
    {
        $request = $this->_create_request( array( 'posts' => array( 1, 3, 6 ) ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
        $this->assertEquals( Zoninator_Rest_Api_Controller::ZONE_ID_POST_IDS_REQUIRED,  $response->get_error_code() );
    }

    function test_reorder_posts_on_zone_return_200_if_zone_id_and_posts_present()
    {
        $request = $this->_create_request( array( 'zone_id' => 3, 'posts' => array( 1, 3, 6 ) ) );
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( new stdClass() );
        $response = $this->_controller->reorder_posts( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_reorder_posts_on_zone_return_500_if_zone_id_and_posts_present_and_reordering_fails()
    {
        $request = $this->_create_request( array( 'zone_id' => 3, 'posts' => array( 1, 3, 6 ) ) );
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( new WP_Error('my-error') );
        $response = $this->_controller->reorder_posts( $request );
        $this->_assert_response_status($response, 500);
    }

    function test_get_zone_feed_errors_if_internal_error()
    {
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $this->_zone_gateway->method( 'get_zone_feed' )->willReturn( new WP_Error( 'en-error' ) );
        $this->_zone_gateway->method( 'get_zone' )->willReturn( array(1) );
        $response = $this->_controller->get_zone_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_get_zone_feed_200()
    {
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $this->_zone_gateway->method( 'get_zone_feed' )->willReturn( array() );
        $this->_zone_gateway->method( 'get_zone' )->willReturn( array(1) );
        $response = $this->_controller->get_zone_posts( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_get_zone_feed_errors_if_no_zone_id()
    {
        $request = $this->_create_request( array(  ) );
        $this->_zone_gateway->method( 'get_zone_feed' )->willReturn( array() );
        $this->_zone_gateway->method( 'get_zone' )->willReturn( array() );
        $response = $this->_controller->get_zone_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_remove_post_200()
    {
        $this->_zone_gateway->method( 'remove_post' )->willReturn( null );
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_remove_post_fail_if_no_post_id()
    {
        $this->_zone_gateway->method( 'remove_post' )->willReturn( null );
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_remove_post_fail_if_no_zone_id()
    {
        $this->_zone_gateway->method( 'remove_post' )->willReturn( null );
        $request = $this->_create_request( array( 'post_id' => $this->_post_id ) );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_remove_post_500_if_removal_fails()
    {
        $this->_zone_gateway->method( 'remove_zone_posts' )->willReturn( new WP_Error('error') );
        $request = $this->_create_request( array( 'post_id' => $this->_post_id, 'zone_id' => 3 ) );
        $response = $this->_controller->remove_post_from_zone( $request );
        $this->_assert_response_status($response, 500);
    }

    function test_reorder_posts_200()
    {
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( null );
        $request = $this->_create_request( array( 'posts' => array( $this->_post_id ), 'zone_id' => 3 ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_reorder_posts_error_if_no_zone_id()
    {
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( null );
        $request = $this->_create_request( array( 'posts' => array( $this->_post_id ) ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_reorder_posts_error_if_no_posts()
    {
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( null );
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_reorder_500_if_internal_error()
    {
        $this->_zone_gateway->method( 'add_zone_posts' )->willReturn( new WP_Error('error') );
        $request = $this->_create_request( array( 'posts' => array( $this->_post_id ), 'zone_id' => 3 ) );
        $response = $this->_controller->reorder_posts( $request );
        $this->_assert_response_status($response, 500);
    }

    function test_zone_update_lock_200()
    {
        $this->_zone_gateway->method( 'is_zone_locked' )->willReturn( false );
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $response = $this->_controller->zone_update_lock( $request );
        $this->_assert_response_status($response, 200);
    }

    function test_zone_update_lock_400_if_zone_already_locked()
    {
        $this->_zone_gateway->method( 'is_zone_locked' )->willReturn( true );
        $request = $this->_create_request( array( 'zone_id' => 3 ) );
        $response = $this->_controller->zone_update_lock( $request );
        $this->_assert_response_status($response, 400);
    }

    function test_zone_update_error_if_no_zone_id()
    {
        $this->_zone_gateway->method( 'is_zone_locked' )->willReturn( false );
        $request = $this->_create_request( array( ) );
        $response = $this->_controller->zone_update_lock( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    function test_search_posts_error_if_empty_term()
    {
        $request = $this->_create_request( array( 'term' => '' ) );
        $response = $this->_controller->search_posts( $request );
        $this->assertInstanceOf( 'WP_Error', $response );
    }

    /**
     * @return int|WP_Error
     */
    private function _insert_a_post()
    {
        return wp_insert_post(array(
            'post_content' => 'Content',
            'post_title' => 'Title',
            'post_excerpt' => 'Excerpt',
            'post_status' => 'published',
            'post_type' => 'post'
        ));
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
    private function _assert_response_status($response, $status = 200)
    {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals($status, $response->get_status());
    }
}
