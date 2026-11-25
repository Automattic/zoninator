<?php
declare( strict_types = 1 );

/**
 * Integration tests for the Zone Posts variation of the Query Loop block.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_REST_Request;

final class ZoninatorZoneBlockTest extends TestCase {

	public function test_block_lists_published_zone_posts_in_zone_order(): void {
		$zone_id = $this->create_zone();
		$first   = $this->create_post( 'Zone first' );
		$second  = $this->create_post( 'Zone second' );
		$draft   = $this->create_post( 'Zone draft', 'draft' );
		$this->create_post( 'Not in zone' );
		\Zoninator()->add_zone_posts( $zone_id, array( $second, $draft, $first ) );

		$html = $this->render_block( $zone_id );

		$this->assertStringNotContainsString( 'Zone draft', $html );
		$this->assertStringNotContainsString( 'Not in zone', $html );
		$first_at  = strpos( $html, 'Zone first' );
		$second_at = strpos( $html, 'Zone second' );
		$this->assertIsInt( $first_at );
		$this->assertIsInt( $second_at );
		$this->assertLessThan( $first_at, $second_at );
	}

	public function test_block_keeps_its_page_size(): void {
		$zone_id  = $this->create_zone();
		$post_ids = array(
			$this->create_post( 'Zone one' ),
			$this->create_post( 'Zone two' ),
			$this->create_post( 'Zone three' ),
		);
		\Zoninator()->add_zone_posts( $zone_id, $post_ids );

		$html = $this->render_block( $zone_id, 2 );

		$this->assertStringContainsString( 'Zone one', $html );
		$this->assertStringContainsString( 'Zone two', $html );
		$this->assertStringNotContainsString( 'Zone three', $html );
	}

	public function test_block_without_a_zone_shows_no_posts(): void {
		$this->create_post( 'Latest post' );

		$this->assertStringNotContainsString( 'Latest post', $this->render_block( 0 ) );
	}

	public function test_other_query_loops_are_left_alone(): void {
		$query = array(
			'post_type' => 'page',
			'orderby'   => 'title',
		);
		$block = new \WP_Block(
			array( 'blockName' => 'core/post-template' ),
			array( 'query' => array( 'postType' => 'page' ) )
		);

		$this->assertSame( $query, \Zoninator()->get_block()->filter_query_vars( $query, $block ) );
	}

	public function test_editor_preview_lists_zone_posts_in_zone_order(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone_id = $this->create_zone();
		$first   = $this->create_post( 'Zone first' );
		$second  = $this->create_post( 'Zone second' );
		$draft   = $this->create_post( 'Zone draft', 'draft' );
		$this->create_post( 'Not in zone' );
		\Zoninator()->add_zone_posts( $zone_id, array( $second, $draft, $first ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'zoninatorZone', $zone_id );
		$request->set_param( 'orderby', 'date' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $second, $first ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	public function test_editor_preview_without_a_zone_is_left_alone(): void {
		$older = self::factory()->post->create( array( 'post_date' => '2024-01-01 00:00:00' ) );
		$newer = self::factory()->post->create( array( 'post_date' => '2024-02-01 00:00:00' ) );
		$zone_id = $this->create_zone();
		\Zoninator()->add_zone_posts( $zone_id, array( $older ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $newer, $older ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	public function test_editor_preview_without_a_picked_zone_shows_no_posts(): void {
		$this->create_post( 'Latest post' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'zoninatorZone', 0 );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_editor_preview_leaves_out_private_posts_the_user_can_read(): void {
		// Editors can read private posts, so only the zone query's published-only default keeps this one out.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone_id = $this->create_zone();
		$first   = $this->create_post( 'Zone first' );
		$private = $this->create_post( 'Zone private', 'private' );
		$second  = $this->create_post( 'Zone second' );
		\Zoninator()->add_zone_posts( $zone_id, array( $second, $private, $first ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( 'zoninatorZone', $zone_id );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $second, $first ), wp_list_pluck( $response->get_data(), 'id' ) );
	}

	public function test_block_second_page_shows_the_next_zone_posts(): void {
		$zone_id  = $this->create_zone();
		$post_ids = array(
			$this->create_post( 'Zone one' ),
			$this->create_post( 'Zone two' ),
			$this->create_post( 'Zone three' ),
		);
		\Zoninator()->add_zone_posts( $zone_id, $post_ids );

		$_GET['query-1-page'] = '2';
		try {
			$html = $this->render_block( $zone_id, 2 );
		} finally {
			unset( $_GET['query-1-page'] );
		}

		$this->assertStringNotContainsString( 'Zone one', $html );
		$this->assertStringNotContainsString( 'Zone two', $html );
		$this->assertStringContainsString( 'Zone three', $html );
	}

	public function test_editor_script_loads_in_the_block_editor(): void {
		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue( wp_script_is( \Automattic\Zoninator\Block::SCRIPT_HANDLE, 'enqueued' ) );
	}

	private function create_zone(): int {
		$zone = \Zoninator()->insert_zone( 'block-zone', 'Block zone' );

		return (int) $zone['term_id'];
	}

	private function create_post( string $title, string $status = 'publish' ): int {
		return self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
	}

	private function render_block( int $zone_id, int $per_page = 10 ): string {
		$attributes = array(
			'queryId'   => 1,
			'query'     => array(
				'perPage'       => $per_page,
				'pages'         => 0,
				'offset'        => 0,
				'postType'      => 'post',
				'inherit'       => false,
				'zoninatorZone' => $zone_id,
			),
			'namespace' => 'zoninator/zone-posts',
		);

		return do_blocks(
			'<!-- wp:query ' . wp_json_encode( $attributes ) . ' --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->'
		);
	}
}
