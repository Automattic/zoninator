<?php
declare( strict_types = 1 );

/**
 * Integration tests for the latest content offered before anything is typed in the zone screen's search.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * @group ajax
 */
class ZoninatorRecentPostsTest extends WP_Ajax_UnitTestCase {

	public function tearDown(): void {
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
		parent::tearDown();
	}

	/**
	 * Posts already in the zone must not be listed, and the list must still
	 * hold a full page of other posts.
	 */
	public function test_latest_content_leaves_out_zone_posts_and_stays_full(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$zoninator = \Zoninator();
		$limit     = $zoninator->get_posts_per_page();

		$post_ids = array();
		for ( $i = 1; $i <= $limit + 2; $i++ ) {
			$post_ids[] = self::factory()->post->create( array(
				'post_date' => gmdate( 'Y-m-d H:i:s', time() - ( 100 - $i ) * HOUR_IN_SECONDS ),
			) );
		}

		// Newest two posts are in the zone, and the screen sends them as the posts to leave out.
		$zone_post_ids = array_slice( $post_ids, -2 );

		$_POST['action']   = 'zoninator_search_posts';
		$_POST['term']     = '';
		$_POST['exclude']  = $zone_post_ids;
		$_POST['_wpnonce'] = wp_create_nonce( $zoninator->_get_nonce_key( \Zoninator::ZONE_AJAX_NONCE_ACTION ) );
		$_REQUEST          = $_POST;

		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		try {
			$this->_handleAjax( 'zoninator_search_posts' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		remove_filter( 'query', $logger );

		// The slow NOT IN clause from issue #75 must not be used.
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/\.ID NOT IN/', $query );
		}

		$listed = array_map( 'intval', wp_list_pluck( json_decode( $this->_last_response, true ), 'post_id' ) );

		$this->assertCount( $limit, $listed );
		foreach ( $zone_post_ids as $zone_post_id ) {
			$this->assertNotContains( $zone_post_id, $listed );
		}
	}
}
