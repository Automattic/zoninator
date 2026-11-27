<?php
/**
 * Template tags: the functions themes use to read zones.
 *
 * @package Automattic\Zoninator
 */

use Automattic\Zoninator\Infrastructure\WordPress\Zones;

/**
 * Gets every zone.
 *
 * @since 1.0.0 Before, z_get_zones().
 *
 * @return WP_Term[]|false The zones, or false if they can't be read.
 */
function zoninator_get_zones() {
	return zoninator_container()->get( Zones::class )->get_all();
}

/**
 * Gets a zone.
 *
 * @since 1.0.0 Before, z_get_zone().
 *
 * @param int|string $zone Zone ID or slug.
 * @return WP_Term|false The zone, or false if there's no such zone.
 */
function zoninator_get_zone( $zone ) {
	return zoninator_container()->get( Zones::class )->get( $zone );
}

/**
 * Gets the posts in a zone, in zone order.
 *
 * @since 1.0.0 Before, z_get_posts_in_zone().
 *
 * @param int|string $zone Zone ID or slug.
 * @param array      $args WP_Query args to override the defaults.
 * @return WP_Post[] The posts.
 */
function zoninator_get_posts_in_zone( $zone, $args = array() ) {
	return zoninator_container()->get( Zones::class )->get_posts( $zone, $args );
}

/**
 * Gets a query for the posts in a zone, in zone order.
 *
 * @since 1.0.0 Before, z_get_zone_query().
 *
 * @param int|string $zone Zone ID or slug.
 * @param array      $args WP_Query args to override the defaults.
 * @return WP_Query The query.
 */
function zoninator_get_zone_query( $zone, $args = array() ) {
	return zoninator_container()->get( Zones::class )->get_query( $zone, $args );
}

/**
 * Gets the post after a post in a zone.
 *
 * @since 1.0.0 Before, z_get_next_post_in_zone().
 *
 * @param int|string $zone    Zone ID or slug.
 * @param int        $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Post|false The next post, or false if there's none or the post isn't in the zone.
 */
function zoninator_get_next_post_in_zone( $zone, $post_id = 0 ) {
	return zoninator_container()->get( Zones::class )->get_next_post( $zone, $post_id ? $post_id : (int) get_the_ID() );
}

/**
 * Gets the post before a post in a zone.
 *
 * @since 1.0.0 Before, z_get_prev_post_in_zone().
 *
 * @param int|string $zone    Zone ID or slug.
 * @param int        $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Post|false The previous post, or false if there's none or the post isn't in the zone.
 */
function zoninator_get_prev_post_in_zone( $zone, $post_id = 0 ) {
	return zoninator_container()->get( Zones::class )->get_prev_post( $zone, $post_id ? $post_id : (int) get_the_ID() );
}

/**
 * Gets every zone a post is in.
 *
 * @since 1.0.0 Before, z_get_post_zones().
 *
 * @param int $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Term[] The zones, by zone ID. Empty if the post isn't in any, or
 *                   wouldn't be listed in them (for example, a draft on the front end).
 */
function zoninator_get_post_zones( $post_id = 0 ) {
	return zoninator_container()->get( Zones::class )->get_for_post( $post_id ? $post_id : (int) get_the_ID() );
}

/**
 * Turns zone locks off, when it's the callback for the zoninator_zone_max_lock_period filter:
 * add_filter( 'zoninator_zone_max_lock_period', 'zoninator_disable_locks' ).
 *
 * @since 1.0.0 Before, z_disable_zoninator_locks().
 *
 * @return int
 */
function zoninator_disable_locks() {
	return -1;
}
