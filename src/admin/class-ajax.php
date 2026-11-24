<?php
/**
 * The admin-ajax handlers behind the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Admin;

use Automattic\Zoninator\Capabilities;
use Automattic\Zoninator\Locks;
use Automattic\Zoninator\Request;
use Automattic\Zoninator\Zones;

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
 *
 * @since 0.12.0
 */
final class Ajax {

	/**
	 * The nonce action every handler checks, passed through Zoninator::_get_nonce_key().
	 */
	public const NONCE_ACTION = 'ajax-action';

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Zone locks.
	 *
	 * @var Locks
	 */
	private $locks;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Reads and checks requests.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * The Zones screen, which renders a zone post's row.
	 *
	 * @var Screen
	 */
	private $screen;

	/**
	 * Returns the REST controller, whose search the old search action uses.
	 *
	 * @var callable
	 */
	private $search_controller;

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 * @param Locks $locks Zone locks.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Request $request Reads and checks requests.
	 * @param Screen $screen The Zones screen, which renders a zone post's row.
	 * @param callable $search_controller Returns the REST controller, whose search the old search action uses.
	 */
	public function __construct( Zones $zones, Locks $locks, Capabilities $capabilities, Request $request, Screen $screen, callable $search_controller ) {
		$this->zones             = $zones;
		$this->locks             = $locks;
		$this->capabilities      = $capabilities;
		$this->request           = $request;
		$this->screen            = $screen;
		$this->search_controller = $search_controller;
	}

	public function respond( $status, $content = '', $action = '' ) {
		$action = empty( $action ) ? self::NONCE_ACTION : $action;
		$nonce  = wp_create_nonce( Request::get_nonce_key( $action ) );

		wp_send_json(
			array(
				'status'  => $status,
				'content' => $content,
				'nonce'   => $nonce,
			)
		);
	}

	public function add_post() {
		$zone_id = $this->request->get_post_var( 'zone_id', 0, 'absint' );
		$post_id = $this->request->get_post_var( 'post_id', 0, 'absint' );

		// Verify nonce
		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access( '', $zone_id );

		// Validate
		if ( ! $zone_id || ! $this->capabilities->current_user_can_add_post_to_zone( $post_id ) ) {
			$this->respond( 0 );
		}

		$result = $this->zones->add_posts( $zone_id, $post_id, true );

		if ( is_wp_error( $result ) ) {
			$status  = 0;
			$content = $result->get_error_message();
		} else {
			$post = get_post( $post_id );
			$zone = $this->zones->get( $zone_id );

			ob_start();
			$this->screen->render_zone_post( $post, $zone );
			$content = ob_get_contents();
			ob_end_clean();

			$status = 1;
		}

		$this->respond( $status, $content );
	}

	public function remove_post() {
		$zone_id = $this->request->get_post_var( 'zone_id', 0, 'absint' );
		$post_id = $this->request->get_post_var( 'post_id', 0, 'absint' );

		// Verify nonce
		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access( '', $zone_id );

		// Validate
		if ( ! $zone_id || ! $post_id ) {
			$this->respond( 0 );
		}

		$result = $this->zones->remove_posts( $zone_id, $post_id );

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
		$zone_id  = $this->request->get_post_var( 'zone_id', 0, 'absint' );
		$post_ids = (array) $this->request->get_post_var( 'posts', array(), 'absint' );

		// Verify nonce
		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access( '', $zone_id );

		// validate
		if ( ! $zone_id || array() === $post_ids || count( $post_ids ) !== count( array_filter( $post_ids, array( $this->capabilities, 'current_user_can_add_post_to_zone' ) ) ) ) {
			$this->respond( 0 );
		}

		$result = $this->zones->add_posts( $zone_id, $post_ids, false );

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
		$from_zone_id = $this->request->get_post_var( 'from_zone_id', 0, 'absint' );
		$to_zone_id   = $this->request->get_post_var( 'to_zone_id', 0, 'absint' );

		$this->request->verify_nonce( self::NONCE_ACTION );

		// Add to new zone
		$this->zones->add_posts( $to_zone_id, $post_id, true );

		// Remove from old zone
		$this->zones->remove_posts( $from_zone_id, $post_id );
	}

	public function search_posts() {

		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access();

		// The REST search route does the work, so both apply the same filters.
		wp_send_json(
			( $this->search_controller )()->find_posts(
				$this->request->get_request_var( 'term', '', 'stripslashes' ),
				$this->request->get_request_var( 'cat', '', 'absint' ),
				$this->request->get_request_var( 'date', '', 'striptags' ),
				(array) $this->request->get_request_var( 'exclude', array(), 'absint' ),
				absint( $this->request->get_request_var( 'limit', 0 ) ),
				absint( $this->request->get_request_var( 'zone_id', 0 ) )
			)
		);
	}

	/**
	 * Sends the latest content that isn't in the zone, as the options of the old Add Recent Content dropdown.
	 */
	public function recent_posts() {
		$zone_id = $this->request->get_post_var( 'zone_id', 0, 'absint' );
		$cat     = $this->request->get_post_var( 'cat', '', 'absint' );
		$date    = $this->request->get_post_var( 'date', '', 'striptags' );

		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access( '', $zone_id );

		$content = '';
		foreach ( $this->find_recent_posts( $zone_id, $cat, $date ) as $post ) {
			$content .= $this->recent_post_option( $post );
		}

		if ( ! $content ) {
			$empty_label = __( 'No results found', 'zoninator' );
		} elseif ( $cat ) {
			/* translators: %s: category name. */
			$empty_label = sprintf( __( 'Choose post from %s', 'zoninator' ), get_the_category_by_ID( $cat ) );
		} else {
			$empty_label = __( 'Choose a post', 'zoninator' );
		}

		$this->respond( 1, '<option value="">' . esc_html( $empty_label ) . '</option>' . $content );
	}

	/**
	 * Prints the old Add Recent Content dropdown: the latest content that isn't in the zone.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function recent_posts_dropdown( $zone_id ) {
		?>
	<div class="zone-search-wrapper">
		<label for="zone-post-latest"><?php esc_html_e( 'Add Recent Content', 'zoninator' ); ?></label><br />
		<select name="search-posts" id="zone-post-latest">
			<option value=""><?php esc_html_e( 'Choose a post', 'zoninator' ); ?></option>
			<?php
			foreach ( $this->find_recent_posts( $zone_id ) as $post ) {
				echo $this->recent_post_option( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- recent_post_option() escapes it.
			}
			?>
		</select>
	</div>
		<?php
	}

	public function update_lock() {
		$zone_id = $this->request->get_post_var( 'zone_id', 0, 'absint' );

		$this->request->verify_nonce( self::NONCE_ACTION );
		$this->request->verify_access( '', $zone_id );

		if ( ! $zone_id ) {
			exit;
		}

		if ( ! $this->locks->is_locked( $zone_id ) ) {
			$this->locks->lock( $zone_id );
			$this->respond( 1, '' );
		}
	}


	/**
	 * @param int        $zone_id Zone ID.
	 * @param int|string $cat     Category ID to filter by, if any.
	 * @param string     $date    Date to filter by, as Y-m-d, if any.
	 * @return array[] Search results, newest first.
	 */
	private function find_recent_posts( $zone_id, $cat = '', $date = '' ) {
		$zone_posts = $this->zones->get_posts( $zone_id );
		$exclude    = is_wp_error( $zone_posts ) ? array() : wp_list_pluck( $zone_posts, 'ID' );

		return ( $this->search_controller )()->find_posts( '', $cat, $date, $exclude, 0, absint( $zone_id ) );
	}

	/**
	 * @param array $post A search result.
	 * @return string The post as a dropdown option, escaped.
	 */
	private function recent_post_option( array $post ) {
		return sprintf( '<option value="%d">%s</option>', $post['post_id'], esc_html( $post['title'] . ' (' . $post['post_status'] . ')' ) );
	}
}
