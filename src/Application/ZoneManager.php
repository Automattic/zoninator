<?php
/**
 * Creates and changes zones.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;

/**
 * Creates zones, changes their name, slug and details, and deletes them.
 *
 * @since 1.0.0
 */
final class ZoneManager {

	/**
	 * Constructor.
	 *
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZoneSettingsInterface        $settings   Says which details every zone has.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 * @param ZoneLocker                   $locker     Zone locks.
	 */
	public function __construct(
		private readonly ZoneRepositoryInterface $zones,
		private readonly ZoneSettingsInterface $settings,
		private readonly ZonePostsRepositoryInterface $zone_posts,
		private readonly ZoneLocker $locker,
	) {
	}

	/**
	 * Creates a zone.
	 *
	 * @param string               $slug    The zone's slug.
	 * @param string               $name    The zone's name, or '' to use the slug.
	 * @param array<string, mixed> $details Details to set, by name. The rest get their default values.
	 * @return Zone The new zone.
	 *
	 * @throws InvalidZoneException  If the slug is empty.
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function create( string $slug, string $name = '', array $details = array() ): Zone {
		return $this->zones->insert( Zone::create( $slug, $name, $details, $this->settings->get_detail_defaults() ) );
	}

	/**
	 * Changes a zone.
	 *
	 * @param int                                                                 $zone_id Zone ID.
	 * @param array{name?: string, slug?: string, details?: array<string, mixed>} $changes What to change. Anything left out, or empty, stays as it is.
	 * @return Zone The zone as it was saved.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneNotSavedException If the zone couldn't be saved.
	 */
	public function update( int $zone_id, array $changes ): Zone {
		$zone = $this->zones->find( $zone_id );

		if ( ! $zone ) {
			throw new ZoneNotFoundException( $zone_id );
		}

		return $this->zones->update( $zone->with_changes( $changes, $this->settings->get_detail_defaults() ) );
	}

	/**
	 * Deletes a zone and everything kept for it: its posts' places in it, and
	 * its lock. The posts themselves are left as they are, out of the zone.
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneNotSavedException If the zone couldn't be deleted. Its posts have already been taken out.
	 */
	public function remove( int $zone_id ): void {
		if ( ! $this->zones->find( $zone_id ) ) {
			throw new ZoneNotFoundException( $zone_id );
		}

		$this->zone_posts->clear( $zone_id );
		$this->zones->delete( $zone_id );
		$this->locker->unlock( $zone_id );
	}
}
