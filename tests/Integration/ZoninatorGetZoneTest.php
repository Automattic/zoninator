<?php
declare( strict_types = 1 );

/**
 * Integration tests for looking up a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class ZoninatorGetZoneTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function setUp(): void {
		parent::setUp();

		$zone          = \Zoninator()->insert_zone( 'get-zone', 'Get Zone' );
		$this->zone_id = $zone['term_id'];
	}

	public function test_get_zone_by_id() {
		$zone = \Zoninator()->get_zone( $this->zone_id );

		$this->assertInstanceOf( \WP_Term::class, $zone );
		$this->assertSame( 'get-zone', $zone->slug );
	}

	public function test_get_zone_by_slug() {
		$zone = \Zoninator()->get_zone( 'get-zone' );

		$this->assertInstanceOf( \WP_Term::class, $zone );
		$this->assertSame( $this->zone_id, $zone->term_id );
	}

	public function test_get_zone_returns_object_as_is() {
		$term = get_term( $this->zone_id );

		$this->assertSame( $term, \Zoninator()->get_zone( $term ) );
	}

	public function test_get_zone_fills_zone_details() {
		$zone = \Zoninator()->get_zone( $this->zone_id );

		$this->assertTrue( $zone->zoninator_parsed );
	}

	public function test_get_zone_returns_false_for_missing_zone() {
		$this->assertFalse( \Zoninator()->get_zone( 999999 ) );
		$this->assertFalse( \Zoninator()->get_zone( 'no-such-zone' ) );
	}

	public function test_get_zone_returns_false_for_invalid_type() {
		$this->assertFalse( \Zoninator()->get_zone( array( $this->zone_id ) ) );
		$this->assertFalse( \Zoninator()->get_zone( null ) );
	}

	public function test_get_zone_does_not_instantiate_objects_from_the_description() {
		global $wpdb;

		// Write straight to the table, as any path to the description could.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'description' => serialize( array( 'description' => new \ArrayObject() ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			array( 'term_id' => $this->zone_id )
		);
		clean_term_cache( $this->zone_id );

		$zone = \Zoninator()->get_zone( $this->zone_id );

		$this->assertInstanceOf( \__PHP_Incomplete_Class::class, $zone->description );
	}
}
