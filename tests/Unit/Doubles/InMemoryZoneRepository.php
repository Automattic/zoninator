<?php
declare( strict_types = 1 );

/**
 * A zone repository for unit tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;

/**
 * Keeps zones in an array, and refuses two zones with one slug, as a real store would.
 */
final class InMemoryZoneRepository implements ZoneRepositoryInterface {

	/**
	 * Zones, by ID.
	 *
	 * @var array<int, Zone>
	 */
	private $zones = array();

	public function find( int $zone_id ): ?Zone {
		return $this->zones[ $zone_id ] ?? null;
	}

	public function insert( Zone $zone ): Zone {
		foreach ( $this->zones as $existing ) {
			if ( $existing->get_slug() === $zone->get_slug() ) {
				throw new ZoneNotSavedException( 'A zone with that slug already exists.', 'term_exists' );
			}
		}

		$zone_id                 = $this->zones ? max( array_keys( $this->zones ) ) + 1 : 1;
		$this->zones[ $zone_id ] = new Zone( $zone_id, $zone->get_slug(), $zone->get_name(), $zone->get_details() );

		return $this->zones[ $zone_id ];
	}

	public function update( Zone $zone ): Zone {
		$this->zones[ (int) $zone->get_id() ] = $zone;

		return $zone;
	}

	public function delete( int $zone_id ): void {
		unset( $this->zones[ $zone_id ] );
	}
}
