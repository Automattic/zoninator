<?php
declare( strict_types = 1 );

/**
 * Unit tests for the rules about making and changing a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Domain;

use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\Zone;
use PHPUnit\Framework\TestCase;

final class ZoneTest extends TestCase {

	private const DEFAULTS = array(
		'description' => '',
		'kicker'      => 'News',
	);

	public function test_a_new_zone_is_named_after_its_slug_unless_given_a_name(): void {
		$this->assertSame( 'homepage', Zone::create( 'homepage', '', array(), self::DEFAULTS )->get_name() );
		$this->assertSame( 'Home page', Zone::create( 'homepage', 'Home page', array(), self::DEFAULTS )->get_name() );
	}

	public function test_a_new_zone_has_every_default_detail_it_was_not_given(): void {
		$zone = Zone::create( 'homepage', '', array( 'description' => 'Top stories' ), self::DEFAULTS );

		$this->assertSame(
			array(
				'description' => 'Top stories',
				'kicker'      => 'News',
			),
			$zone->get_details()
		);
		$this->assertSame( 'Top stories', $zone->get_description() );
		$this->assertNull( $zone->get_id() );
	}

	public function test_a_zone_needs_a_slug(): void {
		$this->expectException( InvalidZoneException::class );

		Zone::create( '', 'Home page', array(), self::DEFAULTS );
	}

	public function test_an_empty_name_or_slug_leaves_the_current_one(): void {
		$zone = new Zone( 3, 'homepage', 'Home page', self::DEFAULTS );

		$changed = $zone->with_changes(
			array(
				'name' => '',
				'slug' => '',
			),
			self::DEFAULTS
		);

		$this->assertSame( 'Home page', $changed->get_name() );
		$this->assertSame( 'homepage', $changed->get_slug() );
		$this->assertSame( 3, $changed->get_id() );
	}

	public function test_a_change_gives_a_new_zone_and_leaves_the_old_one_alone(): void {
		$zone = new Zone( 3, 'homepage', 'Home page', self::DEFAULTS );

		$changed = $zone->with_changes( array( 'name' => 'Front page' ), self::DEFAULTS );

		$this->assertSame( 'Front page', $changed->get_name() );
		$this->assertSame( 'Home page', $zone->get_name() );
	}

	public function test_a_detail_that_is_not_sent_keeps_its_current_value(): void {
		$zone = new Zone(
			3,
			'homepage',
			'Home page',
			array(
				'description' => 'Top stories',
				'kicker'      => 'Sport',
			)
		);

		$changed = $zone->with_changes( array( 'details' => array( 'description' => 'Latest' ) ), self::DEFAULTS );

		$this->assertSame(
			array(
				'description' => 'Latest',
				'kicker'      => 'Sport',
			),
			$changed->get_details()
		);
	}

	public function test_a_stored_detail_the_site_no_longer_has_is_dropped_unless_sent(): void {
		$zone = new Zone( 3, 'homepage', 'Home page', self::DEFAULTS + array( 'retired' => 'old' ) );

		$this->assertArrayNotHasKey( 'retired', $zone->with_changes( array(), self::DEFAULTS )->get_details() );
		$this->assertSame( 'kept', $zone->with_changes( array( 'details' => array( 'retired' => 'kept' ) ), self::DEFAULTS )->get_details()['retired'] );
	}
}
