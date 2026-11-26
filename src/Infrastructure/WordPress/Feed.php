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

		$posts = $this->zones->get_posts( $zone, apply_filters( 'zoninator_json_feed_fields', array(), $zone_slug_or_id ) );

		return apply_filters( 'zoninator_json_feed_results', $this->filter_fields( $posts ), $zone_slug_or_id );
	}

	/**
	 * Reduces each post to the feed's fields.
	 *
	 * @param WP_Post[] $posts The zone's posts.
	 * @return stdClass[] One item for each post, with only those fields.
	 */
	private function filter_fields( array $posts ): array {
		$fields = (array) apply_filters(
			'zoninator_zone_feed_fields',
			array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' )
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
