<?php
declare( strict_types = 1 );

/**
 * What every store of zone locks must do.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests;

use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Domain\ZoneLock;

/**
 * The tests every LockStoreInterface must pass: the stand-in the unit tests
 * use, and the real one. They keep the two from drifting apart, so the
 * ZoneLocker unit tests say something about the plugin.
 */
trait LockStoreContract {

	/**
	 * @return LockStoreInterface The store to test.
	 */
	abstract protected function store(): LockStoreInterface;

	public function test_a_zone_starts_with_no_lock(): void {
		$this->assertNull( $this->store()->find( 7 ) );
	}

	public function test_a_saved_lock_is_found_with_its_holder(): void {
		$this->store()->save( 7, new ZoneLock( 11 ), 30 );

		$this->assertSame( 11, $this->store()->find( 7 )->get_holder_id() );
	}

	public function test_saving_a_lock_again_replaces_the_holder(): void {
		$this->store()->save( 7, new ZoneLock( 11 ), 30 );

		$this->store()->save( 7, new ZoneLock( 12 ), 30 );

		$this->assertSame( 12, $this->store()->find( 7 )->get_holder_id() );
	}

	public function test_a_deleted_lock_is_gone(): void {
		$this->store()->save( 7, new ZoneLock( 11 ), 30 );

		$this->store()->delete( 7 );

		$this->assertNull( $this->store()->find( 7 ) );
	}

	public function test_each_zone_has_its_own_lock(): void {
		$this->store()->save( 7, new ZoneLock( 11 ), 30 );
		$this->store()->save( 8, new ZoneLock( 12 ), 30 );

		$this->store()->delete( 8 );

		$this->assertSame( 11, $this->store()->find( 7 )->get_holder_id() );
		$this->assertNull( $this->store()->find( 8 ) );
	}
}
