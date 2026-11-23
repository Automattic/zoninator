<?php
/**
 * Tests for the class constants, getters and the old public properties.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Settings;

/**
 * Tests for the class constants, getters and the old public properties.
 */
class ZoninatorPropertiesTest extends TestCase {

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

	public function test_old_property_still_readable_with_notice(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$zone_taxonomy' );

		$this->assertSame( \Zoninator::ZONE_TAXONOMY, Zoninator()->zone_taxonomy );
	}

	public function test_old_private_property_still_readable_with_notice(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$posts_per_page' );

		$this->assertSame( Zoninator()->get_posts_per_page(), Zoninator()->posts_per_page );
	}

	/**
	 * Stored zones, post meta, nonces and admin links depend on these values.
	 *
	 * @dataProvider data_old_fixed_properties
	 *
	 * @param string $property Old property name.
	 * @param string $value    Its value, which must not change.
	 * @param string $constant Constant that now holds it.
	 */
	public function test_old_fixed_properties_keep_their_values( string $property, string $value, string $constant ): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$' . $property );

		$this->assertSame( $value, Zoninator()->$property );
		$this->assertSame( $value, constant( $constant ) );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public function data_old_fixed_properties(): array {
		return array(
			'taxonomy'          => array( 'zone_taxonomy', 'zoninator_zones', '\\Zoninator::ZONE_TAXONOMY' ),
			'meta prefix'       => array( 'zone_meta_prefix', '_zoninator_order_', '\\Zoninator::ZONE_META_PREFIX' ),
			'admin page'        => array( 'key', 'zoninator', '\\Automattic\\Zoninator\\Admin\\Screen::PAGE_SLUG' ),
			'AJAX nonce action' => array( 'zone_ajax_nonce_action', 'ajax-action', '\\Automattic\\Zoninator\\Admin\\Ajax::NONCE_ACTION' ),
		);
	}

	public function test_nonce_keys_keep_their_prefix(): void {
		$this->assertSame( 'zone-nonce-ajax-action', Zoninator()->_get_nonce_key( 'ajax-action' ) );
	}

	public function test_isset_on_old_property(): void {
		$zoninator = Zoninator();

		$this->assertTrue( isset( $zoninator->zone_taxonomy ) );
		$this->assertFalse( isset( $zoninator->not_a_property ) );
	}

	public function test_write_to_old_private_property_still_takes_effect_with_notice(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$posts_per_page' );

		$zoninator = Zoninator();
		$before    = $zoninator->get_posts_per_page();

		$zoninator->posts_per_page = $before + 10;
		$after                     = $zoninator->get_posts_per_page();
		$zoninator->posts_per_page = $before;

		$this->assertSame( $before + 10, $after );
	}

	public function test_write_to_old_post_types_still_takes_effect_with_notice(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$post_types' );

		$zoninator             = Zoninator();
		$zoninator->post_types = array( 'post', 'page' );
		$after                 = $zoninator->get_supported_post_types();
		$zoninator->post_types = null;

		$this->assertSame( array( 'post', 'page' ), $after );
		$this->assertSame( array( 'post' ), $zoninator->get_supported_post_types() );
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

	public function test_write_to_old_detail_defaults_still_adds_a_zone_detail(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$zone_detail_defaults' );

		$zoninator = Zoninator();
		$before    = $zoninator->get_zone_detail_defaults();

		$zoninator->zone_detail_defaults = $before + array( 'kicker' => 'News' );
		$after                           = $zoninator->get_zone_detail_defaults();
		$zoninator->zone_detail_defaults = $before;

		$this->assertSame( array( 'description' => '', 'kicker' => 'News' ), $after );
	}

	public function test_write_to_old_constant_property_is_ignored_with_notice(): void {
		$this->setExpectedIncorrectUsage( 'Zoninator::$zone_taxonomy' );

		$zoninator = Zoninator();

		$zoninator->zone_taxonomy = 'something_else';

		// Reading it again still goes through __get(), so no dynamic property was created.
		$this->assertSame( \Zoninator::ZONE_TAXONOMY, $zoninator->zone_taxonomy );
	}
}
