<?php
/**
 * Locks zones for the people editing them.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\LockSettingsInterface;
use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Domain\ZoneLock;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;

/**
 * Takes, checks and releases the lock that stops two people editing a zone
 * at once.
 *
 * @since 1.0.0
 */
final class ZoneLocker {

	/**
	 * Constructor.
	 *
	 * @param LockStoreInterface      $store    Where the locks are kept.
	 * @param LockSettingsInterface   $settings Says how long a lock is valid for.
	 * @param ZoneRepositoryInterface $zones    Where the zones are kept.
	 */
	public function __construct(
		private readonly LockStoreInterface $store,
		private readonly LockSettingsInterface $settings,
		private readonly ZoneRepositoryInterface $zones,
	) {
	}

	/**
	 * Locks a zone for a user, or renews the lock they already hold.
	 *
	 * @param int $zone_id Zone ID.
	 * @param int $user_id ID of the user editing the zone.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneLockedException   If someone else holds the zone's lock.
	 */
	public function lock( int $zone_id, int $user_id ): void {
		if ( ! $this->zones->find( $zone_id ) ) {
			throw new ZoneNotFoundException( $zone_id );
		}

		$this->check_unlocked( $zone_id, $user_id );

		$this->store->save( $zone_id, new ZoneLock( $user_id ), ZoneLock::duration( $this->settings->get_lock_period() ) );
	}

	/**
	 * Gets who is keeping a user out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @param int $user_id ID of the user who wants to edit the zone.
	 * @return int|null ID of the user holding the zone's lock, or null if the user may edit the zone.
	 */
	public function get_blocker_id( int $zone_id, int $user_id ): ?int {
		return $this->find_blocking_lock( $zone_id, $user_id )?->get_holder_id();
	}

	/**
	 * Checks that no one else's lock keeps a user out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @param int $user_id ID of the user who wants to change the zone.
	 *
	 * @throws ZoneLockedException If someone else holds the zone's lock.
	 */
	public function check_unlocked( int $zone_id, int $user_id ): void {
		$lock = $this->find_blocking_lock( $zone_id, $user_id );

		if ( $lock ) {
			throw new ZoneLockedException( $lock );
		}
	}

	/**
	 * Releases a zone's lock, whoever holds it.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function unlock( int $zone_id ): void {
		$this->store->delete( $zone_id );
	}

	/**
	 * Finds the lock that keeps a user out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @param int $user_id ID of the user who wants to edit the zone.
	 * @return ZoneLock|null The lock, or null if the zone has none or the user holds it.
	 */
	private function find_blocking_lock( int $zone_id, int $user_id ): ?ZoneLock {
		$lock = $this->store->find( $zone_id );

		return $lock && $lock->blocks( $user_id ) ? $lock : null;
	}
}
