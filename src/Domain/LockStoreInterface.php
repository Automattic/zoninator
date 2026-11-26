<?php
/**
 * Where zone locks are kept.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Keeps each zone's lock until it's released or runs out.
 *
 * @since 1.0.0
 */
interface LockStoreInterface {

	/**
	 * Gets a zone's lock.
	 *
	 * @param int $zone_id Zone ID.
	 * @return ZoneLock|null The lock, or null if the zone isn't locked or its lock has run out.
	 */
	public function find( int $zone_id ): ?ZoneLock;

	/**
	 * Keeps a lock on a zone, replacing any it already has.
	 *
	 * @param int      $zone_id Zone ID.
	 * @param ZoneLock $lock    The lock.
	 * @param int      $seconds How long to keep it.
	 */
	public function save( int $zone_id, ZoneLock $lock, int $seconds ): void;

	/**
	 * Removes a zone's lock, if it has one.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function delete( int $zone_id ): void;
}
