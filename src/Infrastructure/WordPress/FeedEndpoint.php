<?php
/**
 * The endpoint for the public JSON feed of a zone's posts.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

/**
 * Serves each zone's feed as JSON at /zones/{slug}/feed.json.
 *
 * It registers its own hooks in register_hooks(), and sites reach it with
 * zoninator_container()->get( FeedEndpoint::class ) to unhook them.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class FeedEndpoint {

	/**
	 * The feed's data.
	 *
	 * @var Feed
	 */
	private $feed;

	/**
	 * Constructor.
	 *
	 * @param Feed $feed The feed's data.
	 */
	public function __construct( Feed $feed ) {
		$this->feed = $feed;
	}

	/**
	 * Hooks the feed's rewrite rule and its response. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	public function add_rewrite_rule() {
		add_rewrite_tag( '%' . Taxonomy::NAME . '%', '([^&]+)' );
		add_rewrite_rule( '^zones/([^/]+)/feed.json/?$', 'index.php?' . Taxonomy::NAME . '=$matches[1]', 'top' );
	}

	public function serve() {
		$query_var = get_query_var( Taxonomy::NAME );

		if ( ! empty( $query_var ) ) {
			$zone_slug = get_query_var( Taxonomy::NAME );
			$results   = $this->feed->get( $zone_slug );
			if ( is_wp_error( $results ) ) {
				$this->send_error( $results->get_error_message() );
			}

			$this->send_json( $results );
		}
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
