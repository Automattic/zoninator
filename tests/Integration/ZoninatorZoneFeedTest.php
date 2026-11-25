<?php
declare( strict_types = 1 );

/**
 * Integration tests for the public JSON feed at /zones/{slug}/feed.json.
 *
 * do_zoninator_feeds() echoes the feed and exits, so these tests cover the
 * rewrite rule that routes the URL and get_zone_feed(), which builds the body.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

final class ZoninatorZoneFeedTest extends TestCase {

	public function tearDown(): void {
		// The rollback restores the option, but not the rewrite rules held in memory.
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	public function test_feed_url_sets_the_zone_query_var(): void {
		$this->set_permalink_structure( '/%postname%/' );
		zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Feed::class )->add_rewrite_rule();
		flush_rewrite_rules();

		$this->go_to( home_url( '/zones/homepage/feed.json' ) );

		$this->assertSame( 'homepage', get_query_var( \Zoninator::ZONE_TAXONOMY ) );
	}

	public function test_feed_by_slug_lists_published_posts_in_zone_order(): void {
		$zone_id  = $this->create_zone();
		$post_ids = self::factory()->post->create_many( 2 );
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		\Zoninator()->add_zone_posts( $zone_id, array( $post_ids[1], $draft_id, $post_ids[0] ) );

		$feed = \Zoninator()->get_zone_feed( 'feed-zone' );

		$this->assertSame( array( $post_ids[1], $post_ids[0] ), array_map( 'intval', wp_list_pluck( $feed, 'ID' ) ) );
		$this->assertSame(
			array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' ),
			array_keys( (array) $feed[0] )
		);
	}

	public function test_feed_hides_content_of_password_protected_posts(): void {
		$zone_id = $this->create_zone();
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'Secret content',
				'post_excerpt'  => 'Secret excerpt',
				'post_password' => 'secret',
			)
		);
		\Zoninator()->add_zone_posts( $zone_id, array( $post_id ) );

		$feed = \Zoninator()->get_zone_feed( 'feed-zone' );

		$this->assertSame( '', $feed[0]->post_content );
		$this->assertSame( '', $feed[0]->post_excerpt );
	}

	public function test_feed_fields_filter_limits_the_fields(): void {
		$zone_id = $this->create_zone();
		\Zoninator()->add_zone_posts( $zone_id, array( self::factory()->post->create() ) );
		add_filter(
			'zoninator_zone_feed_fields',
			static function () {
				return array( 'ID', 'post_title' );
			}
		);

		$feed = \Zoninator()->get_zone_feed( 'feed-zone' );

		$this->assertSame( array( 'ID', 'post_title' ), array_keys( (array) $feed[0] ) );
	}

	public function test_feed_for_a_missing_zone_is_an_error(): void {
		$feed = \Zoninator()->get_zone_feed( 'no-such-zone' );

		$this->assertWPError( $feed );
		$this->assertSame( 'invalid-zone-supplied', $feed->get_error_code() );
	}

	public function test_feed_filters_get_the_zone_they_were_asked_for(): void {
		$zone_id = $this->create_zone();
		\Zoninator()->add_zone_posts( $zone_id, array( self::factory()->post->create() ) );
		$seen    = array();
		$args    = static function ( $args, $zone ) use ( &$seen ) {
			$seen[] = array( 'args', $args, $zone );
			return $args;
		};
		$results = static function ( $results, $zone ) use ( &$seen ) {
			$seen[] = array( 'results', count( $results ), $zone );
			return array( 'replaced' );
		};
		add_filter( 'zoninator_json_feed_fields', $args, 10, 2 );
		add_filter( 'zoninator_json_feed_results', $results, 10, 2 );

		$feed = \Zoninator()->get_zone_feed( 'feed-zone' );

		$this->assertSame( array( 'replaced' ), $feed );
		$this->assertSame(
			array(
				array( 'args', array(), 'feed-zone' ),
				array( 'results', 1, 'feed-zone' ),
			),
			$seen
		);
	}

	public function test_a_request_without_the_feed_query_var_is_left_alone(): void {
		ob_start();
		zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\Feed::class )->serve();

		$this->assertSame( '', ob_get_clean() );
	}

	private function create_zone(): int {
		$zone = \Zoninator()->insert_zone( 'feed-zone', 'Feed zone' );

		return (int) $zone['term_id'];
	}
}
