<?php
/**
 * The data in the public JSON feed of a zone's posts.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use stdClass;
use WP_Error;

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
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 */
	public function __construct( Zones $zones ) {
		$this->zones = $zones;
	}

	/**
	 * @param $zone_slug_or_id
	 * @return array|WP_Error
	 */
	public function get( $zone_slug_or_id ) {
		$zone_id = $this->zones->get( $zone_slug_or_id );

		if ( empty( $zone_id ) ) {
			return new WP_Error( 'invalid-zone-supplied', __( 'Invalid zone supplied', 'zoninator' ) );
		}

		$results          = $this->zones->get_posts( $zone_id, apply_filters( 'zoninator_json_feed_fields', array(), $zone_slug_or_id ) );
		$filtered_results = $this->filter_fields( $results );

		return apply_filters( 'zoninator_json_feed_results', $filtered_results, $zone_slug_or_id );
	}

	private function filter_fields( $results ) {
		$filtered_results   = array();
		$whitelisted_fields = apply_filters(
			'zoninator_zone_feed_fields',
			array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' )
		);

		$filtered_results = array();
		$i                = 0;
		foreach ( $results as $result ) {
			if ( ! isset( $filtered_results[ $i ] ) ) {
				$filtered_results[ $i ] = new stdClass();
			}

			foreach ( $whitelisted_fields as $field ) {
				if ( ! isset( $filtered_results[ $i ] ) ) {
					$filtered_results[ $i ] = new stdClass();
				}

				// Don't expose content or excerpt for password-protected posts
				if ( ( 'post_content' === $field || 'post_excerpt' === $field ) && ! empty( $result->post_password ) ) {
					$filtered_results[ $i ]->$field = '';
				} else {
					// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- see https://github.com/PHPCSStandards/PHP_CodeSniffer/issues/598
					$filtered_results[ $i ]->$field = $result->$field;
				}
			}

			++$i;
		}

		return $filtered_results;
	}
}
