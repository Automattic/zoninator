<?php
declare( strict_types = 1 );

/**
 * Integration tests for the template tags in functions.php.
 *
 * Themes call these directly, so they are public API.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Post;
use WP_Query;
use WP_Term;
use Zoninator;

final class ZoninatorTemplateTagsTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * Posts in zone order.
	 *
	 * @var int[]
	 */
	private $post_ids;

	public function setUp(): void {
		parent::setUp();

		$zone          = Zoninator()->insert_zone( 'tags-zone', 'Tags zone' );
		$this->zone_id = (int) $zone['term_id'];

		$created        = self::factory()->post->create_many( 3 );
		$this->post_ids = array( $created[2], $created[0], $created[1] );
		Zoninator()->add_zone_posts( $this->zone_id, $this->post_ids );
	}

	public function test_z_get_zones_lists_every_zone(): void {
		Zoninator()->insert_zone( 'other-zone', 'Other zone' );

		$slugs = wp_list_pluck( z_get_zones(), 'slug' );
		sort( $slugs );

		$this->assertSame( array( 'other-zone', 'tags-zone' ), $slugs );
	}

	public function test_z_get_zone_accepts_an_id_or_a_slug(): void {
		$this->assertSame( $this->zone_id, z_get_zone( 'tags-zone' )->term_id );
		$this->assertSame( 'tags-zone', z_get_zone( $this->zone_id )->slug );
	}

	public function test_z_get_posts_in_zone_keeps_zone_order(): void {
		$this->assertSame( $this->post_ids, $this->ids( z_get_posts_in_zone( 'tags-zone' ) ) );
	}

	public function test_z_get_posts_in_zone_passes_query_args_through(): void {
		$posts = z_get_posts_in_zone( $this->zone_id, array( 'posts_per_page' => 2 ) );

		$this->assertSame( array_slice( $this->post_ids, 0, 2 ), $this->ids( $posts ) );
	}

	public function test_z_get_zone_query_returns_the_zone_posts_in_order(): void {
		$query = z_get_zone_query( $this->zone_id );

		$this->assertInstanceOf( WP_Query::class, $query );
		$this->assertSame( $this->post_ids, $this->ids( $query->posts ) );
	}

	public function test_z_get_next_post_in_zone_returns_the_following_post(): void {
		$next = z_get_next_post_in_zone( $this->zone_id, $this->post_ids[0] );

		$this->assertSame( $this->post_ids[1], $next->ID );
	}

	public function test_z_get_prev_post_in_zone_returns_the_preceding_post(): void {
		$prev = z_get_prev_post_in_zone( $this->zone_id, $this->post_ids[2] );

		$this->assertSame( $this->post_ids[1], $prev->ID );
	}

	public function test_z_get_next_and_prev_post_in_zone_do_not_count_rows(): void {
		$queries = array();
		$record  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $record );

		z_get_next_post_in_zone( $this->zone_id, $this->post_ids[0] );
		z_get_prev_post_in_zone( $this->zone_id, $this->post_ids[2] );

		remove_filter( 'query', $record );

		$this->assertNotEmpty( $queries );
		foreach ( $queries as $query ) {
			$this->assertStringNotContainsString( 'FOUND_ROWS', $query );
		}
	}

	public function test_z_get_next_post_in_zone_returns_false_after_the_last_post(): void {
		$this->assertFalse( z_get_next_post_in_zone( $this->zone_id, $this->post_ids[2] ) );
	}

	public function test_z_get_prev_post_in_zone_returns_false_before_the_first_post(): void {
		$this->assertFalse( z_get_prev_post_in_zone( $this->zone_id, $this->post_ids[0] ) );
	}

	public function test_z_get_next_post_in_zone_returns_false_for_a_post_outside_the_zone(): void {
		$this->assertFalse( z_get_next_post_in_zone( $this->zone_id, self::factory()->post->create() ) );
	}

	public function test_z_get_next_post_in_zone_compares_positions_as_numbers(): void {
		$more = self::factory()->post->create_many( 8 );
		Zoninator()->add_zone_posts( $this->zone_id, $more, true );

		// Position 9 is followed by 10, which sorts before 9 as a string.
		$next = z_get_next_post_in_zone( $this->zone_id, $more[5] );

		$this->assertSame( $more[6], $next->ID );
	}

	public function test_z_get_next_post_in_zone_defaults_to_the_loop_post(): void {
		$GLOBALS['post'] = get_post( $this->post_ids[0] );

		$next = z_get_next_post_in_zone( $this->zone_id );

		$this->assertSame( $this->post_ids[1], $next->ID );
	}

	public function test_z_get_post_zones_lists_each_zone_the_post_is_in(): void {
		$other = Zoninator()->insert_zone( 'other-zone', 'Other zone' );
		Zoninator()->add_zone_posts( (int) $other['term_id'], $this->post_ids[1] );

		$zones = z_get_post_zones( $this->post_ids[1] );

		$this->assertSame( array( $this->zone_id, (int) $other['term_id'] ), wp_list_pluck( $zones, 'term_id' ) );
		$this->assertInstanceOf( WP_Term::class, $zones[0] );
		$this->assertSame( array( $this->zone_id ), wp_list_pluck( z_get_post_zones( $this->post_ids[0] ), 'term_id' ) );
	}

	public function test_z_get_post_zones_defaults_to_the_loop_post(): void {
		$GLOBALS['post'] = get_post( $this->post_ids[2] );

		$this->assertSame( array( $this->zone_id ), wp_list_pluck( z_get_post_zones(), 'term_id' ) );
	}

	public function test_z_get_post_zones_returns_an_empty_array_for_a_post_in_no_zone(): void {
		$this->assertSame( array(), z_get_post_zones( self::factory()->post->create() ) );
		$this->assertSame( array(), z_get_post_zones( 999999 ) );
	}

	public function test_z_get_post_zones_leaves_out_a_draft_on_the_front_end(): void {
		wp_update_post(
			array(
				'ID'          => $this->post_ids[0],
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), z_get_post_zones( $this->post_ids[0] ) );
	}

	public function test_z_get_post_zones_ignores_order_meta_for_a_deleted_zone(): void {
		add_post_meta( $this->post_ids[0], Zoninator::ZONE_META_PREFIX . '999999', 1 );

		$this->assertSame( array( $this->zone_id ), wp_list_pluck( z_get_post_zones( $this->post_ids[0] ), 'term_id' ) );
	}

	/**
	 * @param WP_Post[] $posts Posts.
	 * @return int[]
	 */
	private function ids( array $posts ): array {
		return array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
	}
}
