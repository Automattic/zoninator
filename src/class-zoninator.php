<?php
/**
 * Main Zoninator class
 */

/**
 * Main Zoninator class.
 */
class Zoninator {

	/**
	 * The zone taxonomy's name. Taxonomy owns it; this is its public name.
	 *
	 * @since 0.12.0
	 */
	public const ZONE_TAXONOMY = \Automattic\Zoninator\Taxonomy::NAME;

	/**
	 * Prefix of the post meta key that holds a post's position in a zone. The
	 * zone's term ID follows it. Zones owns it; this is its public name.
	 *
	 * @since 0.12.0
	 */
	public const ZONE_META_PREFIX = \Automattic\Zoninator\Zones::META_PREFIX;

	/**
	* The REST controller, set on rest_api_init.
	*
	* @var \Automattic\Zoninator\REST\Zones_Controller|null
	*/
	public $rest_api;

	/**
	* @var array|string[] Default post types that support zones.
	*/
	public $default_post_types = array( 'post' );

	/**
	 * Renders the Zones admin screen. The screen's public methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Admin\Screen
	 */
	private $admin_screen;

	/**
	 * Zones and the posts in them. The zone data methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Zones
	 */
	private $zones;

	/**
	 * The zone taxonomy. The post type support methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Taxonomy
	 */
	private $taxonomy;

	/**
	 * Zone locks. The lock methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Locks
	 */
	private $locks;

	/**
	 * The JSON feed. The feed methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Feed
	 */
	private $feed;

	/**
	 * The Zone Posts Query Loop variation. The zone block methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Block
	 */
	private $block;

	/**
	 * The settings sites tune with filters, and the screen's messages.
	 *
	 * @var \Automattic\Zoninator\Settings
	 */
	private $settings;

	/**
	 * The capability checks.
	 *
	 * @var \Automattic\Zoninator\Capabilities
	 */
	private $capabilities;

	/**
	 * The Abilities API abilities, built when the API first asks for them.
	 *
	 * @var \Automattic\Zoninator\Abilities|null
	 */
	private $abilities;

	/**
	 * Reads and checks requests. The verify_* methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Request
	 */
	private $request;

	/**
	 * Builds each part of the plugin and hands it what it needs. Nothing but
	 * this class depends on Zoninator, so the parts can be used and tested on
	 * their own.
	 */
	public function __construct() {
		$this->settings     = new \Automattic\Zoninator\Settings();
		$this->taxonomy     = new \Automattic\Zoninator\Taxonomy();
		$this->capabilities = new \Automattic\Zoninator\Capabilities( $this->taxonomy );
		$this->request      = new \Automattic\Zoninator\Request( $this->capabilities );
		$this->zones        = new \Automattic\Zoninator\Zones( $this->settings, $this->taxonomy, $this->capabilities );
		$this->locks        = new \Automattic\Zoninator\Locks( $this->zones, $this->settings );
		$this->feed         = new \Automattic\Zoninator\Feed( $this->zones );
		$this->block        = new \Automattic\Zoninator\Block( $this->zones );
		$this->admin_screen = new \Automattic\Zoninator\Admin\Screen(
			$this->zones,
			$this->locks,
			$this->settings,
			$this->capabilities,
			$this->request,
			// Sites unhook and replace these, so they stay pointed at Zoninator().
			array(
				'page'     => array( $this, 'admin_page' ),
				'position' => array( $this, 'admin_page_zone_post_col_position' ),
				'info'     => array( $this, 'admin_page_zone_post_col_info' ),
			)
		);

		add_action( 'init', array( $this, 'init' ), 99 ); // init later after other post types have been registered

		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		add_action( 'init', array( $this, 'add_zone_feed' ) );

		add_action( 'template_redirect', array( $this, 'do_zoninator_feeds' ) );

		add_action( 'split_shared_term', array( $this, 'split_shared_term' ), 10, 4 );

		add_filter( 'query_loop_block_query_vars', array( $this, 'zone_block_query_vars' ), 10, 2 );

		// The Zone Posts block's editor preview always reads /wp/v2/posts.
		add_filter( 'rest_post_query', array( $this, 'zone_block_rest_query' ), 10, 2 );

		add_action( 'enqueue_block_editor_assets', array( $this, 'zone_block_editor_assets' ) );

		$this->maybe_add_rest_api();

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
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

	public function maybe_add_rest_api() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Load the REST controller only when the REST API is in use, and register its routes.
	 *
	 * @since 0.12.0
	 */
	public function register_rest_routes() {
		$this->rest_api = $this->make_rest_controller();
		$this->rest_api->register_routes();
	}

	/**
	 * Builds the REST controller, loading its class first.
	 *
	 * @return \Automattic\Zoninator\REST\Zones_Controller
	 */
	private function make_rest_controller() {
		require_once __DIR__ . '/rest/class-zones-controller.php';

		return new \Automattic\Zoninator\REST\Zones_Controller(
			$this->zones,
			$this->taxonomy,
			$this->locks,
			$this->feed,
			$this->settings,
			$this->capabilities,
			$this->admin_screen
		);
	}

	/**
	 * Register the zone ability category.
	 *
	 * @since 1.0.0
	 */
	public function register_ability_categories() {
		$this->get_abilities()->register_category();
	}

	/**
	 * Register the zone abilities.
	 *
	 * @since 1.0.0
	 */
	public function register_abilities() {
		$this->get_abilities()->register();
	}

	/**
	 * Builds the abilities the first time they're needed, loading their class first.
	 *
	 * @return \Automattic\Zoninator\Abilities
	 */
	private function get_abilities() {
		if ( null === $this->abilities ) {
			require_once __DIR__ . '/class-abilities.php';

			$this->abilities = new \Automattic\Zoninator\Abilities(
				$this->zones,
				$this->capabilities,
				function () {
					return $this->rest_api ?? $this->make_rest_controller();
				}
			);
		}

		return $this->abilities;
	}

	public function add_zone_feed() {
		return $this->feed->add_rewrite_rule();
	}

	public function init() {
		$this->settings->init();

		do_action( 'zoninator_pre_init' );

		$this->taxonomy->register( $this->default_post_types );

		add_action( 'admin_init', array( $this, 'admin_controller' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'admin_menu', array( $this, 'admin_page_init' ) );

		// Add default advanced search fields
		add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_cat_filter' ) );
		add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_date_filter' ), 20 );

		do_action( 'zoninator_post_init' );
	}

	/**
	 * Turns a Zone Posts Query Loop block's query into the zone's query.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $query Query args core built from the block.
	 * @param WP_Block $block The Post Template or Query Pagination block inside the Query Loop.
	 * @return array
	 */
	public function zone_block_query_vars( $query, $block ) {
		return $this->block->filter_query_vars( $query, $block );
	}

	/**
	 * Turns the Zone Posts block's editor preview request into the zone's query.
	 *
	 * @since 1.0.0
	 *
	 * @param array           $args    Query args for the REST request.
	 * @param WP_REST_Request $request The REST request.
	 * @return array
	 */
	public function zone_block_rest_query( $args, $request ) {
		return $this->block->filter_rest_query( $args, $request );
	}

	/**
	 * Loads the Zone Posts block's editor script.
	 *
	 * @since 1.0.0
	 */
	public function zone_block_editor_assets() {
		return $this->block->enqueue_editor_assets();
	}

	public function widgets_init() {
		register_widget( 'Zoninator_ZonePosts_Widget' );
	}

	public function admin_init() {
		// Enqueue Scripts and Styles
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );
	}

	public function admin_page_init() {
		return $this->admin_screen->register_menu_page();
	}

	public function admin_enqueue_scripts() {
		return $this->admin_screen->enqueue_scripts();
	}

	public function admin_enqueue_styles() {
		return $this->admin_screen->enqueue_styles();
	}

	public function admin_controller() {
		return $this->admin_screen->handle_request();
	}

	public function admin_page() {
		return $this->admin_screen->render();
	}

	public function admin_page_zone_post_col_position( $post, $zone ) {
		return $this->admin_screen->render_position_column( $post, $zone );
	}

	public function admin_page_zone_post_col_info( $post, $zone ) {
		return $this->admin_screen->render_info_column( $post, $zone );
	}

	public function zone_advanced_search_cat_filter() {
		return $this->admin_screen->render_category_filter();
	}

	public function zone_advanced_search_date_filter() {
		return $this->admin_screen->render_date_filter();
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

	public function do_zoninator_feeds() {
		return $this->feed->serve();
	}

	// Handle 4.2 term-splitting
	public function split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
		return $this->zones->split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy );
	}

	/**
	 * @param $zone_slug_or_id
	 * @return array|WP_Error
	 */
	public function get_zone_feed( $zone_slug_or_id ) {
		return $this->feed->get( $zone_slug_or_id );
	}
}
