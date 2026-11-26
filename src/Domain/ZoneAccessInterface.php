<?php
/**
 * Who may change zones.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Says what a user may do to zones and which posts they may put in one.
 *
 * @since 1.0.0
 */
interface ZoneAccessInterface {

	/**
	 * Whether a user may create zones.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_create_zones( int $user_id ): bool;

	/**
	 * Whether a user may change a zone's name and details, or delete it.
	 *
	 * @param int $user_id User ID.
	 * @param int $zone_id Zone ID.
	 * @return bool
	 */
	public function can_edit_zone( int $user_id, int $zone_id ): bool;

	/**
	 * Whether a user may curate zones: add, order and remove their posts.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_curate_zones( int $user_id ): bool;

	/**
	 * Whether a user may put a post in a zone, or take it out.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function can_use_post( int $user_id, int $post_id ): bool;
}
