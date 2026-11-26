<?php
/**
 * Zone locks kept in transients.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\LockStoreInterface;
use Automattic\Zoninator\Domain\ZoneLock;

/**
 * Keeps each zone's lock in a transient that holds the ID of the user
 * editing the zone. The transient is keyed like the zone's post meta, and
 * WordPress drops it when its time runs out.
 *
 * @since 1.0.0
 * @internal Use Automattic\Zoninator\Application\ZoneLocker instead.
 */
final class TransientLockStore implements LockStoreInterface {

	/**
	 * Gets a zone's lock.
	 *
	 * @param int $zone_id Zone ID.
	 * @return ZoneLock|null The lock, or null if the zone isn't locked or its lock has run out.
	 */
	public function find( int $zone_id ): ?ZoneLock {
		$holder_id = absint( get_transient( $this->get_key( $zone_id ) ) );

		return $holder_id ? new ZoneLock( $holder_id ) : null;
	}

	/**
	 * Keeps a lock on a zone, replacing any it already has.
	 *
	 * @param int      $zone_id Zone ID.
	 * @param ZoneLock $lock    The lock.
	 * @param int      $seconds How long to keep it.
	 */
	public function save( int $zone_id, ZoneLock $lock, int $seconds ): void {
		set_transient( $this->get_key( $zone_id ), $lock->get_holder_id(), $seconds );
	}

	/**
	 * Removes a zone's lock, if it has one.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function delete( int $zone_id ): void {
		delete_transient( $this->get_key( $zone_id ) );
	}

	/**
	 * @param int $zone_id Zone ID.
	 * @return string The name of the transient that holds the zone's lock.
	 */
	private function get_key( int $zone_id ): string {
		return Zones::META_PREFIX . $zone_id;
	}
}
