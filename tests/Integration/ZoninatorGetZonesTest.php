<?php
declare( strict_types = 1 );

/**
 * Integration tests for Zoninator::get_zones().
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

/**
 * @group zones
 */
class ZoninatorGetZonesTest extends TestCase {

	public function test_get_zones_fills_zone_details() {
		\Zoninator()->insert_zone( 'zone-details', 'Zone Details', array( 'description' => 'Hello zone' ) );

		$zones = \Zoninator()->get_zones();

		$this->assertIsArray( $zones );
		$this->assertCount( 1, $zones );
		$this->assertTrue( $zones[0]->zoninator_parsed );
		$this->assertSame( 'Hello zone', $zones[0]->description );
	}

	public function test_get_zones_with_fields_ids_returns_ids() {
		$result  = \Zoninator()->insert_zone( 'zone-ids', 'Zone Ids' );
		$zone_id = (int) $result['term_id'];

		$zones = \Zoninator()->get_zones( array( 'fields' => 'ids' ) );

		$this->assertSame( array( $zone_id ), array_map( 'intval', $zones ) );
	}

	public function test_get_zones_ignores_a_taxonomy_arg() {
		$result  = \Zoninator()->insert_zone( 'zone-only', 'Zone Only' );
		$zone_id = (int) $result['term_id'];
		self::factory()->category->create( array( 'name' => 'Not a zone' ) );

		$zones = \Zoninator()->get_zones(
			array(
				'taxonomy' => 'category',
				'fields'   => 'ids',
			)
		);

		$this->assertSame( array( $zone_id ), array_map( 'intval', $zones ) );
	}
}
