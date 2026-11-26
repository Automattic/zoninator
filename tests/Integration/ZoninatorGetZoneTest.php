<?php
declare( strict_types = 1 );

/**
 * Integration tests for looking up a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use ArrayObject;
use WP_Term;

class ZoninatorGetZoneTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function setUp(): void {
		parent::setUp();

		$this->zone_id = self::insert_zone( 'get-zone', 'Get Zone' );
	}

	public function test_get_zone_by_id() {
		$zone = self::zones()->get( $this->zone_id );

		$this->assertInstanceOf( WP_Term::class, $zone );
		$this->assertSame( 'get-zone', $zone->slug );
	}

	public function test_get_zone_by_slug() {
		$zone = self::zones()->get( 'get-zone' );

		$this->assertInstanceOf( WP_Term::class, $zone );
		$this->assertSame( $this->zone_id, $zone->term_id );
	}

	public function test_get_zone_returns_object_as_is() {
		$term = get_term( $this->zone_id );

		$this->assertSame( $term, self::zones()->get( $term ) );
	}

	public function test_get_zone_fills_zone_details() {
		$zone = self::zones()->get( $this->zone_id );

		$this->assertTrue( $zone->zoninator_parsed );
	}

	public function test_get_zone_returns_false_for_missing_zone() {
		$this->assertFalse( self::zones()->get( 999999 ) );
		$this->assertFalse( self::zones()->get( 'no-such-zone' ) );
	}

	public function test_get_zone_returns_false_for_invalid_type() {
		$this->assertFalse( self::zones()->get( array( $this->zone_id ) ) );
		$this->assertFalse( self::zones()->get( null ) );
	}

	public function test_get_zone_does_not_instantiate_objects_from_the_description() {
		global $wpdb;

		// Write straight to the table, as any path to the description could.
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'description' => serialize( array( 'description' => new ArrayObject() ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			array( 'term_id' => $this->zone_id )
		);
		clean_term_cache( $this->zone_id );

		$zone = self::zones()->get( $this->zone_id );

		$this->assertInstanceOf( \__PHP_Incomplete_Class::class, $zone->description );
	}
}
