<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how Zoninator stores zones and their posts.
 *
 * Covers the zone data methods other tests reach only indirectly, so they
 * can move out of the Zoninator class without changing what they do.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;

class ZoninatorZoneDataTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var int[]
	 */
	private $post_ids;

	public function set_up(): void {
		parent::set_up();

		$this->zone_id  = self::insert_zone( 'data-zone', 'Data zone', array( 'description' => 'Kept' ) );
		$this->post_ids = self::factory()->post->create_many( 3 );
		self::add_zone_posts( $this->zone_id, $this->post_ids );
	}

	public function test_insert_zone_needs_a_slug(): void {
		$this->expectException( InvalidZoneException::class );

		self::insert_zone( '' );
	}

	public function test_insert_zone_names_the_zone_after_its_slug_by_default(): void {
		$zone_id = self::insert_zone( 'unnamed-zone' );

		$this->assertSame( 'unnamed-zone', self::zones()->get( $zone_id )->name );
	}

	public function test_insert_zone_filters_the_term_args(): void {
		$filter = static function ( $args ) {
			$args['slug'] = 'filtered-slug';
			return $args;
		};
		add_filter( 'zoninator_insert_zone', $filter );

		$zone_id = self::insert_zone( 'original-slug', 'Filtered' );

		remove_filter( 'zoninator_insert_zone', $filter );
		$this->assertSame( 'filtered-slug', self::zones()->get( $zone_id )->slug );
	}

	public function test_update_zone_with_an_empty_description_clears_it(): void {
		self::update_zone( $this->zone_id, array( 'details' => array( 'description' => '' ) ) );

		$this->assertSame( '', self::zones()->get( $this->zone_id )->description );
	}

	public function test_update_zone_keeps_details_it_was_not_sent(): void {
		self::update_zone( $this->zone_id, array( 'name' => 'Renamed' ) );

		$zone = self::zones()->get( $this->zone_id );
		$this->assertSame( 'Renamed', $zone->name );
		$this->assertSame( 'Kept', $zone->description );
	}

	public function test_update_zone_filters_the_term_args_with_the_zone(): void {
		$seen   = null;
		$filter = static function ( $args, $zone_id, $zone ) use ( &$seen ) {
			$seen = array( $zone_id, $zone->term_id );
			return $args;
		};
		add_filter( 'zoninator_update_zone', $filter, 10, 3 );

		self::update_zone( $this->zone_id, array( 'name' => 'Renamed' ) );

		remove_filter( 'zoninator_update_zone', $filter, 10 );
		$this->assertSame( array( $this->zone_id, $this->zone_id ), $seen );
	}

	/**
	 * @dataProvider data_changes_to_a_missing_zone
	 *
	 * @param string $change 'update' or 'delete'.
	 */
	public function test_update_and_delete_refuse_a_missing_zone( string $change ): void {
		$this->expectException( ZoneNotFoundException::class );

		if ( 'update' === $change ) {
			self::update_zone( 999999 );
		} else {
			self::delete_zone( 999999 );
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_changes_to_a_missing_zone(): array {
		return array(
			'update' => array( 'update' ),
			'delete' => array( 'delete' ),
		);
	}

	public function test_delete_zone_fires_its_action(): void {
		$deleted = null;
		$action  = static function ( $zone_id ) use ( &$deleted ) {
			$deleted = $zone_id;
		};
		add_action( 'zoninator_delete_zone', $action );

		self::delete_zone( $this->zone_id );

		remove_action( 'zoninator_delete_zone', $action );
		$this->assertSame( $this->zone_id, $deleted );
		$this->assertFalse( self::zones()->get( $this->zone_id ) );
	}

	public function test_add_zone_posts_appends_or_replaces(): void {
		$new_id = self::factory()->post->create();

		self::add_zone_posts( $this->zone_id, $new_id, true );
		$this->assertSame( array_merge( $this->post_ids, array( $new_id ) ), $this->zone_post_ids() );

		self::add_zone_posts( $this->zone_id, array( $new_id, $this->post_ids[0] ) );
		$this->assertSame( array( $new_id, $this->post_ids[0] ), $this->zone_post_ids() );
	}

	/**
	 * The actions get post IDs, however the posts were passed in.
	 */
	public function test_add_and_remove_zone_posts_fire_their_actions(): void {
		$fired  = array();
		$record = static function ( $posts, $zone ) use ( &$fired ) {
			$fired[] = array( current_action(), $posts, $zone->term_id );
		};
		add_action( 'zoninator_add_zone_posts', $record, 10, 2 );
		add_action( 'zoninator_remove_zone_posts', $record, 10, 2 );

		$new_id = self::factory()->post->create();
		self::add_zone_posts( $this->zone_id, $new_id, true );
		self::remove_zone_posts( $this->zone_id, $new_id );

		remove_action( 'zoninator_add_zone_posts', $record, 10 );
		remove_action( 'zoninator_remove_zone_posts', $record, 10 );
		$this->assertSame(
			array(
				array( 'zoninator_add_zone_posts', array( $new_id ), $this->zone_id ),
				array( 'zoninator_remove_zone_posts', array( $new_id ), $this->zone_id ),
			),
			$fired
		);
	}

	public function test_remove_zone_posts_without_posts_empties_the_zone(): void {
		self::remove_zone_posts( $this->zone_id );

		$this->assertSame( array(), $this->zone_post_ids() );
	}

	public function test_the_post_beside_another_can_be_asked_for_by_id_or_post(): void {
		$this->assertSame( $this->post_ids[1], self::zones()->get_next_post( $this->zone_id, $this->post_ids[0] )->ID );
		$this->assertSame( $this->post_ids[1], self::zones()->get_prev_post( 'data-zone', get_post( $this->post_ids[2] ) )->ID );
		$this->assertFalse( self::zones()->get_next_post( $this->zone_id, $this->post_ids[2] ) );
		$this->assertFalse( self::zones()->get_next_post( $this->zone_id, self::factory()->post->create() ) );
	}

	public function test_post_order_is_its_position_in_the_zone(): void {
		$this->assertSame( '2', self::zones()->get_post_order( $this->post_ids[1], $this->zone_id ) );
		$this->assertSame( '', self::zones()->get_post_order( self::factory()->post->create(), $this->zone_id ) );
	}

	public function test_zone_query_always_orders_by_zone_position(): void {
		$query = self::zones()->get_query(
			$this->zone_id,
			array(
				'orderby'  => 'title',
				'meta_key' => 'something_else', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$this->assertSame( 'meta_value_num', $query->get( 'orderby' ) );
		$this->assertSame( self::zones()->get_meta_key( $this->zone_id ), $query->get( 'meta_key' ) );
		$this->assertSame( $this->post_ids, wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_zone_lookups_accept_an_id_slug_or_term(): void {
		$zone = self::zones()->get( $this->zone_id );

		foreach ( array( $this->zone_id, 'data-zone', $zone ) as $reference ) {
			$this->assertSame( $this->zone_id, self::zones()->get_id( $reference ) );
			$this->assertSame( '_zoninator_order_' . $this->zone_id, self::zones()->get_meta_key( $reference ) );
		}

		$this->assertSame( 0, self::zones()->get_id( 'no-such-zone' ) );
		$this->assertFalse( self::zones()->get( 'no-such-zone' ) );
	}

	/**
	 * Hides a post from every query that runs the posts_where filter, as a
	 * plugin that filters posts by language or access might.
	 *
	 * @param int $post_id Post to hide.
	 * @return callable The filter, to remove afterwards.
	 */
	private function hide_post_from_query_filters( int $post_id ): callable {
		$filter = static function ( $where ) use ( $post_id ) {
			global $wpdb;
			return $where . " AND {$wpdb->posts}.ID != {$post_id}";
		};
		add_filter( 'posts_where', $filter );

		return $filter;
	}

	public function test_setting_zone_posts_clears_a_post_that_query_filters_hide(): void {
		$filter = $this->hide_post_from_query_filters( $this->post_ids[2] );

		self::add_zone_posts( $this->zone_id, array( $this->post_ids[1], $this->post_ids[0] ) );

		remove_filter( 'posts_where', $filter );
		$this->assertSame( array( $this->post_ids[1], $this->post_ids[0] ), $this->zone_post_ids() );
	}

	public function test_appending_a_post_counts_a_post_that_query_filters_hide(): void {
		$filter = $this->hide_post_from_query_filters( $this->post_ids[2] );
		$new_id = self::factory()->post->create();

		self::add_zone_posts( $this->zone_id, array( $new_id ), true );

		remove_filter( 'posts_where', $filter );
		$this->assertSame( '4', self::zones()->get_post_order( $new_id, $this->zone_id ) );
	}

	/**
	 * The first post is the one a stray argument to update_metadata() used to move.
	 */
	public function test_appending_posts_already_in_the_zone_leaves_them_where_they_are(): void {
		$fired  = array();
		$record = static function ( $post_ids ) use ( &$fired ) {
			$fired[] = $post_ids;
		};
		add_action( 'zoninator_add_zone_posts', $record );

		self::add_zone_posts( $this->zone_id, array( $this->post_ids[0], $this->post_ids[2] ), true );

		remove_action( 'zoninator_add_zone_posts', $record );
		$this->assertSame( $this->post_ids, $this->zone_post_ids() );
		$this->assertSame( array(), $fired, 'Nothing was added, so the action should not fire.' );
	}

	/**
	 * @return int[] IDs of the posts in the test zone, in order.
	 */
	private function zone_post_ids(): array {
		return wp_list_pluck( self::zones()->get_posts( $this->zone_id ), 'ID' );
	}
}
