<?php
declare( strict_types = 1 );

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
class ZoninatorAjaxSearchPostsTest extends WP_Ajax_UnitTestCase {

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

	public function setUp(): void {
		parent::setUp();

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

	public function tearDown(): void {
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
		parent::tearDown();
	}

	/**
	 * A subscriber must not be able to reach the search handler, even with a
	 * valid nonce. This guards against authenticated enumeration of
	 * scheduled posts via the search oracle.
	 */
	public function test_subscriber_with_valid_nonce_is_blocked_without_json_output(): void {
		wp_set_current_user( $this->subscriber_id );

		$zoninator = Zoninator();

		$_POST['action']   = 'zoninator_search_posts';
		$_POST['term']     = 'zoninator_future_leak';
		$_POST['_wpnonce'] = wp_create_nonce( $zoninator->_get_nonce_key( $zoninator->zone_ajax_nonce_action ) );

		try {
			$this->_handleAjax( 'zoninator_search_posts' );
			$this->fail( 'Expected the handler to stop the request.' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( esc_html( "Sorry, you're not supposed to do that..." ), $e->getMessage() );
		}

		$this->assertSame( '', $this->_last_response );
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
			// Expected. wp_send_json() dies after echoing JSON.
		} catch ( WPAjaxDieContinueException $e ) {
			// Also acceptable under some test die handlers.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertNotEmpty( $response );

		$ids = array_map( static fn( $row ) => (int) $row['post_id'], $response );
		$this->assertContains( $this->future_post_id, $ids );
	}

	/**
	 * Excluded posts must not come back, and the page must still be full
	 * when the excluded posts are the newest matches.
	 */
	public function test_excluded_posts_are_left_out_and_page_is_full(): void {
		$post_ids = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$post_ids[] = self::factory()->post->create( array(
				'post_title' => 'zoninator_exclude_canary ' . $i,
				'post_date'  => gmdate( 'Y-m-d H:i:s', time() - ( 10 - $i ) * HOUR_IN_SECONDS ),
			) );
		}

		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		// Newest two posts are the ones already in the zone.
		$response = $this->search( 'zoninator_exclude_canary', 2, array( $post_ids[3], $post_ids[2] ) );

		remove_filter( 'query', $logger );

		$ids = array_map( static fn( $row ) => (int) $row['post_id'], $response );
		$this->assertSame( array( $post_ids[1], $post_ids[0] ), $ids );

		// The slow NOT IN clause from issue #75 must not be used.
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/\.ID NOT IN/', $query );
		}
	}

	/**
	 * The IDs to exclude come from the request, so a long list must not
	 * make the query fetch a matching number of extra rows.
	 */
	public function test_long_exclude_list_does_not_raise_the_query_limit(): void {
		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		$this->search( 'zoninator_exclude_canary', 2, range( 1000000, 1004999 ) );

		remove_filter( 'query', $logger );

		$limits = array();
		foreach ( $queries as $query ) {
			if ( preg_match( '/LIMIT \d+, (\d+)/', $query, $matches ) ) {
				$limits[] = (int) $matches[1];
			}
		}

		$this->assertNotEmpty( $limits );
		$this->assertLessThanOrEqual( 102, max( $limits ) );
	}

	/**
	 * Run the search handler as an admin and return the decoded response.
	 *
	 * @param string $term    Search term.
	 * @param int    $limit   Max results.
	 * @param int[]  $exclude Post IDs to exclude.
	 * @return array
	 */
	private function search( string $term, int $limit, array $exclude ): array {
		wp_set_current_user( $this->admin_id );

		$zoninator = Zoninator();

		$_POST['action']   = 'zoninator_search_posts';
		$_POST['term']     = $term;
		$_POST['limit']    = $limit;
		$_POST['exclude']  = $exclude;
		$_POST['_wpnonce'] = wp_create_nonce( $zoninator->_get_nonce_key( $zoninator->zone_ajax_nonce_action ) );

		try {
			$this->_handleAjax( 'zoninator_search_posts' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expected. wp_send_json() dies after echoing JSON.
		} catch ( WPAjaxDieContinueException $e ) {
			// Also acceptable under some test die handlers.
		}

		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );

		return $response;
	}
}
