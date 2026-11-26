<?php
/**
 * Curates the posts in a zone for a user.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

/**
 * Adds, orders and removes the posts in a zone for a user, after checking
 * that the user may, that they may use each post, and that no one else
 * holds the zone's lock.
 *
 * ZoneCurator does the same work unchecked, for code that isn't acting for
 * a user.
 *
 * @since 1.0.0
 */
final class GuardedZoneCurator {

	/**
	 * Constructor.
	 *
	 * @param ZoneCurator $curator Changes the posts in a zone.
	 * @param ZoneGuard   $guard   The checks.
	 */
	public function __construct(
		private readonly ZoneCurator $curator,
		private readonly ZoneGuard $guard,
	) {
	}

	/**
	 * Adds posts to the end of a zone. See ZoneCurator::add_posts().
	 *
	 * @param int   $user_id  ID of the user adding them.
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function add_posts( int $user_id, int $zone_id, array $post_ids ): void {
		$this->guard->check_curate( $user_id, $zone_id, $post_ids );
		$this->curator->add_posts( $zone_id, $post_ids );
	}

	/**
	 * Makes a zone hold these posts and no others, in the order given. See ZoneCurator::set_posts().
	 *
	 * @param int   $user_id  ID of the user setting them.
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function set_posts( int $user_id, int $zone_id, array $post_ids ): void {
		$this->guard->check_curate( $user_id, $zone_id, $post_ids );
		$this->curator->set_posts( $zone_id, $post_ids );
	}

	/**
	 * Takes posts out of a zone. See ZoneCurator::remove_posts().
	 *
	 * @param int   $user_id  ID of the user removing them.
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function remove_posts( int $user_id, int $zone_id, array $post_ids ): void {
		$this->guard->check_curate( $user_id, $zone_id, $post_ids );
		$this->curator->remove_posts( $zone_id, $post_ids );
	}
}
