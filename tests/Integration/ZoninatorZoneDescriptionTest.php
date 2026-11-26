<?php
/**
 * Integration tests for storing and reading zone descriptions.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Generator;
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

		self::update_zone( $zone_id, array( 'name' => 'Renamed' ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );
		$raw = get_term( $zone_id, Taxonomy::NAME )->description;

		$this->assertSame( array( 'description' => 'Café &amp; Co' ), unserialize( $raw, array( 'allowed_classes' => false ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * WordPress filters a term's description when the user may not post
	 * unfiltered HTML, and code with no user, such as a scheduled job, may
	 * not. Each of these used to leave the stored details unreadable, wrongly
	 * counted or holding live HTML when they came through the PHP API.
	 *
	 * @dataProvider data_text_core_would_change
	 *
	 * @param string $text Name and description.
	 */
	public function test_the_php_api_stores_text_core_would_change( string $text ): void {
		wp_set_current_user( 0 );

		$zone = zoninator_container()->get( ZoneManager::class )->create( 'api-' . wp_rand(), $text, array( 'description' => $text ) );
		clean_term_cache( (int) $zone->get_id(), Taxonomy::NAME );
		$term   = get_term( (int) $zone->get_id(), Taxonomy::NAME );
		$stored = unserialize( $term->description, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		// The Zone holds the text as it was given.
		$this->assertSame( $text, $zone->get_name() );
		$this->assertSame( $text, $zone->get_description() );
		// It's stored with &, < and > encoded, which leaves the filters nothing to change, so it unserialises as it is.
		$this->assertSame( array( 'description' => htmlspecialchars( $text, ENT_NOQUOTES ) ), $stored );
		$this->assertSame( htmlspecialchars( $text, ENT_NOQUOTES ), $term->name );
		// The template tags give themes the stored text, with no tag left in it.
		$this->assertSame( htmlspecialchars( $text, ENT_NOQUOTES ), self::zones()->get( (int) $zone->get_id() )->description );
	}

	/**
	 * @return Generator<string, array{string}>
	 */
	public static function data_text_core_would_change(): Generator {
		yield 'an ampersand' => array( 'Tom & Jerry' );
		yield 'a quote and semicolon' => array( 'He said "hi"; she left' );
		yield 'an apostrophe' => array( "The editor's picks" );
		yield 'tags core would strip' => array( 'Top <script>alert(1)</script> stories' );
		yield 'tags core would keep' => array( 'Top <b>stories</b>' );
		yield 'what looks like length' => array( 'a";s:1:"b' );
		yield 'text that names entities' => array( 'Write &amp; or &quot; or &eacute;' );
	}

	/**
	 * A change that doesn't touch the description must store it exactly as it was.
	 */
	public function test_changing_a_zone_does_not_encode_its_text_again(): void {
		$manager = zoninator_container()->get( ZoneManager::class );
		$zone    = $manager->create( 'twice', 'Tom & Jerry', array( 'description' => 'Fish & "chips"' ) );
		$before  = get_term( (int) $zone->get_id(), Taxonomy::NAME )->description;

		$changed = $manager->update( (int) $zone->get_id(), array( 'slug' => 'twice-over' ) );
		clean_term_cache( (int) $zone->get_id(), Taxonomy::NAME );
		$term = get_term( (int) $zone->get_id(), Taxonomy::NAME );

		$this->assertSame( $before, $term->description );
		$this->assertSame( 'Tom &amp; Jerry', $term->name );
		$this->assertSame( 'Tom & Jerry', $changed->get_name() );
		$this->assertSame( 'Fish & "chips"', $changed->get_description() );
	}

	/**
	 * A zone saved before 1.0.0 can hold entities Zoninator no longer writes.
	 * WordPress decodes them the next time the zone is saved, so they must be
	 * decoded first, or the stored lengths are wrong again.
	 */
	public function test_entities_stored_by_an_earlier_version_survive_a_change(): void {
		global $wpdb;
		$zone_id = self::insert_zone( 'legacy' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Past the term filters, as an earlier WordPress stored it.
		$wpdb->update( $wpdb->term_taxonomy, array( 'description' => 'a:1:{s:11:"description";s:32:"Caf&eacute; &quot;A&quot;; &amp;";}' ), array( 'term_id' => $zone_id ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );

		self::update_zone( $zone_id, array( 'name' => 'Renamed' ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );
		$raw = get_term( $zone_id, Taxonomy::NAME )->description;

		$this->assertSame( array( 'description' => 'Café "A"; &amp;' ), unserialize( $raw, array( 'allowed_classes' => false ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
	}

	/**
	 * Every way in must store the same thing, as the storage now does the encoding.
	 */
	public function test_the_rest_api_and_the_php_api_store_the_same_text(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$text = 'Tom & "Jerry" <b>';

		$request = new WP_REST_Request( 'POST', '/zoninator/v1/zones' );
		$request->set_param( 'slug', 'same-rest' );
		$request->set_param( 'name', $text );
		$request->set_param( 'description', $text );
		rest_do_request( $request );
		zoninator_container()->get( ZoneManager::class )->create( 'same-php', $text, array( 'description' => $text ) );

		$rest = self::zones()->get( 'same-rest' );
		$php  = self::zones()->get( 'same-php' );

		$this->assertSame( htmlspecialchars( $text, ENT_NOQUOTES ), $rest->description );
		$this->assertSame( $rest->description, $php->description );
		$this->assertSame( $rest->name, $php->name );
	}

	/**
	 * @param string $description Description.
	 * @return WP_Term
	 */
	private function insert( $description ) {
		return self::zones()->get( self::insert_zone( 'description-' . wp_rand(), 'Zone', array( 'description' => $description ) ) );
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
