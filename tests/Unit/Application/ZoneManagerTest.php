<?php
declare( strict_types = 1 );

/**
 * Unit tests for creating and changing zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneManagerTest extends TestCase {

	/**
	 * @var InMemoryZoneRepository
	 */
	private $zones;

	/**
	 * @var ZoneManager
	 */
	private $manager;

	protected function setUp(): void {
		$this->zones   = new InMemoryZoneRepository();
		$this->manager = new ZoneManager( $this->zones, new FixedSettings() );
	}

	public function test_create_saves_the_zone_with_the_default_details(): void {
		$zone = $this->manager->create( 'homepage', 'Home page', array( 'description' => 'Top stories' ) );

		$this->assertSame( 1, $zone->get_id() );
		$this->assertSame( 'News', $zone->get_details()['kicker'] );
		$this->assertEquals( $zone, $this->zones->find( 1 ) );
	}

	public function test_create_reports_a_zone_the_store_refuses(): void {
		$this->manager->create( 'homepage' );

		try {
			$this->manager->create( 'homepage' );
			$this->fail( 'Expected the second zone to be refused.' );
		} catch ( ZoneNotSavedException $exception ) {
			$this->assertSame( 'term_exists', $exception->get_error_code() );
		}
	}

	public function test_update_saves_the_changes_and_keeps_the_rest(): void {
		$this->manager->create( 'homepage', 'Home page', array( 'kicker' => 'Sport' ) );

		$zone = $this->manager->update( 1, array( 'name' => 'Front page' ) );

		$this->assertSame( 'Front page', $zone->get_name() );
		$this->assertSame( 'homepage', $zone->get_slug() );
		$this->assertSame( 'Sport', $this->zones->find( 1 )->get_details()['kicker'] );
	}

	public function test_update_refuses_a_zone_that_does_not_exist(): void {
		$this->expectException( ZoneNotFoundException::class );

		$this->manager->update( 9, array( 'name' => 'Front page' ) );
	}
}
