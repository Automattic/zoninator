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

	/**
	 * How many zones insert() has made, to give each its own slug.
	 *
	 * @var int
	 */
	private $inserted = 0;

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

		$zone = zoninator_container()->get( ZoneManager::class )->create( 'api-zone', $text, array( 'description' => $text ) );
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
	 * WordPress takes control characters out of a description when it saves
	 * it, and carriage returns too by WordPress 7.3, though not in 7.1. Details counted
	 * before that are stored with lengths that are too long, and no longer
	 * unserialise.
	 *
	 * @dataProvider data_text_core_shortens
	 *
	 * @param string $text Description.
	 * @param string $kept The description WordPress keeps.
	 */
	public function test_text_core_shortens_is_stored_with_the_right_lengths( string $text, string $kept ): void {
		wp_set_current_user( 0 );

		$zone_id = self::insert_zone( 'shortened', 'Zone', array( 'description' => $text ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );
		$stored = unserialize( get_term( $zone_id, Taxonomy::NAME )->description, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize

		// Unserialising at all is the point. A carriage return is kept or dropped, by WordPress version.
		$this->assertIsArray( $stored );
		$this->assertSame( array( 'description' => $kept ), str_replace( "\r", '', $stored ) );
	}

	/**
	 * @return Generator<string, array{string, string}>
	 */
	public static function data_text_core_shortens(): Generator {
		yield 'a control character' => array( "Top\x01 stories", 'Top stories' );
		yield 'a null byte' => array( "Top\x00 stories", 'Top stories' );
		yield 'line breaks from a form' => array( "One\r\nTwo", "One\nTwo" );
		yield 'a control character after what looks like the end' => array( "Top\";}\x01", 'Top";}' );
	}

	/**
	 * A description is text, whatever it holds. It can't add a detail, or
	 * change another one.
	 */
	public function test_a_description_cannot_change_the_zones_other_details(): void {
		wp_set_current_user( 0 );
		add_filter( 'zoninator_zone_detail_defaults', static fn( $defaults ) => $defaults + array( 'note' => '' ) );
		$text = "Top\";s:7:\"term_id\";i:999;}\x01";

		$zone_id = self::insert_zone( 'only-text', 'Only text', array( 'description' => $text ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );
		$zone = self::zones()->get( $zone_id );

		$this->assertSame( $zone_id, $zone->term_id );
		$this->assertSame( 'Top";s:7:"term_id";i:999;}', $zone->description );
		$this->assertSame( '', $zone->note );
	}

	/**
	 * A zone's details become properties of its term. One stored under the
	 * name of a property the term already has, or a name PHP refuses, must
	 * not replace the term's own, or stop the zone being read.
	 */
	public function test_a_stored_detail_cannot_replace_the_terms_own_fields(): void {
		global $wpdb;
		$zone_id = self::insert_zone( 'own-fields', 'Own fields' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Past the term filters, as an earlier version could store it.
		$wpdb->update( $wpdb->term_taxonomy, array( 'description' => 'a:5:{s:11:"description";s:3:"Top";s:7:"term_id";i:999;s:4:"name";s:5:"Other";s:0:"";i:1;s:4:"note";s:4:"Kept";}' ), array( 'term_id' => $zone_id ) );
		clean_term_cache( $zone_id, Taxonomy::NAME );

		$zone = self::zones()->get( $zone_id );

		$this->assertSame( $zone_id, $zone->term_id );
		$this->assertSame( 'Own fields', $zone->name );
		$this->assertSame( 'Top', $zone->description );
		$this->assertSame( 'Kept', $zone->note );
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
		return self::zones()->get( self::insert_zone( 'description-' . ++$this->inserted, 'Zone', array( 'description' => $description ) ) );
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
