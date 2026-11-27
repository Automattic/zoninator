<?php
declare( strict_types = 1 );

/**
 * Integration tests for which posts can be put in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Generator;
use WP_REST_Request;

class ZoninatorZonePostAccessTest extends TestCase {

	/**
	 * @var int
	 */
	private $editor_id;

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * A published post the editor may not read, whose title is an email address.
	 *
	 * @var int
	 */
	private $secret_id;

	public function setUp(): void {
		parent::setUp();

		$this->editor_id  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->zone_id    = self::insert_zone( 'access-zone', 'Access zone' );
		$this->secret_id  = self::factory()->post->create( array( 'post_title' => 'zoninator-canary@example.com' ) );

		// A supported, published post that only the read_post check stops the editor using.
		add_filter(
			'map_meta_cap',
			function ( $caps, $cap, $user_id, $args ) {
				return 'read_post' === $cap && isset( $args[0] ) && $this->secret_id === (int) $args[0] ? array( 'do_not_allow' ) : $caps;
			},
			10,
			4
		);
	}

	public function test_rest_add_rejects_a_post_the_user_cannot_read(): void {
		wp_set_current_user( $this->editor_id );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/zoninator/v1/zones/' . $this->zone_id . '/posts/' . $this->secret_id ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringNotContainsString( 'zoninator-canary@example.com', wp_json_encode( $response->get_data() ) );
		$this->assertSame( '', $this->zone_order( $this->secret_id ) );
	}

	/**
	 * Curating a zone is deciding what's in it. A post already there can be
	 * taken out whether or not the user may read it, and the response says
	 * nothing about it.
	 */
	public function test_rest_remove_takes_out_a_post_the_user_cannot_read(): void {
		wp_set_current_user( $this->editor_id );
		self::add_zone_posts( $this->zone_id, array( $this->secret_id ) );

		$response = rest_do_request( new WP_REST_Request( 'DELETE', '/zoninator/v1/zones/' . $this->zone_id . '/posts/' . $this->secret_id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( '', $this->zone_order( $this->secret_id ) );
	}

	/**
	 * The text search and the latest content both list only posts the user may read.
	 *
	 * @dataProvider data_search_terms
	 *
	 * @param string $term Search term.
	 */
	public function test_rest_search_leaves_out_posts_the_user_cannot_read( string $term ): void {
		wp_set_current_user( $this->editor_id );
		$readable_id = self::factory()->post->create( array( 'post_title' => 'zoninator-canary readable' ) );

		$request = new WP_REST_Request( 'GET', '/zoninator/v1/search' );
		$request->set_param( 'term', $term );
		$response = rest_do_request( $request );
		$found    = wp_list_pluck( $response->get_data(), 'post_id' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $readable_id, $found );
		$this->assertNotContains( $this->secret_id, $found );
		$this->assertStringNotContainsString( 'zoninator-canary@example.com', wp_json_encode( $response->get_data() ) );
	}

	/**
	 * @return Generator<string, array{string}>
	 */
	public static function data_search_terms(): Generator {
		yield 'text search' => array( 'zoninator-canary' );
		yield 'latest content' => array( '' );
	}

	public function test_rest_update_rejects_a_post_the_user_cannot_read(): void {
		wp_set_current_user( $this->editor_id );

		$request = new WP_REST_Request( 'PUT', '/zoninator/v1/zones/' . $this->zone_id . '/posts' );
		$request->set_param( 'post_ids', array( $this->secret_id ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( '', $this->zone_order( $this->secret_id ) );
	}

	/**
	 * Saving the zone's order sends every post in it. One the user may not
	 * read is already there, so it isn't being added, and the order is saved.
	 */
	public function test_rest_update_reorders_a_zone_that_holds_a_post_the_user_cannot_read(): void {
		wp_set_current_user( $this->editor_id );
		$readable_id = self::factory()->post->create();
		self::add_zone_posts( $this->zone_id, array( $this->secret_id, $readable_id ) );

		$request = new WP_REST_Request( 'PUT', '/zoninator/v1/zones/' . $this->zone_id . '/posts' );
		$request->set_param( 'post_ids', array( $readable_id, $this->secret_id ) );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '1', $this->zone_order( $readable_id ) );
		$this->assertSame( '2', $this->zone_order( $this->secret_id ) );
	}

	/**
	 * The screen lists every post in the zone, or saving the zone's order
	 * would drop the ones left out. A post the user may not read keeps its
	 * row, which says nothing about it.
	 */
	public function test_the_zones_screen_lists_a_post_the_user_cannot_read_without_saying_what_it_is(): void {
		wp_set_current_user( $this->editor_id );
		$readable_id = self::factory()->post->create( array( 'post_title' => 'Readable story' ) );
		self::add_zone_posts( $this->zone_id, array( $this->secret_id, $readable_id ) );
		$seen = array();
		add_filter(
			'zoninator_zone_post_columns',
			static function ( $columns, $post ) use ( &$seen ) {
				$seen[] = $post->ID;

				return $columns;
			},
			10,
			2
		);

		$html = self::render_zone_editor( $this->zone_id );

		$this->assertStringContainsString( 'id="zone-post-' . $this->secret_id . '"', $html );
		$this->assertStringContainsString( 'Readable story', $html );
		$this->assertStringNotContainsString( 'zoninator-canary@example.com', $html );
		$this->assertStringNotContainsString( get_permalink( $this->secret_id ), $html );
		// Each row has a Remove button, so the post can still be taken out.
		$this->assertSame( 2, substr_count( $html, 'button-link-delete delete' ) );
		// A site's columns aren't asked to print a post the user may not read.
		$this->assertSame( array( $readable_id ), $seen );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string The post's order in the zone, or '' if it isn't in it.
	 */
	private function zone_order( $post_id ) {
		return (string) get_post_meta( $post_id, self::zones()->get_meta_key( $this->zone_id ), true );
	}
}
