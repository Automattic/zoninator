<?php
/**
 * Integration tests for storing and reading zone descriptions.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use WP_REST_Request;
use WP_Term;

final class ZoninatorZoneDescriptionTest extends TestCase {

	/**
	 * Text that core's term filters change if it reaches them as HTML entities.
	 */
	private const TEXT = 'Café “Crème” — brûlée';

	public function test_insert_zone_keeps_non_ascii_text(): void {
		$zone = $this->insert( self::TEXT );

		$this->assertSame( self::TEXT, $zone->description );
	}

	public function test_rest_create_keeps_non_ascii_text(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/zoninator/v1/zones' );
		$request->set_param( 'slug', 'rest-description' );
		$request->set_param( 'name', 'Café' );
		$request->set_param( 'description', self::TEXT );
		$response = rest_do_request( $request );
		$zone     = self::zones()->get( 'rest-description' );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Café', $zone->name );
		$this->assertSame( self::TEXT, $zone->description );
		$this->assertSame( self::TEXT, $response->get_data()['description'] );
	}

	public function test_a_description_with_wrong_stored_lengths_is_read(): void {
		$zone_id = $this->damaged_zone();

		$this->assertSame( 'Café &amp; Co', self::zones()->get( $zone_id )->description );
	}

	public function test_saving_a_damaged_zone_stores_a_valid_description(): void {
		$zone_id = $this->damaged_zone();

		self::zones()->update( $zone_id, array( 'name' => 'Renamed' ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );
		$raw = get_term( $zone_id, Taxonomy::NAME )->description;

		$this->assertSame( array( 'description' => 'Café &amp; Co' ), unserialize( $raw, array( 'allowed_classes' => false ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * @param string $description Description.
	 * @return WP_Term
	 */
	private function insert( $description ) {
		$result = self::zones()->insert( 'description-' . wp_rand(), 'Zone', array( 'description' => $description ) );
		$this->assertNotWPError( $result );

		return self::zones()->get( (int) $result['term_id'] );
	}

	/**
	 * A zone saved by an earlier version: "Café" was stored as "Caf&eacute;" and
	 * counted, then core decoded the entity, so the stored length is 6 too long.
	 *
	 * @return int Zone ID.
	 */
	private function damaged_zone() {
		$result = wp_insert_term(
			'Damaged',
			Taxonomy::NAME,
			array( 'description' => 'a:1:{s:11:"description";s:20:"Café &amp; Co";}' )
		);
		$this->assertNotWPError( $result );

		return (int) $result['term_id'];
	}
}
