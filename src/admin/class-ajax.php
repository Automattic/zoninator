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

		wp_send_json(
			$this->search_controller()->find_posts(
				$this->zoninator->_get_request_var( 'term', '', 'stripslashes' ),
				$this->zoninator->_get_request_var( 'cat', '', 'absint' ),
				$this->zoninator->_get_request_var( 'date', '', 'striptags' ),
				(array) $this->zoninator->_get_request_var( 'exclude', array(), 'absint' ),
				absint( $this->zoninator->_get_request_var( 'limit', 0 ) )
			)
		);
	}

	/**
	 * Sends the latest content that isn't in the zone, as the options of the old Add Recent Content dropdown.
	 */
	public function recent_posts() {
		$zone_id = $this->zoninator->_get_post_var( 'zone_id', 0, 'absint' );
		$cat     = $this->zoninator->_get_post_var( 'cat', '', 'absint' );
		$date    = $this->zoninator->_get_post_var( 'date', '', 'striptags' );

		$this->zoninator->verify_nonce( self::NONCE_ACTION );
		$this->zoninator->verify_access( '', $zone_id );

		$content = '';
		foreach ( $this->find_recent_posts( $zone_id, $cat, $date ) as $post ) {
			$content .= $this->recent_post_option( $post );
		}

		if ( ! $content ) {
			$empty_label = __( 'No results found', 'zoninator' );
		} elseif ( $cat ) {
			/* translators: Category name */
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
	 * The REST search route does the work, so the old actions apply the same filters.
	 *
	 * @return \Automattic\Zoninator\REST\Zones_Controller
	 */
	private function search_controller() {
		require_once dirname( __DIR__ ) . '/rest/class-zones-controller.php';
		return new \Automattic\Zoninator\REST\Zones_Controller( $this->zoninator );
	}

	/**
	 * @param int        $zone_id Zone ID.
	 * @param int|string $cat     Category ID to filter by, if any.
	 * @param string     $date    Date to filter by, as Y-m-d, if any.
	 * @return array[] Search results, newest first.
	 */
	private function find_recent_posts( $zone_id, $cat = '', $date = '' ) {
		$zone_posts = $this->zoninator->get_zone_posts( $zone_id );
		$exclude    = is_wp_error( $zone_posts ) ? array() : wp_list_pluck( $zone_posts, 'ID' );

		return $this->search_controller()->find_posts( '', $cat, $date, $exclude );
	}

	/**
	 * @param array $post A search result.
	 * @return string The post as a dropdown option, escaped.
	 */
	private function recent_post_option( array $post ) {
		return sprintf( '<option value="%d">%s</option>', $post['post_id'], esc_html( $post['title'] . ' (' . $post['post_status'] . ')' ) );
	}
}
