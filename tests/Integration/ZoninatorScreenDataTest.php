<?php
declare( strict_types = 1 );

/**
 * Integration tests for what the Zones admin screen gathers to show.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenData;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenDataLoader;
use WP_Term;

final class ZoninatorScreenDataTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function set_up(): void {
		parent::set_up();

		$this->zone_id = self::insert_zone( 'data-zone', 'Data zone' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	public function test_an_open_zone_comes_with_its_posts_in_order(): void {
		$post_ids = self::factory()->post->create_many( 2 );
		self::add_zone_posts( $this->zone_id, array( $post_ids[1], $post_ids[0] ) );

		$data = $this->load( $this->zone_id );

		$this->assertSame( $this->zone_id, $data->get_zone_id() );
		$this->assertSame( 'Data zone', $data->active_zone->name );
		$this->assertSame( array( $post_ids[1], $post_ids[0] ), wp_list_pluck( $data->zone_posts, 'ID' ) );
		$this->assertContains( $this->zone_id, wp_list_pluck( $data->zones, 'term_id' ) );
	}

	public function test_with_no_zone_asked_for_a_user_who_can_add_zones_gets_none_open(): void {
		$data = $this->load( 0 );

		$this->assertNull( $data->active_zone );
		$this->assertSame( 0, $data->get_zone_id() );
		$this->assertSame( array(), $data->zone_posts );
		$this->assertTrue( $data->can_add_zones );
	}

	/**
	 * Someone who can't add a zone has nothing to do without one open.
	 */
	public function test_with_no_zone_asked_for_a_user_who_cannot_add_zones_gets_the_first_open(): void {
		add_filter( 'zoninator_add_zone_cap', static fn() => 'manage_options' );

		$data = $this->load( 0 );

		$this->assertFalse( $data->can_add_zones );
		$this->assertSame( (int) $data->zones[0]->term_id, $data->get_zone_id() );
	}

	public function test_a_zone_that_does_not_exist_is_not_opened(): void {
		$data = $this->load( 999999 );

		$this->assertNull( $data->active_zone );
		$this->assertSame( 999999, $data->active_zone_id );
		$this->assertSame( 0, $data->locked_by );
	}

	public function test_it_says_who_else_is_editing_the_zone_but_not_when_it_is_the_user(): void {
		$other_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		self::locker()->lock( $this->zone_id, get_current_user_id() );
		$mine = $this->load( $this->zone_id );
		self::locker()->unlock( $this->zone_id );
		self::locker()->lock( $this->zone_id, $other_id );
		$theirs = $this->load( $this->zone_id );

		$this->assertSame( 0, $mine->locked_by );
		$this->assertSame( $other_id, $theirs->locked_by );
	}

	/**
	 * The filter's name says "current user", but a zone can be changed for
	 * any user, so a callback needs to be told which one.
	 */
	public function test_the_edit_zone_filter_is_told_which_user_is_asked_about(): void {
		$current_id = get_current_user_id();
		$other_id   = self::factory()->user->create( array( 'role' => 'editor' ) );
		$seen       = array();
		$spy        = static function ( $has_cap, $zone_id, $user_id ) use ( &$seen ) {
			$seen[] = array( $zone_id, $user_id );

			return $has_cap;
		};
		add_filter( 'zoninator_current_user_can_edit_zone', $spy, 10, 3 );

		$this->load( $this->zone_id );
		zoninator_container()->get( GuardedZoneManager::class )->update( $other_id, $this->zone_id, array( 'name' => 'Renamed' ) );

		remove_filter( 'zoninator_current_user_can_edit_zone', $spy, 10 );
		$this->assertSame( array( array( $this->zone_id, $current_id ), array( $this->zone_id, $other_id ) ), $seen );
	}

	public function test_it_says_what_the_user_may_do(): void {
		add_filter( 'zoninator_current_user_can_edit_zone', '__return_false' );

		$editor = $this->load( $this->zone_id );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$author = $this->load( $this->zone_id );

		$this->assertTrue( $editor->can_manage_zones );
		$this->assertFalse( $editor->can_edit_zone );
		$this->assertFalse( $author->can_manage_zones );
		$this->assertFalse( $author->can_add_zones );
	}

	/**
	 * A site's args can make get_terms() give IDs in place of terms. The list must still be zones.
	 */
	public function test_the_list_holds_zones_even_when_a_site_asks_for_ids(): void {
		add_filter( 'zoninator_admin_page_get_zones_args', static fn() => array( 'fields' => 'ids' ) );

		$data = $this->load( 0 );

		$this->assertContainsOnlyInstancesOf( WP_Term::class, $data->zones );
		$this->assertContains( 'Data zone', wp_list_pluck( $data->zones, 'name' ) );
	}

	private function load( int $zone_id ): ScreenData {
		return zoninator_container()->get( ScreenDataLoader::class )->load( $zone_id );
	}
}
