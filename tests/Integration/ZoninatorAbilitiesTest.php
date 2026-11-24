<?php
declare( strict_types = 1 );

/**
 * Integration tests for the zone abilities.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Error;

final class ZoninatorAbilitiesTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API needs WordPress 6.9 or later.' );
		}

		$zone          = Zoninator()->insert_zone( 'abilities-zone', 'Abilities zone', array( 'description' => 'For the abilities tests' ) );
		$this->zone_id = (int) $zone['term_id'];

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
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_ability_names(): array {
		$names = array( 'zoninator/list-zones', 'zoninator/get-zone-posts', 'zoninator/search-posts' );

		return array_combine( $names, array_map( static fn( $name ) => array( $name ), $names ) );
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
		Zoninator()->add_zone_posts( $this->zone_id, array( $second, $first ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $second, $first ), array_map( 'intval', wp_list_pluck( $result, 'ID' ) ) );
		$this->assertSame( 'Second', $result[0]['post_title'] );
	}

	public function test_get_zone_posts_rejects_an_unknown_zone(): void {
		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array( 'zone_id' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}

	public function test_get_zone_posts_needs_a_zone_id(): void {
		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
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
		Zoninator()->add_zone_posts( $this->zone_id, array( $post_id ) );

		$result = wp_get_ability( 'zoninator/get-zone-posts' )->execute( (object) array( 'zone_id' => $this->zone_id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( $post_id ), array_map( 'intval', wp_list_pluck( $result, 'ID' ) ) );
	}

	public function test_search_posts_rejects_an_unknown_zone(): void {
		$result = wp_get_ability( 'zoninator/search-posts' )->execute( array( 'zone_id' => 999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'zone-not-found', $result->get_error_code() );
	}
}
