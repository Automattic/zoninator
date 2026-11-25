<?php
/**
 * Main Zoninator class
 */

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\Locks;
use Automattic\Zoninator\Infrastructure\WordPress\Request;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;

/**
 * Zoninator's public API, which Zoninator() returns: zones and their posts,
 * zone locks, the post types that support zones, and the settings.
 *
 * Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper builds it and the classes it delegates to.
 */
final class Zoninator {

	/**
	 * The zone taxonomy's name. Taxonomy owns it; this is its public name.
	 *
	 * @since 0.12.0
	 */
	public const ZONE_TAXONOMY = Taxonomy::NAME;

	/**
	 * Prefix of the post meta key that holds a post's position in a zone. The
	 * zone's term ID follows it. Zones owns it; this is its public name.
	 *
	 * @since 0.12.0
	 */
	public const ZONE_META_PREFIX = Zones::META_PREFIX;

	/**
	* The REST controller. PluginBootstrapper::register_rest_routes() sets it on rest_api_init.
	*
	* @var ZonesController|null
	*/
	public $rest_api;

	/**
	 * Zones and the posts in them. The zone data methods here delegate to it.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * The zone taxonomy. The post type support methods here delegate to it.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Zone locks. The lock methods here delegate to it.
	 *
	 * @var Locks
	 */
	private $locks;

	/**
	 * The JSON feed. get_zone_feed() delegates to it.
	 *
	 * @var Feed
	 */
	private $feed;

	/**
	 * The settings sites tune with filters, and the screen's messages.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Reads and checks requests. The verify_* methods here delegate to it.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Constructor. Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper builds the arguments.
	 *
	 * @param Settings     $settings     The settings sites tune with filters.
	 * @param Taxonomy     $taxonomy     The zone taxonomy.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Request      $request      Reads and checks requests.
	 * @param Zones        $zones        Zones and the posts in them.
	 * @param Locks        $locks        Zone locks.
	 * @param Feed         $feed         The JSON feed.
	 */
	public function __construct(
		Settings $settings,
		Taxonomy $taxonomy,
		Capabilities $capabilities,
		Request $request,
		Zones $zones,
		Locks $locks,
		Feed $feed
	) {
		$this->settings     = $settings;
		$this->taxonomy     = $taxonomy;
		$this->capabilities = $capabilities;
		$this->request      = $request;
		$this->zones        = $zones;
		$this->locks        = $locks;
		$this->feed         = $feed;
	}

	/**
	 * Number of seconds a lock is valid for.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function get_zone_lock_period() {
		return $this->settings->get_lock_period();
	}

	/**
	 * Max number of seconds for all locks in a session.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function get_zone_max_lock_period() {
		return $this->settings->get_max_lock_period();
	}

	/**
	 * Number of posts shown in zone searches and recent posts.
	 *
	 * @since 0.12.0
	 *
	 * @return int
	 */
	public function get_posts_per_page() {
		return $this->settings->get_posts_per_page();
	}

	/**
	 * Zone details saved in the zone term, with their default values.
	 *
	 * @since 0.12.0
	 *
	 * @return array
	 */
	public function get_zone_detail_defaults() {
		return $this->settings->get_detail_defaults();
	}

	/**
	 * Gets the label for a post's status, such as "Published", falling back to the status itself.
	 *
	 * @since 0.12.0
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function get_post_status_label( $post ) {
		return $this->zones->get_post_status_label( $post );
	}

	/**
	 * Gets the singular label for a post's type, such as "Post", falling back to the type itself.
	 *
	 * @since 0.12.0
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function get_post_type_label( $post ) {
		return $this->zones->get_post_type_label( $post );
	}

	public function get_supported_post_types() {
		return $this->taxonomy->get_post_types();
	}

	/**
	 * Registers a post type to be available for zones
	 *
	 * @param string|array $post_types A post type string or array of post type strings to register.
	 * @return bool True if any post types were added, false if not.
	 */
	public function register_zone_post_type( $post_types = '' ) {
		return $this->taxonomy->add_post_types( $post_types );
	}

	public function insert_zone( $slug, $name = '', $details = array() ) {
		return $this->zones->insert( $slug, $name, $details );
	}

	public function update_zone( $zone, $data = array() ) {
		return $this->zones->update( $zone, $data );
	}

	public function delete_zone( $zone ) {
		return $this->zones->delete( $zone );
	}

	/**
	 * @param $zone
	 * @param $posts
	 * @param bool  $append
	 * @return bool|WP_Error
	 */
	public function add_zone_posts( $zone, $posts, $append = false ) {
		return $this->zones->add_posts( $zone, $posts, $append );
	}

	/**
	 * @param mixed $zone
	 * @param mixed $posts
	 *
	 * @return bool|WP_Error
	 */
	public function remove_zone_posts( $zone, $posts = null ) {
		return $this->zones->remove_posts( $zone, $posts );
	}

	public function get_zone_posts( $zone, $args = array() ) {
		return $this->zones->get_posts( $zone, $args );
	}

	public function get_zone_query( $zone, $args = array() ) {
		return $this->zones->get_query( $zone, $args );
	}

	public function get_last_post_in_zone( $zone ) {
		return $this->zones->get_last_post( $zone );
	}

	public function get_first_post_in_zone( $zone ) {
		return $this->zones->get_first_post( $zone );
	}

	public function get_prev_post_in_zone( $zone, $post_id ) {
		return $this->zones->get_prev_post( $zone, $post_id );
	}

	public function get_next_post_in_zone( $zone, $post_id ) {
		return $this->zones->get_next_post( $zone, $post_id );
	}

	public function get_single_post_in_zone( $zone, $args = array() ) {
		return $this->zones->get_single_post( $zone, $args );
	}

	public function get_zones_for_post( $post_id ) {
		return $this->zones->get_for_post( $post_id );
	}

	public function get_zones( $args = array() ) {
		return $this->zones->get_all( $args );
	}

	public function get_zone( $zone ) {
		return $this->zones->get( $zone );
	}

	public function lock_zone( $zone, $user_id = 0 ) {
		return $this->locks->lock( $zone, $user_id );
	}

	// Not really needed with transients...
	public function unlock_zone( $zone ) {
		return $this->locks->unlock( $zone );
	}

	public function is_zone_locked( $zone ) {
		return $this->locks->is_locked( $zone );
	}

	public function zone_exists( $zone ) {
		return $this->zones->exists( $zone );
	}

	public function get_zone_id( $zone ) {
		return $this->zones->get_id( $zone );
	}

	public function get_zone_meta_key( $zone ) {
		return $this->zones->get_meta_key( $zone );
	}

	public function get_zone_slug( $zone ) {
		return $this->zones->get_slug( $zone );
	}

	public function get_post_id( $post ) {
		return $this->zones->get_post_id( $post );
	}

	/**
	 * Whether the current user may put a post in a zone.
	 *
	 * Request handlers take post IDs from the user, so they check here that
	 * each ID is a post of a zone post type that the user can read.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return bool
	 */
	public function current_user_can_add_post_to_zone( $post ) {
		return $this->capabilities->current_user_can_add_post_to_zone( $post );
	}

	public function get_post_order( $post, $zone ) {
		return $this->zones->get_post_order( $post, $zone );
	}

	public function verify_nonce( $action ) {
		return $this->request->verify_nonce( $action );
	}

	public function verify_access( $action = '', $zone_id = null ) {
		return $this->request->verify_access( $action, $zone_id );
	}

	/**
	 * @param $zone_slug_or_id
	 * @return array|WP_Error
	 */
	public function get_zone_feed( $zone_slug_or_id ) {
		return $this->feed->get( $zone_slug_or_id );
	}
}
