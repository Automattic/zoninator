<?php
/**
 * Where zones are kept.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Finds, saves and deletes zones.
 *
 * @since 1.0.0
 */
interface ZoneRepositoryInterface {

	/**
	 * Finds a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @return Zone|null The zone, or null if there's none with that ID.
	 */
	public function find( int $zone_id ): ?Zone;

	/**
	 * Saves a new zone.
	 *
	 * @param Zone $zone The zone, which has no ID yet.
	 * @return Zone The zone as it was saved, with its ID.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function insert( Zone $zone ): Zone;

	/**
	 * Saves changes to a zone.
	 *
	 * @param Zone $zone The zone, with its ID.
	 * @return Zone The zone as it was saved.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function update( Zone $zone ): Zone;

	/**
	 * Deletes a zone. Its posts are left alone; see ZonePostsRepositoryInterface::clear().
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotSavedException If the zone couldn't be deleted.
	 */
	public function delete( int $zone_id ): void;
}
