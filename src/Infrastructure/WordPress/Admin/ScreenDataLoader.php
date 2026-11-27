<?php
/**
 * Gathers what the Zones admin screen shows.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use WP_Term;

/**
 * Gathers what the Zones screen shows for the current user: the zones, the
 * open zone and its posts, who is editing it, and what the user may do.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ScreenDataLoader {

	/**
	 * Constructor.
	 *
	 * @param Zones        $zones        Zones and the posts in them.
	 * @param Capabilities $capabilities The capability checks.
	 * @param ZoneLocker   $locker       Zone locks.
	 */
	public function __construct(
		private readonly Zones $zones,
		private readonly Capabilities $capabilities,
		private readonly ZoneLocker $locker,
	) {
	}

	/**
	 * Gathers the screen's data.
	 *
	 * @param int $zone_id ID of the zone to open, or 0 for none.
	 * @return ScreenData
	 */
	public function load( int $zone_id ): ScreenData {
		$user_id       = get_current_user_id();
		$zones         = $this->get_zones();
		$can_add_zones = $this->capabilities->can_create_zones( $user_id );

		// Someone who can't add a zone has nothing to do without one open, so open the first.
		if ( ! $zone_id && ! $can_add_zones && $zones ) {
			$zone_id = (int) $zones[0]->term_id;
		}

		$zone = $zone_id ? $this->zones->get( $zone_id ) : null;
		$zone = $zone instanceof WP_Term ? $zone : null;

		$locked_by = $zone ? (int) $this->locker->get_blocker_id( (int) $zone->term_id, $user_id ) : 0;
		$holder    = $locked_by ? get_userdata( $locked_by ) : false;

		return new ScreenData(
			zones: $zones,
			active_zone_id: $zone_id,
			active_zone: $zone,
			zone_posts: $zone ? $this->zones->get_posts( $zone ) : array(),
			locked_by: $locked_by,
			locked_by_name: $holder ? $holder->display_name : '',
			can_add_zones: $can_add_zones,
			can_edit_zone: $this->capabilities->can_edit_zone( $user_id, $zone ? (int) $zone->term_id : 0 ),
			can_manage_zones: $this->capabilities->can_curate_zones( $user_id ),
		);
	}

	/**
	 * Gets every zone, for the list.
	 *
	 * @return WP_Term[]
	 */
	private function get_zones(): array {
		/**
		 * Filters the get_terms() arguments for the list of zones on the Zones screen.
		 *
		 * The taxonomy is always the zone taxonomy. This doesn't change
		 * zoninator_get_zones(), the REST API or the abilities.
		 *
		 * @since 0.7
		 *
		 * @param array $args get_terms() arguments.
		 */
		$zones = $this->zones->get_all( apply_filters( 'zoninator_admin_page_get_zones_args', array() ) );

		if ( ! is_array( $zones ) ) {
			return array();
		}

		// A site's args can ask get_terms() for IDs in place of terms, so look those up.
		$zones = array_map(
			function ( $zone ) {
				return $zone instanceof WP_Term ? $zone : $this->zones->get( $zone );
			},
			$zones
		);

		return array_values(
			array_filter(
				$zones,
				static function ( $zone ) {
					return $zone instanceof WP_Term;
				}
			)
		);
	}
}
