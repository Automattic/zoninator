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

use WP_Error;

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

		$zone           = Zoninator()->insert_zone( 'data-zone', 'Data zone', array( 'description' => 'Kept' ) );
		$this->zone_id  = (int) $zone['term_id'];
		$this->post_ids = self::factory()->post->create_many( 3 );
		Zoninator()->add_zone_posts( $this->zone_id, $this->post_ids );
	}

	public function test_insert_zone_needs_a_slug(): void {
		$result = Zoninator()->insert_zone( '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'zone-empty-slug', $result->get_error_code() );
	}

	public function test_insert_zone_names_the_zone_after_its_slug_by_default(): void {
		$zone = Zoninator()->insert_zone( 'unnamed-zone' );

		$this->assertSame( 'unnamed-zone', Zoninator()->get_zone( (int) $zone['term_id'] )->name );
	}

	public function test_insert_zone_filters_the_term_args(): void {
		$filter = static function ( $args ) {
			$args['slug'] = 'filtered-slug';
			return $args;
		};
		add_filter( 'zoninator_insert_zone', $filter );

		$zone = Zoninator()->insert_zone( 'original-slug', 'Filtered' );

		remove_filter( 'zoninator_insert_zone', $filter );
		$this->assertSame( 'filtered-slug', Zoninator()->get_zone( (int) $zone['term_id'] )->slug );
	}

	public function test_update_zone_with_an_empty_description_clears_it(): void {
		Zoninator()->update_zone( $this->zone_id, array( 'details' => array( 'description' => '' ) ) );

		$this->assertSame( '', Zoninator()->get_zone( $this->zone_id )->description );
	}

	public function test_update_zone_keeps_details_it_was_not_sent(): void {
		Zoninator()->update_zone( $this->zone_id, array( 'name' => 'Renamed' ) );

		$zone = Zoninator()->get_zone( $this->zone_id );
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

		Zoninator()->update_zone( $this->zone_id, array( 'name' => 'Renamed' ) );

		remove_filter( 'zoninator_update_zone', $filter, 10 );
		$this->assertSame( array( $this->zone_id, $this->zone_id ), $seen );
	}

	public function test_update_and_delete_refuse_a_missing_zone(): void {
		foreach ( array( Zoninator()->update_zone( 999999 ), Zoninator()->delete_zone( 999999 ) ) as $result ) {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'invalid-zone', $result->get_error_code() );
		}
	}

	public function test_delete_zone_fires_its_action(): void {
		$deleted = null;
		$action  = static function ( $zone_id ) use ( &$deleted ) {
			$deleted = $zone_id;
		};
		add_action( 'zoninator_delete_zone', $action );

		$result = Zoninator()->delete_zone( $this->zone_id );

		remove_action( 'zoninator_delete_zone', $action );
		$this->assertTrue( $result );
		$this->assertSame( $this->zone_id, $deleted );
		$this->assertFalse( Zoninator()->zone_exists( $this->zone_id ) );
	}

	public function test_add_zone_posts_appends_or_replaces(): void {
		$new_id = self::factory()->post->create();

		Zoninator()->add_zone_posts( $this->zone_id, $new_id, true );
		$this->assertSame( array_merge( $this->post_ids, array( $new_id ) ), $this->zone_post_ids() );

		Zoninator()->add_zone_posts( $this->zone_id, array( $new_id, $this->post_ids[0] ) );
		$this->assertSame( array( $new_id, $this->post_ids[0] ), $this->zone_post_ids() );
	}

	public function test_add_and_remove_zone_posts_fire_their_actions(): void {
		$fired  = array();
		$record = static function ( $posts, $zone ) use ( &$fired ) {
			$fired[] = array( current_action(), $posts, $zone->term_id );
		};
		add_action( 'zoninator_add_zone_posts', $record, 10, 2 );
		add_action( 'zoninator_remove_zone_posts', $record, 10, 2 );

		$new_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $this->zone_id, $new_id, true );
		Zoninator()->remove_zone_posts( $this->zone_id, $new_id );

		remove_action( 'zoninator_add_zone_posts', $record, 10 );
		remove_action( 'zoninator_remove_zone_posts', $record, 10 );
		$this->assertSame(
			array(
				array( 'zoninator_add_zone_posts', $new_id, $this->zone_id ),
				array( 'zoninator_remove_zone_posts', $new_id, $this->zone_id ),
			),
			$fired
		);
	}

	public function test_remove_zone_posts_without_posts_empties_the_zone(): void {
		Zoninator()->remove_zone_posts( $this->zone_id );

		$this->assertSame( array(), $this->zone_post_ids() );
	}

	public function test_first_and_last_post_in_zone(): void {
		$this->assertSame( $this->post_ids[0], Zoninator()->get_first_post_in_zone( $this->zone_id )->ID );
		$this->assertSame( $this->post_ids[2], Zoninator()->get_last_post_in_zone( $this->zone_id )->ID );
	}

	public function test_first_post_in_an_empty_zone_is_false(): void {
		$zone = Zoninator()->insert_zone( 'empty-zone' );

		$this->assertFalse( Zoninator()->get_first_post_in_zone( (int) $zone['term_id'] ) );
	}

	public function test_post_order_is_its_position_in_the_zone(): void {
		$this->assertSame( '2', Zoninator()->get_post_order( $this->post_ids[1], $this->zone_id ) );
		$this->assertSame( '', Zoninator()->get_post_order( self::factory()->post->create(), $this->zone_id ) );
	}

	public function test_zone_query_always_orders_by_zone_position(): void {
		$query = Zoninator()->get_zone_query(
			$this->zone_id,
			array(
				'orderby'  => 'title',
				'meta_key' => 'something_else', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$this->assertSame( 'meta_value_num', $query->get( 'orderby' ) );
		$this->assertSame( Zoninator()->get_zone_meta_key( $this->zone_id ), $query->get( 'meta_key' ) );
		$this->assertSame( $this->post_ids, wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_zone_lookups_accept_an_id_slug_or_term(): void {
		$zone = Zoninator()->get_zone( $this->zone_id );

		foreach ( array( $this->zone_id, 'data-zone', $zone ) as $reference ) {
			$this->assertSame( $this->zone_id, Zoninator()->get_zone_id( $reference ) );
			$this->assertSame( '_zoninator_order_' . $this->zone_id, Zoninator()->get_zone_meta_key( $reference ) );
			$this->assertSame( 'data-zone', Zoninator()->get_zone_slug( $reference ) );
			$this->assertTrue( Zoninator()->zone_exists( $reference ) );
		}

		$this->assertSame( 0, Zoninator()->get_zone_id( 'no-such-zone' ) );
		$this->assertFalse( Zoninator()->zone_exists( 'no-such-zone' ) );
	}

	public function test_get_post_id_accepts_an_id_array_or_object(): void {
		$post = get_post( $this->post_ids[0] );

		$this->assertSame( $post->ID, Zoninator()->get_post_id( $post->ID ) );
		$this->assertSame( $post->ID, Zoninator()->get_post_id( array( 'ID' => (string) $post->ID ) ) );
		$this->assertSame( $post->ID, Zoninator()->get_post_id( $post ) );
		$this->assertFalse( Zoninator()->get_post_id( (string) $post->ID ) );
	}

	public function test_split_shared_term_moves_the_posts_to_the_new_zone(): void {
		$new_zone = Zoninator()->insert_zone( 'split-zone' );
		$new_id   = (int) $new_zone['term_id'];
		$fired    = array();
		$record   = static function ( ...$args ) use ( &$fired ) {
			$fired[] = array_merge( array( current_action() ), $args );
		};
		add_action( 'zoninator_split_shared_term', $record, 10, 3 );
		add_action( 'zoninator_did_split_shared_term', $record, 10, 3 );

		Zoninator()->split_shared_term( $this->zone_id, $new_id, 123, 'zoninator_zones' );

		remove_action( 'zoninator_split_shared_term', $record, 10 );
		remove_action( 'zoninator_did_split_shared_term', $record, 10 );
		$this->assertSame( array(), $this->zone_post_ids() );
		$this->assertSame( $this->post_ids, wp_list_pluck( Zoninator()->get_zone_posts( $new_id ), 'ID' ) );
		$this->assertSame(
			array(
				array( 'zoninator_split_shared_term', $this->zone_id, $new_id, 123 ),
				array( 'zoninator_did_split_shared_term', $this->zone_id, $new_id, 123 ),
			),
			$fired
		);
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

		Zoninator()->add_zone_posts( $this->zone_id, array( $this->post_ids[1], $this->post_ids[0] ) );

		remove_filter( 'posts_where', $filter );
		$this->assertSame( array( $this->post_ids[1], $this->post_ids[0] ), $this->zone_post_ids() );
	}

	public function test_appending_a_post_counts_a_post_that_query_filters_hide(): void {
		$filter = $this->hide_post_from_query_filters( $this->post_ids[2] );
		$new_id = self::factory()->post->create();

		Zoninator()->add_zone_posts( $this->zone_id, array( $new_id ), true );

		remove_filter( 'posts_where', $filter );
		$this->assertSame( '4', Zoninator()->get_post_order( $new_id, $this->zone_id ) );
	}

	public function test_split_shared_term_moves_a_post_that_query_filters_hide(): void {
		$new_zone = Zoninator()->insert_zone( 'split-zone' );
		$new_id   = (int) $new_zone['term_id'];
		$filter   = $this->hide_post_from_query_filters( $this->post_ids[2] );

		Zoninator()->split_shared_term( $this->zone_id, $new_id, 123, 'zoninator_zones' );

		remove_filter( 'posts_where', $filter );
		$this->assertSame( array(), $this->zone_post_ids() );
		$this->assertSame( $this->post_ids, wp_list_pluck( Zoninator()->get_zone_posts( $new_id ), 'ID' ) );
	}

	public function test_split_shared_term_moves_a_scheduled_post_too(): void {
		$scheduled_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) ),
			)
		);
		Zoninator()->add_zone_posts( $this->zone_id, array( $scheduled_id ), true );
		$new_zone = Zoninator()->insert_zone( 'split-scheduled-zone' );
		$new_id   = (int) $new_zone['term_id'];

		Zoninator()->split_shared_term( $this->zone_id, $new_id, 123, 'zoninator_zones' );

		$this->assertSame( '', get_post_meta( $scheduled_id, Zoninator()->get_zone_meta_key( $this->zone_id ), true ) );
		$this->assertSame( '4', get_post_meta( $scheduled_id, Zoninator()->get_zone_meta_key( $new_id ), true ) );
	}

	public function test_split_shared_term_ignores_other_taxonomies(): void {
		$new_zone = Zoninator()->insert_zone( 'split-zone' );

		Zoninator()->split_shared_term( $this->zone_id, (int) $new_zone['term_id'], 123, 'category' );

		$this->assertSame( $this->post_ids, $this->zone_post_ids() );
	}

	public function test_split_shared_term_is_hooked_on_the_facade(): void {
		global $wp_filter;

		$this->assertSame( 10, has_action( 'split_shared_term', array( Zoninator(), 'split_shared_term' ) ) );

		$callbacks = $wp_filter['split_shared_term']->callbacks[10];
		$callback  = current(
			array_filter(
				$callbacks,
				static function ( $entry ) {
					return array( Zoninator(), 'split_shared_term' ) === $entry['function'];
				}
			)
		);
		$this->assertSame( 4, $callback['accepted_args'] );
	}

	/**
	 * @return int[] IDs of the posts in the test zone, in order.
	 */
	private function zone_post_ids(): array {
		return wp_list_pluck( Zoninator()->get_zone_posts( $this->zone_id ), 'ID' );
	}
}
