<?php
/**
 * Integration tests for the ajax_search_posts admin-ajax handler.
 *
 * Covers the authorisation and nonce enforcement added in response to
 * authenticated information disclosure of scheduled (future) posts via
 * wp_ajax_zoninator_search_posts.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;
use WPAjaxDieStopException;
use Zoninator;

/**
 * @group ajax
 */
class Zoninator_Ajax_Search_Posts_Test extends WP_Ajax_UnitTestCase {

	/**
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * @var int
	 */
	private $future_post_id;

	public function set_up(): void {
		parent::set_up();

		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->future_post_id = self::factory()->post->create( array(
			'post_title'  => 'zoninator_future_leak_canary',
			'post_status' => 'future',
			'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
		) );

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	public function tear_down(): void {
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
		parent::tear_down();
	}

	/**
	 * A subscriber must not be able to reach the search handler, even when
	 * authenticated. This guards against authenticated enumeration of
	 * scheduled posts via the search oracle.
	 */
	public function test_subscriber_is_blocked_without_json_output(): void {
		wp_set_current_user( $this->subscriber_id );

		$_POST['action'] = 'zoninator_search_posts';
		$_POST['term']   = 'zoninator_future_leak';

		$this->expectException( WPAjaxDieStopException::class );

		try {
			$this->_handleAjax( 'zoninator_search_posts' );
		} finally {
			$this->assertStringNotContainsString(
				'zoninator_future_leak_canary',
				$this->_last_response,
				'Subscriber must not receive search results for future posts.'
			);
		}
	}

	/**
	 * An authorised user without a valid nonce must be rejected. This guards
	 * against CSRF against the search endpoint.
	 */
	public function test_admin_without_nonce_is_blocked(): void {
		wp_set_current_user( $this->admin_id );

		$_POST['action'] = 'zoninator_search_posts';
		$_POST['term']   = 'zoninator_future_leak';

		$this->expectException( WPAjaxDieStopException::class );

		$this->_handleAjax( 'zoninator_search_posts' );
	}

	/**
	 * An authorised user with a valid nonce receives JSON results for matches,
	 * confirming the legitimate flow still works after the fix.
	 */
	public function test_admin_with_valid_nonce_receives_results(): void {
		wp_set_current_user( $this->admin_id );

		$zoninator  = Zoninator();
		$nonce_key  = $zoninator->_get_nonce_key( $zoninator->zone_ajax_nonce_action );
		$nonce      = wp_create_nonce( $nonce_key );

		$_POST['action']   = 'zoninator_search_posts';
		$_POST['term']     = 'zoninator_future_leak';
		$_POST['_wpnonce'] = $nonce;

		try {
			$this->_handleAjax( 'zoninator_search_posts' );
			$this->fail( 'Expected handler to exit after emitting JSON.' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expected. Handler calls exit after echoing JSON.
		} catch ( WPAjaxDieContinueException $e ) {
			// Also acceptable under some test die handlers.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertNotEmpty( $response );

		$ids = array_map( static fn( $row ) => (int) $row['post_id'], $response );
		$this->assertContains( $this->future_post_id, $ids );
	}
}
