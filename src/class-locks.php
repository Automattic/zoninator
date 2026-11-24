<?php
/**
 * Zone locks.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

/**
 * Stops two people editing a zone at once. A lock is a transient, keyed
 * like the zone's post meta, that holds the ID of the user editing the
 * zone and lasts one second longer than the lock period in Settings.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (lock_zone() for lock(), and so on), which delegates here.
 *
 * @since 0.12.0
 */
final class Locks {

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Zoninator's settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 * @param Settings $settings Zoninator's settings.
	 */
	public function __construct( Zones $zones, Settings $settings ) {
		$this->zones    = $zones;
		$this->settings = $settings;
	}

	public function lock( $zone, $user_id = 0 ) {
		$zone_id = $this->zones->get_id( $zone );

		if ( ! $zone_id ) {
			return false;
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$lock_key = $this->zones->get_meta_key( $zone );
		$expiry   = $this->settings->get_lock_period() + 1; // Add a one to avoid most race condition issues between lock expiry and ajax call
		set_transient( $lock_key, $user_id, $expiry );

		// Possible alternative: set zone lock as property with time and user
		return null;
	}

	// Not really needed with transients...
	public function unlock( $zone ) {
		$zone_id = $this->zones->get_id( $zone );

		if ( ! $zone_id ) {
			return false;
		}

		$lock_key = $this->zones->get_meta_key( $zone );

		delete_transient( $lock_key );
		return null;
	}

	public function is_locked( $zone ) {
		$zone_id = $this->zones->get_id( $zone );
		if ( ! $zone_id ) {
			return false;
		}

		$user     = wp_get_current_user();
		$lock_key = $this->zones->get_meta_key( $zone );

		$lock = get_transient( $lock_key );

		// If lock doesn't exist, or check if current user same as lock user
		if ( ! $lock || absint( $lock ) === absint( $user->ID ) ) {
			return false;
		} else {          // return user_id of locking user
			return absint( $lock );
		}
	}
}
