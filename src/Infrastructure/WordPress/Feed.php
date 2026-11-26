<?php
/**
 * The data in the public JSON feed of a zone's posts.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use stdClass;
use WP_Error;
use WP_Post;

/**
 * A zone's published posts as the JSON feed lists them: only the feed's
 * fields, through the feed's filters. FeedEndpoint serves it at
 * /zones/{slug}/feed.json, and GET /zoninator/v1/zones/{zone_id}/posts returns it.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Feed {

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 */
	public function __construct( private readonly Zones $zones ) {
	}

	/**
	 * Gets a zone's feed.
	 *
	 * @param int|string $zone_slug_or_id Zone ID or slug, as the caller was given it. The feed's filters are passed it as it is.
	 * @return array|WP_Error|null The feed's items, or null if there's no such zone. A zoninator_json_feed_results callback can return something else, such as a WP_Error.
	 */
	public function get( $zone_slug_or_id ): mixed {
		$zone = $this->zones->get( $zone_slug_or_id );

		if ( ! $zone ) {
			return null;
		}

		/**
		 * Filters the WP_Query arguments for the posts in a zone's JSON feed, and in
		 * GET /zoninator/v1/zones/{zone_id}/posts.
		 *
		 * Despite the name, these are query arguments, not fields. orderby and
		 * meta_key can't be changed.
		 *
		 * @since 0.6
		 *
		 * @param array      $args            WP_Query arguments.
		 * @param int|string $zone_slug_or_id The slug from the feed URL, or the ID from the REST route.
		 */
		$posts = $this->zones->get_posts( $zone, apply_filters( 'zoninator_json_feed_fields', array(), $zone_slug_or_id ) );

		/**
		 * Filters a zone's JSON feed, and GET /zoninator/v1/zones/{zone_id}/posts,
		 * before they're sent.
		 *
		 * Return a WP_Error to refuse it: the feed sends a 406, and the REST route a 400.
		 *
		 * @since 0.6
		 *
		 * @param stdClass[]|WP_Error $results         One item for each post.
		 * @param int|string         $zone_slug_or_id The slug from the feed URL, or the ID from the REST route.
		 */
		return apply_filters( 'zoninator_json_feed_results', $this->filter_fields( $posts ), $zone_slug_or_id );
	}

	/**
	 * Reduces each post to the feed's fields.
	 *
	 * @param WP_Post[] $posts The zone's posts.
	 * @return stdClass[] One item for each post, with only those fields.
	 */
	private function filter_fields( array $posts ): array {
		/**
		 * Filters the post fields each item in a zone's JSON feed has.
		 *
		 * The feed is public, and gives each field as it's stored. post_content
		 * isn't one of the defaults, as stored content hasn't been through the
		 * site's content filters, which is where a paywall or a membership
		 * plugin holds it back. Add it only if all of a zone's content is public.
		 *
		 * @since 0.9
		 * @since 1.0.0 post_content is no longer one of the defaults.
		 *
		 * @param string[] $fields WP_Post fields. Default ID, post_date, post_title,
		 *                         post_excerpt, post_status and guid.
		 */
		$fields = (array) apply_filters(
			'zoninator_zone_feed_fields',
			array( 'ID', 'post_date', 'post_title', 'post_excerpt', 'post_status', 'guid' )
		);
		$items  = array();

		foreach ( $posts as $post ) {
			$item = new stdClass();

			foreach ( $fields as $field ) {
				// Don't expose content or excerpt for password-protected posts
				$is_protected = in_array( $field, array( 'post_content', 'post_excerpt' ), true ) && ! empty( $post->post_password );

				$item->$field = $is_protected ? '' : $post->$field;
			}

			$items[] = $item;
		}

		return $items;
	}
}
