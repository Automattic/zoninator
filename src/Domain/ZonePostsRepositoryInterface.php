<?php
/**
 * Where the posts in each zone are kept.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

/**
 * Keeps the posts in each zone, in order.
 *
 * @since 1.0.0
 */
interface ZonePostsRepositoryInterface {

	/**
	 * Adds posts to the end of a zone, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
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
