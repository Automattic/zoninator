<?php
/**
 * Creates, changes and deletes zones for a user.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\Zone;

/**
 * Creates, changes and deletes zones for a user, after checking that the
 * user may and that no one else holds the zone's lock.
 *
 * Every way a person or an agent changes a zone goes through this class or
 * GuardedZoneCurator: the Zones screen, the REST API and the abilities.
 * ZoneManager does the same work unchecked, for code that isn't acting for
 * a user.
 *
 * @since 1.0.0
 */
final class GuardedZoneManager {

	/**
	 * Constructor.
	 *
	 * @param ZoneManager $manager Creates, changes and deletes zones.
	 * @param ZoneGuard   $guard   The checks.
	 */
	public function __construct(
		private readonly ZoneManager $manager,
		private readonly ZoneGuard $guard,
	) {
	}

	/**
	 * Creates a zone. See ZoneManager::create().
	 *
	 * @param int                  $user_id ID of the user creating it.
	 * @param string               $slug    The zone's slug.
	 * @param string               $name    The zone's name, or '' to use the slug.
	 * @param array<string, mixed> $details Details to set, by name.
	 * @return Zone The new zone.
	 */
	public function create( int $user_id, string $slug, string $name = '', array $details = array() ): Zone {
		$this->guard->check_create( $user_id );

		return $this->manager->create( $slug, $name, $details );
	}

	/**
	 * Changes a zone. See ZoneManager::update().
	 *
	 * @param int   $user_id ID of the user changing it.
	 * @param int   $zone_id Zone ID.
	 * @param array $changes What to change.
	 * @return Zone The zone as it was saved.
	 */
	public function update( int $user_id, int $zone_id, array $changes ): Zone {
		$this->guard->check_edit( $user_id, $zone_id );

		return $this->manager->update( $zone_id, $changes );
	}

	/**
	 * Deletes a zone. See ZoneManager::remove().
	 *
	 * @param int $user_id ID of the user deleting it.
	 * @param int $zone_id Zone ID.
	 */
	public function remove( int $user_id, int $zone_id ): void {
		$this->guard->check_edit( $user_id, $zone_id );

		$this->manager->remove( $zone_id );
	}
}
