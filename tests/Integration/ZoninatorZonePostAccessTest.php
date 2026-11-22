<?php
declare( strict_types = 1 );

/**
 * Integration tests for which posts can be put in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WP_REST_Request;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;

/**
 * @group ajax
 */
class ZoninatorZonePostAccessTest extends WP_Ajax_UnitTestCase {

	/**
	 * @var int
	 */
	private $editor_id;

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * A post the editor may not read, whose title is an email address.
	 *
	 * @var int
	 */
	private $request_id;

	public function setUp(): void {
		parent::setUp();

		$this->editor_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$zone             = Zoninator()->insert_zone( 'access-zone', 'Access zone' );
		$this->zone_id    = (int) $zone['term_id'];
		$this->request_id = wp_create_user_request( 'zoninator-canary@example.com', 'export_personal_data' );

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	public function tearDown(): void {
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
		parent::tearDown();
	}

	public function test_add_post_rejects_a_post_the_user_cannot_read(): void {
		$response = $this->ajax( 'zoninator_add_post', array( 'post_id' => $this->request_id ) );

		$this->assertSame( 0, $response['status'] );
		$this->assertStringNotContainsString( 'zoninator-canary@example.com', $this->_last_response );
		$this->assertSame( '', $this->zone_order( $this->request_id ) );
	}

	public function test_add_post_accepts_a_supported_post(): void {
		$post_id  = self::factory()->post->create();
		$response = $this->ajax( 'zoninator_add_post', array( 'post_id' => $post_id ) );

		$this->assertSame( 1, $response['status'] );
		$this->assertSame( '1', $this->zone_order( $post_id ) );
	}

	public function test_reorder_rejects_a_post_the_user_cannot_read(): void {
		$post_id  = self::factory()->post->create();
		$response = $this->ajax( 'zoninator_reorder_posts', array( 'posts' => array( $post_id, $this->request_id ) ) );

		$this->assertSame( 0, $response['status'] );
		$this->assertSame( '', $this->zone_order( $this->request_id ) );
	}

	/**
	 * An author lacks the default zone management capability, so each zone
	 * handler must stop them even when they send a valid nonce.
	 *
	 * @dataProvider data_zone_ajax_actions
	 *
	 * @param string $action AJAX action.
	 */
	public function test_zone_action_refuses_a_user_who_cannot_manage_zones( string $action ): void {
		$post_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $this->zone_id, array( $post_id ) );

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );

		try {
			$this->ajax(
				$action,
				array(
					'post_id' => $post_id,
					'posts'   => array( $post_id ),
				),
				$author_id
			);
			$this->fail( 'Expected the handler to stop the request.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( esc_html( "Sorry, you're not supposed to do that..." ), $e->getMessage() );
		}

		$this->assertSame( '', $this->_last_response );
		$this->assertSame( '1', $this->zone_order( $post_id ) );
		$this->assertFalse( Zoninator()->is_zone_locked( $this->zone_id ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_zone_ajax_actions(): array {
		return array(
			'add post'      => array( 'zoninator_add_post' ),
			'remove post'   => array( 'zoninator_remove_post' ),
			'reorder posts' => array( 'zoninator_reorder_posts' ),
			'update lock'   => array( 'zoninator_update_lock' ),
			'search posts'  => array( 'zoninator_search_posts' ),
		);
	}

	public function test_rest_update_rejects_a_post_the_user_cannot_read(): void {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', '/zoninator/v1/zones/' . $this->zone_id . '/posts' );
		$request->set_param( 'post_ids', array( $this->request_id ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( '', $this->zone_order( $this->request_id ) );
	}

	/**
	 * Run a Zoninator AJAX action with a valid nonce and decode the response.
	 *
	 * @param string   $action  AJAX action.
	 * @param array    $data    Request data.
	 * @param int|null $user_id User to run as. Defaults to the editor.
	 * @return array|null
	 */
	private function ajax( $action, $data, $user_id = null ) {
		wp_set_current_user( $user_id ?? $this->editor_id );

		$zoninator = Zoninator();
		$_POST     = array_merge(
			array(
				'action'   => $action,
				'zone_id'  => $this->zone_id,
				'_wpnonce' => wp_create_nonce( $zoninator->_get_nonce_key( \Zoninator::ZONE_AJAX_NONCE_ACTION ) ),
			),
			$data
		);

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected. wp_send_json() dies after echoing JSON.
		}

		return json_decode( $this->_last_response, true );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string The post's order in the zone, or '' if it isn't in it.
	 */
	private function zone_order( $post_id ) {
		return (string) get_post_meta( $post_id, Zoninator()->get_zone_meta_key( $this->zone_id ), true );
	}
}
