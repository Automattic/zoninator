<?php
declare( strict_types = 1 );

/**
 * Unit tests for the rules about who may lock a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryLockStore;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneLockerTest extends TestCase {

	private const ZONE   = 1;
	private const EDITOR = 11;
	private const OTHER  = 12;

	/**
	 * @var InMemoryLockStore
	 */
	private $store;

	/**
	 * @var ZoneLocker
	 */
	private $locker;

	protected function setUp(): void {
		$zones = new InMemoryZoneRepository();
		$zones->insert( new Zone( null, 'homepage', 'Home page', array() ) );

		$this->store  = new InMemoryLockStore();
		$this->locker = new ZoneLocker( $this->store, new FixedSettings(), $zones );
	}

	public function test_only_a_zone_can_be_locked(): void {
		try {
			$this->locker->lock( 9, self::EDITOR );
			$this->fail( 'Expected the missing zone to be refused.' );
		} catch ( ZoneNotFoundException $exception ) {
			$this->assertArrayNotHasKey( 9, $this->store->seconds );
		}
	}

	public function test_an_unlocked_zone_keeps_no_one_out(): void {
		$this->assertNull( $this->locker->get_blocker_id( self::ZONE, self::EDITOR ) );
	}

	public function test_a_lock_keeps_everyone_out_but_its_holder(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );

		$this->assertNull( $this->locker->get_blocker_id( self::ZONE, self::EDITOR ) );
		$this->assertSame( self::EDITOR, $this->locker->get_blocker_id( self::ZONE, self::OTHER ) );
	}

	public function test_a_lock_on_one_zone_leaves_the_others_open(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );

		$this->assertNull( $this->locker->get_blocker_id( self::ZONE + 1, self::OTHER ) );
	}

	public function test_a_lock_lasts_one_second_longer_than_the_lock_period(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );

		$this->assertSame( 31, $this->store->seconds[ self::ZONE ] );
	}

	public function test_the_holder_can_renew_their_lock(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );
		$this->store->seconds = array();

		$this->locker->lock( self::ZONE, self::EDITOR );

		$this->assertSame( 31, $this->store->seconds[ self::ZONE ] );
	}

	public function test_no_one_can_take_a_lock_someone_else_holds(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );

		try {
			$this->locker->lock( self::ZONE, self::OTHER );
			$this->fail( 'Expected the zone to stay locked.' );
		} catch ( ZoneLockedException $exception ) {
			$this->assertSame( self::EDITOR, $exception->get_holder_id() );
		}

		$this->assertSame( self::EDITOR, $this->locker->get_blocker_id( self::ZONE, self::OTHER ) );
	}

	public function test_unlocking_lets_someone_else_in(): void {
		$this->locker->lock( self::ZONE, self::EDITOR );

		$this->locker->unlock( self::ZONE );
		$this->locker->lock( self::ZONE, self::OTHER );

		$this->assertSame( self::OTHER, $this->locker->get_blocker_id( self::ZONE, self::EDITOR ) );
	}
}
