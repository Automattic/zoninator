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
 *
 * Since 0.12.0 the screen uses the zoninator/v1 REST API instead, so every
 * action here is deprecated, and this class goes in 1.0.0.
 */
final class Ajax {

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

	public function move_zone_post( $from_zone, $to_zone, $post_id ) {
		$from_zone_id = $this->zoninator->_get_post_var( 'from_zone_id', 0, 'absint' );
		$to_zone_id   = $this->zoninator->_get_post_var( 'to_zone_id', 0, 'absint' );

		$this->zoninator->verify_nonce( self::NONCE_ACTION );

		// Add to new zone
		$this->zoninator->add_zone_posts( $to_zone_id, $post_id, true );

		// Remove from old zone
		$this->zoninator->remove_zone_posts( $from_zone_id, $post_id );
	}

	public function search_posts() {

		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access();

		// The REST search route does the work, so both apply the same filters.
		require_once dirname( __DIR__ ) . '/rest/class-zones-controller.php';
		$controller = new \Automattic\Zoninator\REST\Zones_Controller( $this->zoninator );

		wp_send_json(
			$controller->find_posts(
				$this->zoninator->_get_request_var( 'term', '', 'stripslashes' ),
				$this->zoninator->_get_request_var( 'cat', '', 'absint' ),
				$this->zoninator->_get_request_var( 'date', '', 'striptags' ),
				(array) $this->zoninator->_get_request_var( 'exclude', array(), 'absint' ),
				absint( $this->zoninator->_get_request_var( 'limit', 0 ) )
			)
		);
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
}
