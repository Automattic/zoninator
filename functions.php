<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Yes, a prefix of z is too short, but fixing this is a breaking change.

function z_get_zoninator() {
	global $zoninator;
	return $zoninator;
}

/**
 * Gets every zone.
 *
 * @return WP_Term[]|false The zones, or false if they can't be read.
 */
function z_get_zones() {
	return z_get_zoninator()->get_zones();
}

/**
 * Gets a zone.
 *
 * @param int|string $zone Zone ID or slug.
 * @return WP_Term|false The zone, or false if there's no such zone.
 */
function z_get_zone( $zone ) {
	return z_get_zoninator()->get_zone( $zone );
}

/**
 * Gets the posts in a zone, in zone order.
 *
 * @param int|string $zone Zone ID or slug.
 * @param array      $args WP_Query args to override the defaults.
 * @return WP_Post[] The posts.
 */
function z_get_posts_in_zone( $zone, $args = array() ) {
	return z_get_zoninator()->get_zone_posts( $zone, $args );
}

/**
 * Gets a query for the posts in a zone, in zone order.
 *
 * @param int|string $zone Zone ID or slug.
 * @param array      $args WP_Query args to override the defaults.
 * @return WP_Query The query.
 */
function z_get_zone_query( $zone, $args = array() ) {
	return z_get_zoninator()->get_zone_query( $zone, $args );
}

/**
 * Gets the post after a post in a zone.
 *
 * @param int|string $zone    Zone ID or slug.
 * @param int        $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Post|false The next post, or false if there's none or the post isn't in the zone.
 */
function z_get_next_post_in_zone( $zone, $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_next_post_in_zone( $zone, $post_id );
}

/**
 * Gets the post before a post in a zone.
 *
 * @param int|string $zone    Zone ID or slug.
 * @param int        $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Post|false The previous post, or false if there's none or the post isn't in the zone.
 */
function z_get_prev_post_in_zone( $zone, $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_prev_post_in_zone( $zone, $post_id );
}

/**
 * Gets every zone a post is in.
 *
 * @param int $post_id Post ID. Defaults to the current post in the loop.
 * @return WP_Term[] The zones, by zone ID. Empty if the post isn't in any, or
 *                   wouldn't be listed in them (for example, a draft on the front end).
 */
function z_get_post_zones( $post_id = 0 ) {
	$post_id = z_get_loop_post_id_or_default( $post_id );
	return z_get_zoninator()->get_zones_for_post( $post_id );
}

function z_get_loop_post_id_or_default( $post_id = 0 ) {
	if ( ! $post_id ) {
		global $post;
		if ( $post && isset( $post->ID ) ) {
			$post_id = $post->ID;
		}
	}

	return $post_id;
}

/**
 * Handy function to disable the locking mechanism
 */
function z_disable_zoninator_locks() {
	return -1;
}

// (Should probably publicly expose set_zone_posts as well, e.g. if we wanted to add a metabox on the Edit Post page)
