<?php
/**
 * Tests for the class constants and the settings getters.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Request;
use Automattic\Zoninator\Settings;

/**
 * Tests for the class constants and the settings getters.
 */
class ZoninatorSettingsTest extends TestCase {

	public function test_getters_return_the_defaults(): void {
		$zoninator = Zoninator();

		$this->assertSame( 30, $zoninator->get_zone_lock_period() );
		$this->assertSame( 600, $zoninator->get_zone_max_lock_period() );
		$this->assertSame( 10, $zoninator->get_posts_per_page() );
		$this->assertSame( array( 'description' => '' ), $zoninator->get_zone_detail_defaults() );
	}

	public function test_settings_filters_change_the_values_on_init(): void {
		add_filter( 'zoninator_zone_lock_period', static fn() => 45 );
		add_filter( 'zoninator_zone_max_lock_period', static fn() => 900 );
		add_filter( 'zoninator_posts_per_page', static fn() => 25 );

		// A fresh instance, so the plugin's own settings are left alone.
		$settings = new Settings();
		$settings->init();

		$this->assertSame( 45, $settings->get_lock_period() );
		$this->assertSame( 900, $settings->get_max_lock_period() );
		$this->assertSame( 25, $settings->get_posts_per_page() );
	}

	public function test_taxonomy_is_registered_with_constant(): void {
		$this->assertTrue( taxonomy_exists( \Zoninator::ZONE_TAXONOMY ) );
	}

	/**
	 * Stored zones, post meta, nonces and admin links depend on these values.
	 *
	 * @dataProvider data_fixed_values
	 *
	 * @param string $constant Constant.
	 * @param string $value    Its value, which must not change.
	 */
	public function test_fixed_values_do_not_change( string $constant, string $value ): void {
		$this->assertSame( $value, constant( $constant ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function data_fixed_values(): array {
		return array(
			'taxonomy'     => array( '\\Zoninator::ZONE_TAXONOMY', 'zoninator_zones' ),
			'meta prefix'  => array( '\\Zoninator::ZONE_META_PREFIX', '_zoninator_order_' ),
			'admin page'   => array( '\\Automattic\\Zoninator\\Admin\\Screen::PAGE_SLUG', 'zoninator' ),
			'nonce prefix' => array( '\\Automattic\\Zoninator\\Request::NONCE_PREFIX', 'zone-nonce' ),
		);
	}

	public function test_nonce_keys_keep_their_prefix(): void {
		$this->assertSame( 'zone-nonce-update', Request::get_nonce_key( 'update' ) );
	}

	public function test_detail_defaults_filter_adds_a_zone_detail(): void {
		$add_kicker = static function ( $defaults ) {
			return $defaults + array( 'kicker' => 'News' );
		};
		add_filter( 'zoninator_zone_detail_defaults', $add_kicker );

		$plain   = Zoninator()->insert_zone( 'plain-zone' );
		$kicked  = Zoninator()->insert_zone( 'kicked-zone', 'Kicked', array( 'kicker' => 'Sport' ) );
		$kicked  = (int) $kicked['term_id'];
		Zoninator()->update_zone( $kicked, array( 'name' => 'Renamed' ) );

		$this->assertSame( 'News', Zoninator()->get_zone( (int) $plain['term_id'] )->kicker );
		$this->assertSame( 'Sport', Zoninator()->get_zone( $kicked )->kicker );

		remove_filter( 'zoninator_zone_detail_defaults', $add_kicker );
	}

	/**
	 * Sites that relied on the old public properties now get PHP's usual
	 * warning instead of a value, so nothing reads them silently.
	 */
	public function test_old_properties_are_gone(): void {
		$this->assertFalse( isset( Zoninator()->zone_taxonomy ) );
		$this->assertFalse( method_exists( Zoninator(), '__get' ) );
		$this->assertFalse( method_exists( Zoninator(), '__set' ) );
	}
}
