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
	 * Handles the Zones screen's admin-ajax requests. The ajax_* methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Admin\Ajax
	 */
	private $admin_ajax;

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
	 * The settings sites tune with filters, and the screen's messages.
	 *
	 * @var \Automattic\Zoninator\Settings
	 */
	private $settings;

	/**
	 * The capability checks. The _current_user_can_* and _get_*_cap methods here delegate to it.
	 *
	 * @var \Automattic\Zoninator\Capabilities
	 */
	private $capabilities;

	/**
	 * Reads and checks requests. The _get_*_var and verify_* methods here delegate to it.
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
		$this->admin_ajax = new \Automattic\Zoninator\Admin\Ajax(
			$this->zones,
			$this->locks,
			$this->capabilities,
			$this->request,
			$this->admin_screen,
			function () {
				return $this->make_rest_controller();
			}
		);

		add_action( 'init', array( $this, 'init' ), 99 ); // init later after other post types have been registered

		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		add_action( 'init', array( $this, 'add_zone_feed' ) );

		add_action( 'template_redirect', array( $this, 'do_zoninator_feeds' ) );

		add_action( 'split_shared_term', array( $this, 'split_shared_term' ), 10, 4 );

		$this->maybe_add_rest_api();
	}

	/**
	 * Keeps the old public properties readable, with a notice, so sites can update their code.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( $name ) {
		$properties = $this->get_old_properties();

		if ( ! isset( $properties[ $name ] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- same warning PHP gives for an undefined property.
			trigger_error( sprintf( 'Undefined property: %s::$%s', __CLASS__, esc_html( $name ) ), E_USER_WARNING );
			return null;
		}

		_doing_it_wrong(
			esc_html( __CLASS__ . '::$' . $name ),
			/* translators: %s: Replacement constant or method. */
			sprintf( esc_html__( 'This property is no longer public. Use %s instead.', 'zoninator' ), esc_html( $properties[ $name ][1] ) ),
			'0.12.0'
		);

		return $properties[ $name ][0];
	}

	/**
	 * Keeps writes to the old public properties working, with a notice, until 1.0.0.
	 *
	 * A write to a property that is now private still takes effect. A write to
	 * one that is now a constant is ignored, as those values name the
	 * taxonomy, meta keys and nonces the stored data depends on.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value New value.
	 */
	public function __set( $name, $value ) {
		$properties = $this->get_old_properties();

		if ( ! isset( $properties[ $name ] ) ) {
			// Same as PHP without __set(): create the dynamic property.
			$this->$name = $value;
			return;
		}

		if ( 'post_types' === $name || in_array( $name, array( 'zone_detail_defaults', 'zone_lock_period', 'zone_max_lock_period', 'posts_per_page', 'zone_messages' ), true ) ) {
			if ( 'post_types' === $name ) {
				$this->taxonomy->set_post_types( $value );
			} else {
				$this->settings->set( $name, $value );
			}

			if ( isset( $properties[ $name ][2] ) ) {
				/* translators: %s: Filter or method to use instead. */
				$message = sprintf( esc_html__( 'This property is no longer public. The new value is used for now, but will be ignored from 1.0.0. Use %s instead.', 'zoninator' ), esc_html( $properties[ $name ][2] ) );
			} else {
				$message = esc_html__( 'This property is no longer public. The new value is used for now, but will be ignored from 1.0.0.', 'zoninator' );
			}
		} else {
			$message = esc_html__( 'This property is no longer public and the new value is ignored. This value can not be changed.', 'zoninator' );
		}

		_doing_it_wrong( esc_html( __CLASS__ . '::$' . $name ), $message, '0.12.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * @param string $name Property name.
	 * @return bool
	 */
	public function __isset( $name ) {
		$properties = $this->get_old_properties();
		return isset( $properties[ $name ] ) && null !== $properties[ $name ][0];
	}

	/**
	 * Old public property name => array( value, replacement to read it, way to change it or null ).
	 *
	 * The ones still backed by a private property keep honouring writes until 1.0.0.
	 *
	 * @return array
	 */
	private function get_old_properties() {
		return array(
			'key'                    => array( \Automattic\Zoninator\Admin\Screen::PAGE_SLUG, 'Automattic\\Zoninator\\Admin\\Screen::PAGE_SLUG', null ),
			'zone_taxonomy'          => array( self::ZONE_TAXONOMY, 'Zoninator::ZONE_TAXONOMY', null ),
			'zone_term_prefix'       => array( 'zone-', 'your own value (Zoninator never used it)', null ),
			'zone_meta_prefix'       => array( self::ZONE_META_PREFIX, 'Zoninator::ZONE_META_PREFIX', null ),
			'zone_nonce_prefix'      => array( \Automattic\Zoninator\Request::NONCE_PREFIX, 'Zoninator::_get_nonce_key()', null ),
			'zone_ajax_nonce_action' => array( \Automattic\Zoninator\Admin\Ajax::NONCE_ACTION, 'Automattic\\Zoninator\\Admin\\Ajax::NONCE_ACTION', null ),
			'zone_detail_defaults'   => array( $this->settings->get( 'zone_detail_defaults' ), 'Zoninator::get_zone_detail_defaults()', 'the zoninator_zone_detail_defaults filter' ),
			'zone_lock_period'       => array( $this->settings->get( 'zone_lock_period' ), 'Zoninator::get_zone_lock_period()', 'the zoninator_zone_lock_period filter' ),
			'zone_max_lock_period'   => array( $this->settings->get( 'zone_max_lock_period' ), 'Zoninator::get_zone_max_lock_period()', 'the zoninator_zone_max_lock_period filter' ),
			'posts_per_page'         => array( $this->settings->get( 'posts_per_page' ), 'Zoninator::get_posts_per_page()', 'the zoninator_posts_per_page filter' ),
			'post_types'             => array( $this->taxonomy->get_post_types(), 'Zoninator::get_supported_post_types()', 'Zoninator::register_zone_post_type()' ),
			'zone_messages'          => array( $this->settings->get( 'zone_messages' ), 'Zoninator::_get_message()', null ),
		);
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

	public function widgets_init() {
		register_widget( 'Zoninator_ZonePosts_Widget' );
	}

	// Add necessary AJAX actions
	public function admin_ajax_init() {
		add_action( 'wp_ajax_zoninator_reorder_posts', array( $this, 'ajax_reorder_posts' ) );
		add_action( 'wp_ajax_zoninator_add_post', array( $this, 'ajax_add_post' ) );
		add_action( 'wp_ajax_zoninator_remove_post', array( $this, 'ajax_remove_post' ) );
		add_action( 'wp_ajax_zoninator_search_posts', array( $this, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_zoninator_update_lock', array( $this, 'ajax_update_lock' ) );
		add_action( 'wp_ajax_zoninator_update_recent', array( $this, 'ajax_recent_posts' ) );
	}

	public function admin_init() {

		$this->admin_ajax_init();

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

	public function admin_page_zone_tabs( $zones, $active_zone_id = 0 ) {
		return $this->admin_screen->render_zone_tabs( $zones, $active_zone_id );
	}

	public function admin_page_zone_edit( $zone = null ) {
		return $this->admin_screen->render_zone_edit( $zone );
	}

	/**
	 * Renders the zone's name and description, as a form or read only.
	 *
	 * @since 0.12.0
	 *
	 * @param WP_Term|array $zone        The zone, or an empty array for a new one.
	 * @param int|false     $zone_locked ID of the user holding the zone's lock, or false.
	 * @param string        $delete_link URL that deletes the zone.
	 */
	public function admin_page_zone_details( $zone, $zone_locked, $delete_link ) {
		return $this->admin_screen->render_zone_details( $zone, $zone_locked, $delete_link );
	}

	/**
	 * Renders the box for finding, adding and ordering a zone's content.
	 *
	 * @since 0.12.0
	 *
	 * @param WP_Term   $zone        The zone.
	 * @param WP_Post[] $zone_posts  Posts in the zone, in order.
	 * @param int|false $zone_locked ID of the user holding the zone's lock, or false.
	 */
	public function admin_page_zone_content( $zone, $zone_posts, $zone_locked ) {
		return $this->admin_screen->render_zone_content( $zone, $zone_posts, $zone_locked );
	}

	public function admin_page_zone_post( $post, $zone ) {
		return $this->admin_screen->render_zone_post( $post, $zone );
	}

	public function admin_page_zone_post_col_position( $post, $zone ) {
		return $this->admin_screen->render_position_column( $post, $zone );
	}

	public function admin_page_zone_post_col_info( $post, $zone ) {
		return $this->admin_screen->render_info_column( $post, $zone );
	}

	public function zone_advanced_search_filters() {
		return $this->admin_screen->render_search_filters();
	}

	public function zone_advanced_search_cat_filter() {
		return $this->admin_screen->render_category_filter();
	}

	public function zone_advanced_search_date_filter() {
		return $this->admin_screen->render_date_filter();
	}

	public function zone_admin_search_form() {
		return $this->admin_screen->render_search_form();
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

	public function is_zoninator_page() {
		return $this->admin_screen->is_current();
	}

	/**
	 * Sends a response from a Zones screen admin-ajax handler.
	 *
	 * @deprecated 0.12.0 The Zones screen uses the zoninator/v1 REST API. This goes in 1.0.0.
	 */
	public function ajax_return( $status, $content = '', $action = '' ) {
		_deprecated_function( __METHOD__, '0.12.0' );
		return $this->admin_ajax->respond( $status, $content, $action );
	}

	/**
	 * Handles the zoninator_add_post admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen uses POST /zoninator/v1/zones/{zone_id}/posts/{post_id}. The action goes in 1.0.0.
	 */
	public function ajax_add_post() {
		_deprecated_function( __METHOD__, '0.12.0', 'POST /zoninator/v1/zones/{zone_id}/posts/{post_id}' );
		return $this->admin_ajax->add_post();
	}

	/**
	 * Handles the zoninator_remove_post admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen uses DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}. The action goes in 1.0.0.
	 */
	public function ajax_remove_post() {
		_deprecated_function( __METHOD__, '0.12.0', 'DELETE /zoninator/v1/zones/{zone_id}/posts/{post_id}' );
		return $this->admin_ajax->remove_post();
	}

	/**
	 * Handles the zoninator_reorder_posts admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen uses PUT /zoninator/v1/zones/{zone_id}/posts. The action goes in 1.0.0.
	 */
	public function ajax_reorder_posts() {
		_deprecated_function( __METHOD__, '0.12.0', 'PUT /zoninator/v1/zones/{zone_id}/posts' );
		return $this->admin_ajax->reorder_posts();
	}

	/**
	 * Never used by the Zones screen.
	 *
	 * @deprecated 0.12.0 Use Zoninator::add_zone_posts() and Zoninator::remove_zone_posts(). This goes in 1.0.0.
	 */
	public function ajax_move_zone_post( $from_zone, $to_zone, $post_id ) {
		_deprecated_function( __METHOD__, '0.12.0', 'Zoninator::add_zone_posts() and Zoninator::remove_zone_posts()' );
		return $this->admin_ajax->move_zone_post( $from_zone, $to_zone, $post_id );
	}

	/**
	 * Handles the zoninator_search_posts admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen uses GET /zoninator/v1/search. The action goes in 1.0.0.
	 */
	public function ajax_search_posts() {
		_deprecated_function( __METHOD__, '0.12.0', 'GET /zoninator/v1/search' );
		return $this->admin_ajax->search_posts();
	}

	/**
	 * Handles the zoninator_update_lock admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen uses PUT /zoninator/v1/zones/{zone_id}/lock. The action goes in 1.0.0.
	 */
	public function ajax_update_lock() {
		_deprecated_function( __METHOD__, '0.12.0', 'PUT /zoninator/v1/zones/{zone_id}/lock' );
		return $this->admin_ajax->update_lock();
	}

	/**
	 * Handles the zoninator_update_recent admin-ajax action.
	 *
	 * @deprecated 0.12.0 The Zones screen lists the latest content with GET /zoninator/v1/search and no term. The action goes in 1.0.0.
	 */
	public function ajax_recent_posts() {
		_deprecated_function( __METHOD__, '0.12.0', 'GET /zoninator/v1/search' );
		return $this->admin_ajax->recent_posts();
	}

	/**
	 * Prints a dropdown of the latest content that isn't in the zone.
	 *
	 * @deprecated 0.12.0 The Zones screen lists the latest content with GET /zoninator/v1/search and no term. This goes in 1.0.0.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function zone_admin_recent_posts_dropdown( $zone_id ) {
		_deprecated_function( __METHOD__, '0.12.0', 'GET /zoninator/v1/search' );
		$this->admin_ajax->recent_posts_dropdown( $zone_id );
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

	public function get_formatted_zone_slug( $slug ) {
		return $slug; // legacy function -- slugs can no longer be changed
	}

	public function get_unformatted_zone_slug( $slug ) {
		return $slug; // legacy function -- slugs can no longer be changed
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

	public function _unauthorized_access() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->request->unauthorized_access();
	}

	public function _fill_zone_details( $zone ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->zones->fill_details( $zone );
	}

	public function do_zoninator_feeds() {
		return $this->feed->serve();
	}

	/*
	 * No plugin level cache for zone posts. Since WP 6.1, WP_Query caches the
	 * zone query in the `post-queries` group, and any post meta change (add,
	 * remove, reorder) clears it. These no-op methods stay for back-compat.
	 */
	public function get_zone_cache_key( $zone, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- potentially breaking change/
		return '';
	}

	public function get_zone_posts_from_cache( $zone, $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- potentially breaking change/
		return false;
	}

	public function add_zone_posts_to_cache( $posts, $zone, $args = array() ) {
	}

	// Handle 4.2 term-splitting
	public function split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
		return $this->zones->split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy );
	}

	public function _empty_zone_posts_cache( $meta_key ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		// No-op, see the note above get_zone_cache_key().
	}

	public function _get_message( $message_id, $encode = false ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->settings->get_message( $message_id, $encode );
	}

	public function _get_nonce_key( $action ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return \Automattic\Zoninator\Request::get_nonce_key( $action );
	}

	public function _current_user_can_add_zones() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->current_user_can_add_zones();
	}

	public function _current_user_can_edit_zones( $zone_id ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->current_user_can_edit_zones( $zone_id );
	}

	public function _current_user_can_manage_zones() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->current_user_can_manage_zones();
	}

	public function _get_add_zones_cap() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->get_add_zones_cap();
	}

	public function _get_edit_zones_cap() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->get_edit_zones_cap();
	}

	public function _get_manage_zones_cap() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->capabilities->get_manage_zones_cap();
	}

	public function _get_zone_page_url( $args = array() ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->admin_screen->get_page_url( $args );
	}

	public function _validate_date_filter( $date ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return \Automattic\Zoninator\Request::is_valid_date_filter( $date );
	}

	public function _validate_category_filter( $cat ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return \Automattic\Zoninator\Request::is_valid_category_filter( $cat );
	}

	public function _sanitize_value( $var ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.varFound, PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return \Automattic\Zoninator\Request::sanitize_value( $var );
	}

	public function _get_value_or_default( $var, $object, $default = '', $sanitize_callback = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound, Universal.NamingConventions.NoReservedKeywordParameterNames.varFound, PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return \Automattic\Zoninator\Request::get_value_or_default( $var, $object, $default, $sanitize_callback );
	}

	public function _get_request_var( $var, $default = '', $sanitize_callback = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Universal.NamingConventions.NoReservedKeywordParameterNames.varFound, PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->request->get_request_var( $var, $default, $sanitize_callback );
	}

	public function _get_get_var( $var, $default = '', $sanitize_callback = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Universal.NamingConventions.NoReservedKeywordParameterNames.varFound, PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->request->get_get_var( $var, $default, $sanitize_callback );
	}

	public function _get_post_var( $var, $default = '', $sanitize_callback = '' ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Universal.NamingConventions.NoReservedKeywordParameterNames.varFound, PSR2.Methods.MethodDeclaration.Underscore -- breaking change
		return $this->request->get_post_var( $var, $default, $sanitize_callback );
	}

	public function get_admin_zone_post( $post, $zone ) {
		return apply_filters(
			'zoninator_zone_post_columns',
			array(
				'post_id'  => $post->ID,
				'position' => array(
					'current_position'        => intval( $this->get_post_order( $post->ID, $zone ) ),
					'change_position_message' => esc_attr__( 'Drag to change the position of this item.', 'zoninator' ),
					'key'                     => 'position',
				),
				'info'     => array(
					'key'              => 'info',
					'post'             => array(
						'post_title'  => esc_html( $post->post_title ),
						'post_status' => esc_html( $post->post_status ),
					),
					'action_link_data' => array(
						array(
							'action' => 'edit',
							'anchor' => get_edit_post_link( $post->ID ),
							'title'  => __( 'Opens in a new tab', 'zoninator' ),
							'text'   => __( 'Edit', 'zoninator' ),
							'target' => '_blank',
						),
						array(
							'action' => 'delete',
							'anchor' => '#',
							'title'  => '',
							'text'   => __( 'Remove', 'zoninator' ),
						),
						array(
							'action' => 'view',
							'anchor' => get_permalink( $post->ID ),
							'title'  => __( 'Opens in a new tab', 'zoninator' ),
							'text'   => __( 'View', 'zoninator' ),
							'target' => '_blank',
						),
					),
				),
			),
			$post,
			$zone
		);
	}

	public function get_admin_zone_posts( $zone_or_id ) {
		$zone             = $this->get_zone( $zone_or_id );
		$posts            = $this->get_zone_posts( $zone );
		$admin_zone_posts = array();

		foreach ( $posts as $post ) {
			$admin_zone_posts[] = $this->get_admin_zone_post( $post, $zone );
		}

		return $admin_zone_posts;
	}

	/**
	 * @param $zone_slug_or_id
	 * @return array|WP_Error
	 */
	public function get_zone_feed( $zone_slug_or_id ) {
		return $this->feed->get( $zone_slug_or_id );
	}

	public function check( $action = '', $zone_id = null ) {
		return $this->capabilities->check( $action, $zone_id );
	}
}
