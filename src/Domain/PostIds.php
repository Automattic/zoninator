<?php
/**
 * The post IDs a caller gives.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use InvalidArgumentException;

/**
 * Reads a list of post IDs given by a caller.
 *
 * Zones hold posts by ID. A caller that passes something else, such as a
 * WP_Post, has made a mistake that a cast would hide: PHP turns any object
 * into 1. So this refuses anything that isn't plainly an ID.
 *
 * @since 1.0.0
 */
final class PostIds {

	/**
	 * Gets each post ID as an integer, in the order given.
	 *
	 * @param array $post_ids Post IDs: integers, or strings of digits.
	 * @return int[] The same IDs, as integers.
	 *
	 * @throws InvalidArgumentException If one is neither.
	 */
	public static function from( array $post_ids ): array {
		$ids = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! is_int( $post_id ) && ! ( is_string( $post_id ) && ctype_digit( $post_id ) ) ) {
				throw new InvalidArgumentException( sprintf( 'A post ID must be an integer, not %s.', get_debug_type( $post_id ) ) );
			}

			$ids[] = (int) $post_id;
		}

		return $ids;
	}
}
