<?php
/**
 * Where the posts in each zone are kept.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Keeps the posts in each zone, in order.
 *
 * @since 1.0.0
 */
interface ZonePostsRepositoryInterface {

	/**
	 * Gets every post in a zone, in order, whatever its status.
	 *
	 * @param int $zone_id Zone ID.
	 * @return int[] Post IDs.
	 */
	public function get_post_ids( int $zone_id ): array;

	/**
	 * Adds posts to the end of a zone, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs, none of them in the zone already. The caller leaves those out.
	 */
	public function append( int $zone_id, array $post_ids ): void;

	/**
	 * Makes a zone hold these posts and no others, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function replace( int $zone_id, array $post_ids ): void;

	/**
	 * Takes posts out of a zone.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function remove( int $zone_id, array $post_ids ): void;

	/**
	 * Takes every post out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function clear( int $zone_id ): void;
}
