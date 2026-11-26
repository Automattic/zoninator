<?php
/**
 * What the Zones admin screen shows for one request.
 *
 * @package Automattic\Zoninator
 */

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
	 * Every zone, for the list.
	 *
	 * @var WP_Term[]
	 */
	public readonly array $zones;

	/**
	 * ID of the zone that was asked for, or 0 for none. There may be no such zone.
	 *
	 * @var int
	 */
	public readonly int $active_zone_id;

	/**
	 * The zone that's open, or null for none.
	 *
	 * @var WP_Term|null
	 */
	public readonly ?WP_Term $active_zone;

	/**
	 * The posts in the open zone, in order.
	 *
	 * @var WP_Post[]
	 */
	public readonly array $zone_posts;

	/**
	 * ID of the other user who is editing the open zone, or 0 if no one is.
	 *
	 * @var int
	 */
	public readonly int $locked_by;

	/**
	 * Display name of that user, or '' if no one is editing the zone or the user is gone.
	 *
	 * @var string
	 */
	public readonly string $locked_by_name;

	/**
	 * Whether the user may create zones.
	 *
	 * @var bool
	 */
	public readonly bool $can_add_zones;

	/**
	 * Whether the user may change the open zone's name and details, or delete it.
	 *
	 * @var bool
	 */
	public readonly bool $can_edit_zone;

	/**
	 * Whether the user may curate zones.
	 *
	 * @var bool
	 */
	public readonly bool $can_manage_zones;

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
	public function __construct( array $zones, int $active_zone_id, ?WP_Term $active_zone, array $zone_posts, int $locked_by, string $locked_by_name, bool $can_add_zones, bool $can_edit_zone, bool $can_manage_zones ) {
		$this->zones            = $zones;
		$this->active_zone_id   = $active_zone_id;
		$this->active_zone      = $active_zone;
		$this->zone_posts       = $zone_posts;
		$this->locked_by        = $locked_by;
		$this->locked_by_name   = $locked_by_name;
		$this->can_add_zones    = $can_add_zones;
		$this->can_edit_zone    = $can_edit_zone;
		$this->can_manage_zones = $can_manage_zones;
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
