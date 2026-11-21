<?php
declare( strict_types = 1 );

/**
 * Integration tests for the recent posts dropdown on the zone screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

/**
 * @group zoninator
 */
class ZoninatorRecentPostsTest extends TestCase {

	/**
	 * Posts already in the zone must not be listed, and the list must still
	 * hold a full page of other posts.
	 */
	public function test_dropdown_leaves_out_zone_posts_and_stays_full(): void {
		$zoninator = \Zoninator();
		$limit     = $zoninator->posts_per_page;

		$post_ids = array();
		for ( $i = 1; $i <= $limit + 2; $i++ ) {
			$post_ids[] = self::factory()->post->create( array(
				'post_date' => gmdate( 'Y-m-d H:i:s', time() - ( 100 - $i ) * HOUR_IN_SECONDS ),
			) );
		}

		$zone = $zoninator->insert_zone( 'recent-posts-zone', 'Recent posts zone' );
		$this->assertIsArray( $zone );

		// Newest two posts are in the zone.
		$zone_post_ids = array_slice( $post_ids, -2 );
		$zoninator->add_zone_posts( $zone['term_id'], $zone_post_ids );

		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		ob_start();
		$zoninator->zone_admin_recent_posts_dropdown( $zone['term_id'] );
		$html = ob_get_clean();

		remove_filter( 'query', $logger );

		// The slow NOT IN clause from issue #75 must not be used.
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/\.ID NOT IN/', $query );
		}

		preg_match_all( '/<option value="(\d+)">/', $html, $matches );
		$listed = array_map( 'intval', $matches[1] );

		$this->assertCount( $limit, $listed );
		foreach ( $zone_post_ids as $zone_post_id ) {
			$this->assertNotContains( $zone_post_id, $listed );
		}
	}
}
