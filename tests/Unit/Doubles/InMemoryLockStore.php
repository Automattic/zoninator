<?php
declare( strict_types = 1 );

/**
 * A lock store for unit tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Domain\ZoneLock;

/**
 * Keeps locks in an array, and records how long each was saved for.
 */
final class InMemoryLockStore implements LockStoreInterface {

	/**
	 * Locks, by zone ID.
	 *
	 * @var array<int, ZoneLock>
	 */
	private $locks = array();

	/**
	 * How long each lock was last saved for, by zone ID.
	 *
	 * @var array<int, int>
	 */
	public $seconds = array();

	public function find( int $zone_id ): ?ZoneLock {
		return $this->locks[ $zone_id ] ?? null;
	}

	public function save( int $zone_id, ZoneLock $lock, int $seconds ): void {
		$this->locks[ $zone_id ]   = $lock;
		$this->seconds[ $zone_id ] = $seconds;
	}

	public function delete( int $zone_id ): void {
		unset( $this->locks[ $zone_id ] );
	}
}
