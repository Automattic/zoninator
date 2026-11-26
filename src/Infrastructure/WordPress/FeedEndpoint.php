<?php
/**
 * The endpoint for the public JSON feed of a zone's posts.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

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
	 * Constructor.
	 *
	 * @param Feed $feed The feed's data.
	 */
	public function __construct( private readonly Feed $feed ) {
	}

	/**
	 * Hooks the feed's rewrite rule and its response. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_action( 'template_redirect', array( $this, 'serve' ) );
	}

	/**
	 * Adds the feed's rewrite rule and its query var. Hooked on init.
	 */
	public function add_rewrite_rule(): void {
		add_rewrite_tag( '%' . Taxonomy::NAME . '%', '([^&]+)' );
		add_rewrite_rule( '^zones/([^/]+)/feed.json/?$', 'index.php?' . Taxonomy::NAME . '=$matches[1]', 'top' );
	}

	/**
	 * Sends the feed, if this request is for one. Hooked on template_redirect.
	 */
	public function serve(): void {
		$zone_slug = get_query_var( Taxonomy::NAME );

		if ( empty( $zone_slug ) ) {
			return;
		}

		$results = $this->feed->get( $zone_slug );

		// No such zone, or a zoninator_json_feed_results callback that refused.
		if ( null === $results || is_wp_error( $results ) ) {
			status_header( 406 );
			wp_send_json( null );
		}

		wp_send_json( $results );
	}
}
