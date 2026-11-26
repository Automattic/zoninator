<?php
declare( strict_types = 1 );

/**
 * Integration tests for the zone abilities.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Generator;
use WP_Error;

final class ZoninatorAbilitiesTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function setUp(): void {
		parent::setUp();

		$this->zone_id = self::insert_zone( 'abilities-zone', 'Abilities zone', array( 'description' => 'For the abilities tests' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * @dataProvider data_ability_names
	 *
	 * @param string $name Ability name.
	 */
	public function test_ability_is_registered_as_read_only( string $name ): void {
		$ability = wp_get_ability( $name );

		$this->assertNotNull( $ability );
		$this->assertSame( 'zoninator', $ability->get_category() );
		$this->assertTrue( $ability->get_meta_item( 'annotations' )['readonly'] );
		$this->assertTrue( $ability->get_meta_item( 'show_in_rest' ) );
		$this->assertTrue( $ability->get_meta_item( 'mcp' )['public'] );
	}

	/**
	 * @dataProvider data_ability_names
	 *
	 * @param string $name Ability name.
	 */
	public function test_ability_denies_a_user_who_cannot_manage_zones( string $name ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$input  = 'zoninator/get-zone-posts' === $name ? array( 'zone_id' => $this->zone_id ) : null;
		$result = wp_get_ability( $name )->execute( $input );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	/**
	 * @return Generator<string, array{string}>
	 */
	public static function data_ability_names(): Generator {
		foreach ( array( 'zoninator/list-zones', 'zoninator/get-zone-posts', 'zoninator/search-posts' ) as $name ) {
			yield $name => array( $name );
		}
	}

	public function test_list_zones_returns_the_zones(): void {
		$result = wp_get_ability( 'zoninator/list-zones' )->execute();

		$this->assertIsArray( $result );
		$this->assertContains(
			array(
				'term_id'     => $this->zone_id,
				'slug'        => 'abilities-zone',
				'name'        => 'Abilities zone',
				'description' => 'For the abilities tests',
			),
			$result
		);
	}

	public function test_get_zone_posts_returns_the_posts_in_order(): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second' ) );
		self::add_zone_posts( $this->zone_id, array( $second, $first ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $second, $first ), array_map( 'intval', wp_list_pluck( $result, 'ID' ) ) );
		$this->assertSame( 'Second', $result[0]['post_title'] );
	}

	public function test_get_zone_posts_includes_scheduled_posts(): void {
		$scheduled = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		self::add_zone_posts( $this->zone_id, array( $scheduled, $draft ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $scheduled ), wp_list_pluck( $result, 'ID' ) );
		$this->assertSame( 'future', $result[0]['post_status'] );
	}

	public function test_get_zone_posts_leaves_out_posts_the_user_cannot_read(): void {
		$readable   = self::factory()->post->create();
		$unreadable = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		self::add_zone_posts( $this->zone_id, array( $readable, $unreadable ) );

		add_filter(
			'map_meta_cap',
			static function ( $caps, $cap, $user_id, $args ) use ( $unreadable ) {
				return 'read_post' === $cap && isset( $args[0] ) && $unreadable === (int) $args[0] ? array( 'do_not_allow' ) : $caps;
			},
			10,
			4
		);

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $readable ), wp_list_pluck( $result, 'ID' ) );
	}

	public function test_get_zone_posts_returns_only_the_summary_fields(): void {
		$post_id = self::factory()->post->create( array( 'post_content' => 'Long body' ) );
		self::add_zone_posts( $this->zone_id, array( $post_id ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( 'ID', 'post_date', 'post_title', 'post_status' ), array_keys( $result[0] ) );
	}

	public function test_get_zone_posts_ignores_the_feed_filters(): void {
		$post_id = self::factory()->post->create();
		self::add_zone_posts( $this->zone_id, array( $post_id ) );

		$reshape = static fn() => array( array( 'reshaped' => true ) );
		add_filter( 'zoninator_json_feed_results', $reshape );
		add_filter( 'zoninator_zone_feed_fields', static fn() => array( 'post_content' ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result, 'ID' ) );
	}

	public function test_get_zone_posts_rejects_an_unknown_zone(): void {
		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	public function test_get_zone_posts_needs_a_zone_id(): void {
		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_search_posts_finds_a_post_by_id(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Find me' ) );

		$result = wp_get_ability( 'zoninator/search-posts' )->execute( array( 'term' => (string) $post_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $post_id ), wp_list_pluck( $result, 'post_id' ) );
		$this->assertSame( 'Find me', $result[0]['title'] );
	}

	public function test_search_posts_leaves_out_excluded_posts(): void {
		$kept     = self::factory()->post->create();
		$excluded = self::factory()->post->create();

		$result = wp_get_ability( 'zoninator/search-posts' )->execute( array( 'exclude' => array( $excluded ) ) );

		$ids = wp_list_pluck( $result, 'post_id' );
		$this->assertContains( $kept, $ids );
		$this->assertNotContains( $excluded, $ids );
	}

	public function test_search_posts_with_no_input_lists_the_latest_posts(): void {
		$post_id = self::factory()->post->create();

		$result = wp_get_ability( 'zoninator/search-posts' )->execute();

		$this->assertIsArray( $result );
		$this->assertContains( $post_id, wp_list_pluck( $result, 'post_id' ) );
	}

	public function test_search_posts_accepts_a_comma_separated_exclude_list(): void {
		$excluded = self::factory()->post->create();

		$result = wp_get_ability( 'zoninator/search-posts' )->execute( array( 'exclude' => (string) $excluded ) );

		$this->assertIsArray( $result );
		$this->assertNotContains( $excluded, wp_list_pluck( $result, 'post_id' ) );
	}

	public function test_get_zone_posts_accepts_object_input(): void {
		$post_id = self::factory()->post->create();
		self::add_zone_posts( $this->zone_id, array( $post_id ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( (object) array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $post_id ), array_map( 'intval', wp_list_pluck( $result, 'ID' ) ) );
	}

	public function test_search_posts_rejects_an_unknown_zone(): void {
		$result = wp_get_ability( 'zoninator/search-posts' )->execute( array( 'zone_id' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * @dataProvider data_write_ability_names
	 *
	 * @param string $name Ability name.
	 */
	public function test_write_ability_is_registered_and_kept_off_the_default_mcp_server( string $name ): void {
		$ability = wp_get_ability( $name );

		$this->assertNotNull( $ability );
		$this->assertSame( 'zoninator', $ability->get_category() );
		$this->assertFalse( $ability->get_meta_item( 'annotations' )['readonly'] );
		$this->assertFalse( $ability->get_meta_item( 'mcp' )['public'] );
	}

	/**
	 * @dataProvider data_write_ability_names
	 *
	 * @param string $name Ability name.
	 */
	public function test_write_ability_denies_a_user_who_cannot_manage_zones( string $name ): void {
		list( $kept, $other ) = self::factory()->post->create_many( 2 );
		self::add_zone_posts( $this->zone_id, array( $kept ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		// Each ability would change the zone with these: add or set $other, or remove $kept.
		$result = wp_get_ability( $name )->execute(
			array(
				'zone_id'  => $this->zone_id,
				'post_ids' => array( $kept, $other ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( array( $kept ), $this->zone_post_ids() );
	}

	/**
	 * @return Generator<string, array{string}>
	 */
	public static function data_write_ability_names(): Generator {
		foreach ( array( 'zoninator/add-zone-posts', 'zoninator/set-zone-posts', 'zoninator/remove-zone-posts' ) as $name ) {
			yield $name => array( $name );
		}
	}

	public function test_the_write_abilities_add_order_and_remove_posts(): void {
		list( $first, $second, $third ) = self::factory()->post->create_many( 3 );

		$this->assertSame( array( 'success' => true ), $this->change( 'add', array( $first, $second ) ) );
		$this->change( 'add', array( $third ) );
		$this->assertSame( array( $first, $second, $third ), $this->zone_post_ids() );

		$this->change( 'set', array( $third, $first, $second ) );
		$this->assertSame( array( $third, $first, $second ), $this->zone_post_ids() );

		$this->change( 'remove', array( $first ) );
		$this->assertSame( array( $third, $second ), $this->zone_post_ids() );
	}

	public function test_a_write_ability_rejects_a_post_that_cannot_go_in_a_zone(): void {
		$result = $this->change( 'add', array( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$this->assertSame( 'invalid-post-id', $result->get_error_code() );
		$this->assertSame( array(), $this->zone_post_ids() );
	}

	public function test_a_write_ability_rejects_an_unknown_zone(): void {
		$result = wp_get_ability( 'zoninator/add-zone-posts' )->execute(
			array(
				'zone_id'  => 999999,
				'post_ids' => array( self::factory()->post->create() ),
			)
		);

		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	/**
	 * An agent acting for one user must not save over another user editing the zone.
	 */
	public function test_a_write_ability_respects_someone_elses_lock(): void {
		$holder_id = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'display_name' => 'Lock Holder',
			)
		);
		self::locker()->lock( $this->zone_id, $holder_id );

		$result = $this->change( 'add', array( self::factory()->post->create() ) );

		$this->assertSame( 'zone-locked', $result->get_error_code() );
		$this->assertStringContainsString( 'Lock Holder', $result->get_error_message() );
		$this->assertSame( array(), $this->zone_post_ids() );
	}

	/**
	 * @param string $verb     'add', 'set' or 'remove'.
	 * @param int[]  $post_ids Post IDs.
	 * @return array|WP_Error
	 */
	private function change( string $verb, array $post_ids ) {
		return wp_get_ability( "zoninator/$verb-zone-posts" )->execute(
			array(
				'zone_id'  => $this->zone_id,
				'post_ids' => $post_ids,
			)
		);
	}

	/**
	 * @return int[] IDs of the posts in the test zone, in order, whatever their status.
	 */
	private function zone_post_ids(): array {
		return wp_list_pluck( self::zones()->get_posts( $this->zone_id, array( 'post_status' => 'any' ) ), 'ID' );
	}
}
