<?php
/**
 * What the Zones admin screen shows for one request.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use WP_Post;
use WP_Term;

/**
 * Everything the Zones screen needs to print itself for the current user:
 * the zones, the one that's open, its posts, who is editing it, and what the
 * user may do. ScreenDataLoader gathers it, and the views only read it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ScreenData {

	/**
	 * Constructor.
	 *
	 * @param WP_Term[]    $zones            Every zone.
	 * @param int          $active_zone_id   ID of the zone that was asked for, or 0.
	 * @param WP_Term|null $active_zone      The zone that's open, or null.
	 * @param WP_Post[]    $zone_posts       The posts in the open zone, in order.
	 * @param int          $locked_by        ID of the other user editing the open zone, or 0.
	 * @param string       $locked_by_name   Display name of that user, or ''.
	 * @param bool         $can_add_zones    Whether the user may create zones.
	 * @param bool         $can_edit_zone    Whether the user may change or delete the open zone.
	 * @param bool         $can_manage_zones Whether the user may curate zones.
	 */
	public function __construct(
		public readonly array $zones,
		public readonly int $active_zone_id,
		public readonly ?WP_Term $active_zone,
		public readonly array $zone_posts,
		public readonly int $locked_by,
		public readonly string $locked_by_name,
		public readonly bool $can_add_zones,
		public readonly bool $can_edit_zone,
		public readonly bool $can_manage_zones,
	) {
	}

	/**
	 * Gets the open zone's ID.
	 *
	 * @return int The ID, or 0 if no zone is open.
	 */
	public function get_zone_id(): int {
		return $this->active_zone ? (int) $this->active_zone->term_id : 0;
	}
}
