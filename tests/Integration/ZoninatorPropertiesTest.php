<?php
/**
 * Tests for the class constants, getters and the old public properties.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

/**
 * Tests for the class constants, getters and the old public properties.
 */
class ZoninatorPropertiesTest extends TestCase {

	public function test_getters_return_filtered_values(): void {
		$zoninator = Zoninator();

		$this->assertSame( 30, $zoninator->get_zone_lock_period() );
		$this->assertSame( 600, $zoninator->get_zone_max_lock_period() );
		$this->assertSame( 10, $zoninator->get_posts_per_page() );
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

	public function test_isset_on_old_property(): void {
		$zoninator = Zoninator();

		$this->assertTrue( isset( $zoninator->zone_taxonomy ) );
		$this->assertFalse( isset( $zoninator->not_a_property ) );
	}
}
