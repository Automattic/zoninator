<?php
/**
 * The public JSON feed of a zone's posts.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

use stdClass;
use WP_Error;

/**
 * Serves each zone's published posts as JSON at /zones/{slug}/feed.json.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (do_zoninator_feeds() for serve(), get_zone_feed() for get(), and so on),
 * which delegates here. Its hooks stay registered on Zoninator.
 *
 * @since 0.12.0
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

	public function add_rewrite_rule() {
		add_rewrite_tag( '%' . Taxonomy::NAME . '%', '([^&]+)' );
		add_rewrite_rule( '^zones/([^/]+)/feed.json/?$', 'index.php?' . Taxonomy::NAME . '=$matches[1]', 'top' );
	}

	public function serve() {
		$query_var = get_query_var( Taxonomy::NAME );

		if ( ! empty( $query_var ) ) {
			$zone_slug = get_query_var( Taxonomy::NAME );
			$results   = $this->get( $zone_slug );
			if ( is_wp_error( $results ) ) {
				$this->send_error( $results->get_error_message() );
			}

			$this->send_json( $results );
		}
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

	/**
	 * Encode some data and echo it (possibly without cached headers)
	 *
	 * @param array $data
	 */
	private function send_json( $data ) {

		if ( null == $data ) {
			return false;
		}

		header( 'Content-Type: application/json' );
		echo wp_json_encode( $data );
		exit();
	}

	private function send_error( $message ) {
		$this->status_header_with_message( 406, $message );
		exit();
	}

	/**
	 * Modify the header and description in the global array
	 *
	 * @global array $wp_header_to_desc
	 * @param int    $status
	 * @param string $message
	 */
	private function status_header_with_message( $status, $message ) {
		global $wp_header_to_desc;

		$status                       = absint( $status );
		$official_message             = $wp_header_to_desc[ $status ] ?? '';
		$wp_header_to_desc[ $status ] = $message; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		status_header( $status );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_header_to_desc[ $status ] = $official_message;
	}
}
