<?php
/**
 * Tests for the class constants and the settings getters.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Admin\Assets;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenLocation;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;

/**
 * Tests for the class constants and the settings getters.
 */
class ZoninatorSettingsTest extends TestCase {

	public function test_getters_return_the_defaults(): void {
		$this->assertSame( 30, self::settings()->get_lock_period() );
		$this->assertSame( 600, self::settings()->get_max_lock_period() );
		$this->assertSame( 10, self::settings()->get_posts_per_page() );
		$this->assertSame( array( 'description' => '' ), self::settings()->get_detail_defaults() );
		$this->assertSame( 100, self::settings()->get_post_limit( 1 ) );
	}

	/**
	 * The getters apply their filters when called, so a filter added after init still counts.
	 */
	public function test_settings_filters_change_the_values_when_read(): void {
		$this->assertTrue( did_action( 'init' ) > 0 );
		add_filter( 'zoninator_zone_lock_period', static fn() => 45 );
		add_filter( 'zoninator_zone_max_lock_period', static fn() => 900 );
		add_filter( 'zoninator_posts_per_page', static fn() => 25 );

		$settings = new Settings();

		$this->assertSame( 45, $settings->get_lock_period() );
		$this->assertSame( 900, $settings->get_max_lock_period() );
		$this->assertSame( 25, $settings->get_posts_per_page() );
	}

	/**
	 * The screen's script takes and keeps the lock, and does neither when the
	 * longest lock period it's given is -1.
	 */
	public function test_zoninator_disable_locks_gives_the_screen_no_lock_period(): void {
		add_filter( 'zoninator_zone_max_lock_period', 'zoninator_disable_locks' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$screen = get_current_screen();
		set_current_screen( 'toplevel_page_zoninator' );

		zoninator_container()->get( Assets::class )->enqueue_scripts();

		$GLOBALS['current_screen'] = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$data = (string) wp_scripts()->get_data( 'zoninator-js', 'data' );
		$this->assertSame( 1, preg_match( '/var zoninatorOptions = (\{.*\});/s', $data, $matches ) );
		$this->assertSame( '-1', json_decode( $matches[1], true )['zoneLockPeriodMax'] );
	}

	public function test_taxonomy_is_registered_with_constant(): void {
		$this->assertTrue( taxonomy_exists( Taxonomy::NAME ) );
	}

	/**
	 * Stored zones, post meta, nonces and admin links depend on these values.
	 */
	public function test_fixed_values_do_not_change(): void {
		$this->assertSame( 'zoninator_zones', Taxonomy::NAME );
		$this->assertSame( '_zoninator_order_', Zones::META_PREFIX );
		$this->assertSame( 'zoninator', ScreenLocation::SLUG );
		$this->assertSame( 'zone-nonce', ScreenLocation::NONCE_PREFIX );
	}

	public function test_nonce_keys_keep_their_prefix(): void {
		$this->assertSame( 'zone-nonce-update', zoninator_container()->get( ScreenLocation::class )->get_nonce_action( 'update' ) );
	}

	public function test_detail_defaults_filter_adds_a_zone_detail(): void {
		$add_kicker = static function ( $defaults ) {
			return $defaults + array( 'kicker' => 'News' );
		};
		add_filter( 'zoninator_zone_detail_defaults', $add_kicker );

		$plain_id  = self::insert_zone( 'plain-zone' );
		$kicked_id = self::insert_zone( 'kicked-zone', 'Kicked', array( 'kicker' => 'Sport' ) );
		self::update_zone( $kicked_id, array( 'name' => 'Renamed' ) );

		$this->assertSame( 'News', self::zones()->get( $plain_id )->kicker );
		$this->assertSame( 'Sport', self::zones()->get( $kicked_id )->kicker );

		remove_filter( 'zoninator_zone_detail_defaults', $add_kicker );
	}

	/**
	 * 1.0.0 removed the Zoninator class, its Zoninator() function and the z_
	 * template tags, so nothing must bring them back by accident.
	 */
	public function test_the_old_entry_points_are_gone(): void {
		$this->assertFalse( class_exists( 'Zoninator' ) );
		$this->assertFalse( function_exists( 'z_get_zoninator' ) );
		$this->assertFalse( function_exists( 'z_get_posts_in_zone' ) );
		$this->assertArrayNotHasKey( 'zoninator', $GLOBALS );
	}
}
