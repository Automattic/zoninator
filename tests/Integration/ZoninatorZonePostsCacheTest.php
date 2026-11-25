<?php
declare( strict_types = 1 );

/**
 * Integration tests for zone posts caching.
 *
 * Zoninator has no own cache for zone posts. It relies on the WP_Query
 * query cache, which is cleared by any post meta change.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

/**
 * @group cache
 */
class ZoninatorZonePostsCacheTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var int[]
	 */
	private $post_ids;

	public function setUp(): void {
		parent::setUp();

		$zone          = self::insert_zone( 'cache-zone', 'Cache Zone' );
		$this->zone_id = $zone['term_id'];

		$this->post_ids = self::factory()->post->create_many( 3 );
		self::add_zone_posts( $this->zone_id, array( $this->post_ids[0], $this->post_ids[1] ) );
	}

	/**
	 * Get the IDs of the posts in the test zone.
	 *
	 * @return int[]
	 */
	private function get_zone_post_ids() {
		return wp_list_pluck( self::zones()->get_posts( $this->zone_id ), 'ID' );
	}

	public function test_repeat_call_runs_no_new_queries() {
		global $wpdb;

		$first = $this->get_zone_post_ids();

		$num_queries = $wpdb->num_queries;
		$second      = $this->get_zone_post_ids();

		$this->assertSame( $first, $second );
		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	public function test_added_post_shows_right_away() {
		$this->get_zone_post_ids();

		self::add_zone_posts( $this->zone_id, array( $this->post_ids[2] ), true );

		$this->assertSame( $this->post_ids, $this->get_zone_post_ids() );
	}

	public function test_removed_post_is_gone_right_away() {
		$this->get_zone_post_ids();

		self::remove_zone_posts( $this->zone_id, array( $this->post_ids[0] ) );

		$this->assertSame( array( $this->post_ids[1] ), $this->get_zone_post_ids() );
	}

	public function test_new_order_shows_right_away() {
		$this->get_zone_post_ids();

		self::add_zone_posts( $this->zone_id, array( $this->post_ids[1], $this->post_ids[0] ) );

		$this->assertSame( array( $this->post_ids[1], $this->post_ids[0] ), $this->get_zone_post_ids() );
	}

	public function test_unpublished_post_is_gone_right_away() {
		$this->get_zone_post_ids();

		wp_update_post(
			array(
				'ID'          => $this->post_ids[0],
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array( $this->post_ids[1] ), $this->get_zone_post_ids() );
	}
}
