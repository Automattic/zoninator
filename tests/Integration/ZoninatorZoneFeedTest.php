<?php
declare( strict_types = 1 );

/**
 * Integration tests for the public JSON feed at /zones/{slug}/feed.json.
 *
 * They cover the rewrite rule that routes the URL, Feed, which builds the
 * body, and FeedEndpoint::serve(), which sends it.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use WPDieException;

final class ZoninatorZoneFeedTest extends TestCase {

	public function tearDown(): void {
		// The rollback restores the option, but not the rewrite rules held in memory.
		zoninator_container()->get( FeedEndpoint::class )->add_rewrite_rule();
		$this->set_permalink_structure( '' );
		parent::tearDown();
	}

	public function test_feed_url_sets_the_zone_query_var(): void {
		$this->set_permalink_structure( '/%postname%/' );
		zoninator_container()->get( FeedEndpoint::class )->add_rewrite_rule();
		flush_rewrite_rules();

		$this->go_to( home_url( '/zones/homepage/feed.json' ) );

		$this->assertSame( 'homepage', get_query_var( Taxonomy::NAME ) );
	}

	/**
	 * Activation runs after init, so the rule isn't in memory until activate() adds it.
	 */
	public function test_activation_routes_the_feed_url_without_a_flush_from_the_site(): void {
		global $wp_rewrite;

		$this->set_permalink_structure( '/%postname%/' );
		unset( $wp_rewrite->extra_rules_top['^zones/([^/]+)/feed.json/?$'] );
		flush_rewrite_rules( false );

		do_action( 'activate_' . plugin_basename( ZONINATOR_FILE ), false );
		$this->go_to( home_url( '/zones/homepage/feed.json' ) );

		$this->assertSame( 'homepage', get_query_var( Taxonomy::NAME ) );
	}

	public function test_deactivation_removes_the_feed_url(): void {
		$this->set_permalink_structure( '/%postname%/' );

		do_action( 'deactivate_' . plugin_basename( ZONINATOR_FILE ), false );

		$this->assertArrayNotHasKey( '^zones/([^/]+)/feed.json/?$', get_option( 'rewrite_rules' ) );
	}

	public function test_feed_by_slug_lists_published_posts_in_zone_order(): void {
		$zone_id  = $this->create_zone();
		$post_ids = self::factory()->post->create_many( 2 );
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::add_zone_posts( $zone_id, array( $post_ids[1], $draft_id, $post_ids[0] ) );

		$feed = self::feed()->get( 'feed-zone' );

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
		self::add_zone_posts( $zone_id, array( $post_id ) );

		$feed = self::feed()->get( 'feed-zone' );

		$this->assertSame( '', $feed[0]->post_content );
		$this->assertSame( '', $feed[0]->post_excerpt );
	}

	public function test_feed_fields_filter_limits_the_fields(): void {
		$zone_id = $this->create_zone();
		self::add_zone_posts( $zone_id, array( self::factory()->post->create() ) );
		add_filter(
			'zoninator_zone_feed_fields',
			static function () {
				return array( 'ID', 'post_title' );
			}
		);

		$feed = self::feed()->get( 'feed-zone' );

		$this->assertSame( array( 'ID', 'post_title' ), array_keys( (array) $feed[0] ) );
	}

	public function test_there_is_no_feed_for_a_missing_zone(): void {
		$this->assertNull( self::feed()->get( 'no-such-zone' ) );
	}

	/**
	 * An empty zone is still a zone. Its feed is an empty list, not nothing.
	 */
	public function test_feed_for_an_empty_zone_is_an_empty_list(): void {
		$zone_id = self::insert_zone( 'empty-feed-zone' );

		$this->assertSame( array(), self::feed()->get( 'empty-feed-zone' ) );
		$this->assertSame( array(), self::feed()->get( $zone_id ) );
	}

	public function test_feed_filters_get_the_zone_they_were_asked_for(): void {
		$zone_id = $this->create_zone();
		self::add_zone_posts( $zone_id, array( self::factory()->post->create() ) );
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

		$feed = self::feed()->get( 'feed-zone' );

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
		zoninator_container()->get( FeedEndpoint::class )->serve();

		$this->assertSame( '', ob_get_clean() );
	}

	public function test_serve_sends_the_zone_feed_as_json(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'In the feed' ) );
		self::add_zone_posts( $this->create_zone(), array( $post_id ) );

		list( $body, $status ) = $this->serve( 'feed-zone' );

		$this->assertSame( 200, $status );
		$this->assertSame( array( $post_id ), array_column( json_decode( $body, true ), 'ID' ) );
		$this->assertSame( 'In the feed', json_decode( $body, true )[0]['post_title'] );
	}

	public function test_serve_refuses_a_zone_that_does_not_exist(): void {
		list( $body, $status ) = $this->serve( 'no-such-zone' );

		$this->assertSame( 406, $status );
		$this->assertSame( 'null', $body );
	}

	/**
	 * Serves the feed for a zone, as a request for /zones/{slug}/feed.json would.
	 *
	 * wp_send_json() ends the request, through wp_die() when it's an Ajax
	 * request, so make it one and have wp_die() throw.
	 *
	 * @param string $slug Zone slug.
	 * @return array{string, int} The body and the status sent.
	 */
	private function serve( string $slug ): array {
		set_query_var( Taxonomy::NAME, $slug );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static fn() => static function (): void {
				throw new WPDieException();
			}
		);
		$status = 200;
		add_filter(
			'status_header',
			static function ( $header, $code ) use ( &$status ) {
				$status = $code;
				return $header;
			},
			10,
			2
		);

		ob_start();
		try {
			zoninator_container()->get( FeedEndpoint::class )->serve();
		} catch ( WPDieException $exception ) {
			return array( (string) ob_get_clean(), $status );
		}
		ob_end_clean();

		$this->fail( 'Expected the feed to end the request.' );
	}

	private function create_zone(): int {
		return self::insert_zone( 'feed-zone', 'Feed zone' );
	}
}
