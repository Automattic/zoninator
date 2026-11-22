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

		$zone          = \Zoninator()->insert_zone( 'tags-zone', 'Tags zone' );
		$this->zone_id = (int) $zone['term_id'];

		$created        = self::factory()->post->create_many( 3 );
		$this->post_ids = array( $created[2], $created[0], $created[1] );
		\Zoninator()->add_zone_posts( $this->zone_id, $this->post_ids );
	}

	public function test_z_get_zones_lists_every_zone(): void {
		\Zoninator()->insert_zone( 'other-zone', 'Other zone' );

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

		$this->assertInstanceOf( \WP_Query::class, $query );
		$this->assertSame( $this->post_ids, $this->ids( $query->posts ) );
	}

	/**
	 * @param \WP_Post[] $posts Posts.
	 * @return int[]
	 */
	private function ids( array $posts ): array {
		return array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
	}
}
