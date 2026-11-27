<?php
declare( strict_types = 1 );

/**
 * Unit tests for creating and changing zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryLockStore;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZonePostsRepository;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneManagerTest extends TestCase {

	/**
	 * @var InMemoryZoneRepository
	 */
	private $zones;

	/**
	 * @var InMemoryZonePostsRepository
	 */
	private $zone_posts;

	/**
	 * @var ZoneLocker
	 */
	private $locker;

	/**
	 * @var ZoneManager
	 */
	private $manager;

	protected function setUp(): void {
		$settings         = new FixedSettings();
		$this->zones      = new InMemoryZoneRepository();
		$this->zone_posts = new InMemoryZonePostsRepository();
		$this->locker     = new ZoneLocker( new InMemoryLockStore(), $settings, $this->zones );
		$this->manager    = new ZoneManager( $this->zones, $settings, $this->zone_posts, $this->locker );
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

	public function test_remove_deletes_the_zone_with_its_posts_and_its_lock(): void {
		$zone_id = (int) $this->manager->create( 'homepage', 'Home page' )->get_id();
		$this->zone_posts->replace( $zone_id, array( 5, 6 ) );
		$this->locker->lock( $zone_id, 11 );

		$this->manager->remove( $zone_id );

		$this->assertNull( $this->zones->find( $zone_id ) );
		$this->assertArrayNotHasKey( $zone_id, $this->zone_posts->post_ids );
		$this->assertNull( $this->locker->get_blocker_id( $zone_id, 12 ) );
	}

	public function test_remove_refuses_a_zone_that_does_not_exist(): void {
		$this->zone_posts->replace( 9, array( 5 ) );

		try {
			$this->manager->remove( 9 );
			$this->fail( 'Expected the missing zone to be refused.' );
		} catch ( ZoneNotFoundException $exception ) {
			$this->assertSame( array( 5 ), $this->zone_posts->post_ids[9] );
		}
	}
}
