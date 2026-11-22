<?php
/**
 * The admin-ajax handlers behind the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Admin;

use Zoninator;

/**
 * Handles the Zones screen's admin-ajax requests: adding, removing and
 * ordering zone posts, searching for posts, and refreshing zone locks.
 *
 * Zoninator keeps a public method for each of these, with an ajax_ prefix
 * (ajax_add_post() for add_post(), and ajax_return() for respond()), which
 * delegates here. Hooks are registered on it, so array( Zoninator(), 'method' )
 * keeps working as a callback. Callbacks this class hands to WordPress point
 * at Zoninator too, for the same reason.
 */
class Ajax {

	/**
	 * The nonce action every handler checks, passed through Zoninator::_get_nonce_key().
	 */
	public const NONCE_ACTION = 'ajax-action';

	/**
	 * The Zoninator facade, which holds the zone data and request helpers.
	 *
	 * @var Zoninator
	 */
	private $zoninator;

	/**
	 * Constructor.
	 *
	 * @param Zoninator $zoninator The Zoninator facade.
	 */
	public function __construct( Zoninator $zoninator ) {
		$this->zoninator = $zoninator;
	}

	public function respond( $status, $content = '', $action = '' ) {
		$action = empty( $action ) ? self::NONCE_ACTION : $action;
		$nonce  = wp_create_nonce( $this->zoninator->_get_nonce_key( $action ) );

		wp_send_json(
			array(
				'status'  => $status,
				'content' => $content,
				'nonce'   => $nonce,
			)
		);
	}

	public function add_post() {
		$zone_id = $this->zoninator->_get_post_var( 'zone_id', 0, 'absint' );
		$post_id = $this->zoninator->_get_post_var( 'post_id', 0, 'absint' );

		// Verify nonce
		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access( '', $zone_id );

		// Validate
		if ( ! $zone_id || ! $this->zoninator->current_user_can_add_post_to_zone( $post_id ) ) {
			$this->respond( 0 );
		}

		$result = $this->zoninator->add_zone_posts( $zone_id, $post_id, true );

		if ( is_wp_error( $result ) ) {
			$status  = 0;
			$content = $result->get_error_message();
		} else {
			$post = get_post( $post_id );
			$zone = $this->zoninator->get_zone( $zone_id );

			ob_start();
			$this->zoninator->admin_page_zone_post( $post, $zone );
			$content = ob_get_contents();
			ob_end_clean();

			$status = 1;
		}

		$this->respond( $status, $content );
	}

	public function remove_post() {
		$zone_id = $this->zoninator->_get_post_var( 'zone_id', 0, 'absint' );
		$post_id = $this->zoninator->_get_post_var( 'post_id', 0, 'absint' );

		// Verify nonce
		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access( '', $zone_id );

		// Validate
		if ( ! $zone_id || ! $post_id ) {
			$this->respond( 0 );
		}

		$result = $this->zoninator->remove_zone_posts( $zone_id, $post_id );

		if ( is_wp_error( $result ) ) {
			$status  = 0;
			$content = $result->get_error_message();
		} else {
			$status  = 1;
			$content = '';
		}

		$this->respond( $status, $content );
	}

	public function reorder_posts() {
		$zone_id  = $this->zoninator->_get_post_var( 'zone_id', 0, 'absint' );
		$post_ids = (array) $this->zoninator->_get_post_var( 'posts', array(), 'absint' );

		// Verify nonce
		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access( '', $zone_id );

		// validate
		if ( ! $zone_id || array() === $post_ids || count( $post_ids ) !== count( array_filter( $post_ids, array( $this->zoninator, 'current_user_can_add_post_to_zone' ) ) ) ) {
			$this->respond( 0 );
		}

		$result = $this->zoninator->add_zone_posts( $zone_id, $post_ids, false );

		if ( is_wp_error( $result ) ) {
			$status  = 0;
			$content = $result->get_error_message();
		} else {
			$status  = 1;
			$content = '';
		}

		$this->respond( $status, $content );
	}

	// TODO: implement in front-end
	public function move_zone_post( $from_zone, $to_zone, $post_id ) {
		$from_zone_id = $this->zoninator->_get_post_var( 'from_zone_id', 0, 'absint' );
		$to_zone_id   = $this->zoninator->_get_post_var( 'to_zone_id', 0, 'absint' );

		$this->zoninator->verify_nonce( self::NONCE_ACTION );

		// TODO: validate both zones exist, post exists

		// Add to new zone
		$this->zoninator->add_zone_posts( $to_zone_id, $post_id, true );

		// Remove from old zone
		$this->zoninator->remove_zone_posts( $from_zone_id, $post_id );
	}

	public function search_posts() {

		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access();

		$q           = $this->zoninator->_get_request_var( 'term', '', 'stripslashes' );
		$filter_cat  = $this->zoninator->_get_request_var( 'cat', '', 'absint' );
		$filter_date = $this->zoninator->_get_request_var( 'date', '', 'striptags' );
		$exclude     = (array) $this->zoninator->_get_request_var( 'exclude', array(), 'absint' );
		$post_types  = $this->zoninator->get_supported_post_types();

		if ( '' === $q ) {
			// With nothing typed yet, offer the latest content.
			$args = apply_filters(
				'zoninator_recent_posts_args',
				array(
					'posts_per_page'      => $this->zoninator->get_posts_per_page(),
					'order'               => 'DESC',
					'orderby'             => 'post_date',
					'post_type'           => $post_types,
					'ignore_sticky_posts' => true,
					'post_status'         => array( 'publish', 'future' ),
					'suppress_filters'    => false,
				)
			);
		} else {
			$limit = $this->zoninator->_get_request_var( 'limit', $this->zoninator->get_posts_per_page() );
			if ( $limit <= 0 ) {
				$limit = $this->zoninator->get_posts_per_page();
			}

			$args = apply_filters(
				'zoninator_search_args',
				array(
					's'                => $q,
					'posts_per_page'   => $limit,
					'post_type'        => $post_types,
					'post_status'      => array( 'publish', 'future' ),
					'order'            => 'DESC',
					'orderby'          => 'post_date',
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
					'suppress_filters' => true,
					'no_found_rows'    => true,
				)
			);
		}

		if ( $this->zoninator->_validate_category_filter( $filter_cat ) ) {
			$args['cat'] = $filter_cat;
		}

		if ( $this->zoninator->_validate_date_filter( $filter_date ) ) {
			$filter_date_parts = explode( '-', $filter_date );
			$args['year']      = $filter_date_parts[0];
			$args['monthnum']  = $filter_date_parts[1];
			$args['day']       = $filter_date_parts[2];
		}

		$stripped_posts = array();
		foreach ( $this->get_posts_excluding( $args, $exclude ) as $post ) {
			$stripped_posts[] = apply_filters(
				'zoninator_search_results_post',
				array(
					'title'             => empty( $post->post_title ) ? __( '(no title)', 'zoninator' ) : $post->post_title,
					'post_id'           => $post->ID,
					'date'              => get_the_time( get_option( 'date_format' ), $post ),
					'post_type'         => $post->post_type,
					'post_type_label'   => $this->zoninator->get_post_type_label( $post ),
					'post_status'       => $post->post_status,
					'post_status_label' => $this->zoninator->get_post_status_label( $post ),
				),
				$post
			);
		}

		wp_send_json( $stripped_posts );
	}

	public function update_lock() {
		$zone_id = $this->zoninator->_get_post_var( 'zone_id', 0, 'absint' );

		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access( '', $zone_id );

		if ( ! $zone_id ) {
			exit;
		}

		if ( ! $this->zoninator->is_zone_locked( $zone_id ) ) {
			$this->zoninator->lock_zone( $zone_id );
			$this->respond( 1, '' );
		}
	}

	/**
	 * Get posts but leave out the given IDs, without a NOT IN clause.
	 *
	 * NOT IN gets slow on big posts tables, so we fetch a few more rows
	 * and drop the excluded ones in PHP.
	 *
	 * The search sends the IDs to exclude in the request, so at most 100 extra
	 * rows are fetched, whatever the request holds. Zones are hand-curated and
	 * small, so this only shortens the list when over 100 excluded posts are
	 * among the newest matches.
	 *
	 * @param array $args    WP_Query args.
	 * @param array $exclude Post IDs to leave out.
	 * @return WP_Post[]
	 */
	private function get_posts_excluding( $args, $exclude ) {
		$exclude = array_unique( array_map( 'absint', (array) $exclude ) );
		$limit   = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;

		if ( $limit > 0 ) {
			$args['posts_per_page'] = $limit + min( count( $exclude ), 100 );
		}

		$posts = array_values(
			array_filter(
				get_posts( $args ),
				static function ( $post ) use ( $exclude ) {
					return ! in_array( $post->ID, $exclude, true );
				}
			)
		);

		return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
	}
}
