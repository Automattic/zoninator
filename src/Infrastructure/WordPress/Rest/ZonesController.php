<?php
/**
 * REST controller for the zoninator/v1 namespace.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\Search;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use Throwable;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Zones, their posts and their locks.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZonesController extends WP_REST_Controller {
	public const ZONE_ITEM_URL_REGEX = '/zones/(?P<zone_id>[\d]+)';

	public const ZONE_ITEM_POSTS_URL_REGEX = '/zones/(?P<zone_id>[\d]+)/posts';

	public const ZONE_ITEM_POSTS_POST_REGEX = '/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)';

	public const INVALID_POST_ID = 'invalid-post-id';

	public const ZONE_ID_REQUIRED = 'zone-id-required';

	public const ZONE_FEED_ERROR = 'zone-feed-error';

	public const ZONES_READ_ERROR = 'zones-read-error';

	public const PERMISSION_DENIED = 'permission-denied';

	public const ZONE_NOT_FOUND = 'zone-not-found';

	public const ZONE_LOCKED = 'zone-locked';

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Finds posts to offer for a zone.
	 *
	 * @var Search
	 */
	private $search;

	/**
	 * Creates, changes and deletes zones for the current user.
	 *
	 * @var GuardedZoneManager
	 */
	private $manager;

	/**
	 * Changes the posts in a zone for the current user.
	 *
	 * @var GuardedZoneCurator
	 */
	private $curator;

	/**
	 * Zone locks.
	 *
	 * @var ZoneLocker
	 */
	private $locker;

	/**
	 * The JSON feed.
	 *
	 * @var Feed
	 */
	private $feed;

	/**
	 * Zoninator's settings.
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
	 * The Zones screen, which renders a zone post's row for the screen's requests.
	 *
	 * @var Screen
	 */
	private $screen;

	/**
	 * Key Value Translation array
	 *
	 * @var array
	 */
	private $translations;

	/**
	 * Constructor.
	 *
	 * @param Zones        $zones        Zones and the posts in them.
	 * @param ZoneLocker   $locker       Zone locks.
	 * @param Feed         $feed         The JSON feed.
	 * @param Settings     $settings     Zoninator's settings.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Screen       $screen       The Zones screen.
	 * @param Search             $search       Finds posts to offer for a zone.
	 * @param GuardedZoneManager $manager      Creates, changes and deletes zones for the current user.
	 * @param GuardedZoneCurator $curator      Changes the posts in a zone for the current user.
	 */
	public function __construct( Zones $zones, ZoneLocker $locker, Feed $feed, Settings $settings, Capabilities $capabilities, Screen $screen, Search $search, GuardedZoneManager $manager, GuardedZoneCurator $curator ) {
		$this->zones        = $zones;
		$this->locker       = $locker;
		$this->feed         = $feed;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->screen       = $screen;
		$this->search       = $search;
		$this->manager      = $manager;
		$this->curator      = $curator;
		$this->namespace    = 'zoninator/v1';
	}

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		$this->translations = array(
			self::ZONE_NOT_FOUND  => __( 'Zone not found.', 'zoninator' ),
			self::INVALID_POST_ID => __( 'Invalid post ID.', 'zoninator' ),
		);

		register_rest_route(
			$this->namespace,
			'/zones',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_zones' ),
					'permission_callback' => array( $this, 'get_zones_permissions_check' ),
					'args'                => array(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_zone' ),
					'permission_callback' => array( $this, 'add_zone_permissions_check' ),
					'args'                => $this->_params_for_create_zone(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/zones/(?P<zone_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_zone' ),
					'permission_callback' => array( $this, 'update_zone_permissions_check' ),
					'args'                => $this->_params_for_update_zone(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_zone' ),
					'permission_callback' => array( $this, 'update_zone_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/zones/(?P<zone_id>[\d]+)/posts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_zone_posts' ),
					'permission_callback' => array( $this, 'get_zone_posts_permissions_check' ),
					'args'                => $this->_get_zone_id_param(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_zone_posts' ),
					'permission_callback' => array( $this, 'manage_zone_permissions_check' ),
					'args'                => $this->_get_zone_post_rest_route_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			self::ZONE_ITEM_POSTS_POST_REGEX,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_zone_post' ),
					'permission_callback' => array( $this, 'manage_zone_permissions_check' ),
					'args'                => $this->get_zone_post_item_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_zone_post' ),
					'permission_callback' => array( $this, 'manage_zone_permissions_check' ),
					'args'                => $this->get_zone_post_item_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'search_posts' ),
					'permission_callback' => array( $this, 'manage_zone_permissions_check' ),
					'args'                => $this->get_search_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/zones/(?P<zone_id>[\d]+)/lock',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'zone_update_lock' ),
					'permission_callback' => array( $this, 'manage_zone_permissions_check' ),
					'args'                => $this->_get_zone_id_param(),
				),
			)
		);
	}

	/**
	 * Get the list of all zones
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_zones( $request ) {
		$results = $this->zones->get_all();

		// Zones::get_all() returns false, not a WP_Error, when the zones can't be read.
		if ( ! is_array( $results ) ) {
			return $this->bad_request( self::ZONES_READ_ERROR, __( 'Zones could not be read.', 'zoninator' ) );
		}

		$zones = array_map( array( $this, '_filter_zone_properties' ), $results );

		return $this->ok( $zones );
	}

	/**
	 * Create a Zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_zone( $request ) {
		$name        = $this->get_param( $request, 'name', '' );
		$slug        = $this->get_param( $request, 'slug', $name );
		$description = $this->get_param( $request, 'description', '' );

		try {
			$zone = $this->manager->create( get_current_user_id(), (string) $slug, (string) $name, array( 'description' => $description ) );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, 0 );
		}

		return $this->created( $this->_filter_zone_properties( $this->zones->get( (int) $zone->get_id() ) ) );
	}

	/**
	 * Update zone details
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );
		$name    = $this->get_param( $request, 'name', '' );
		$slug    = $this->get_param( $request, 'slug', '' );

		$zone          = $this->zones->get( $zone_id );
		$update_params = array();

		if ( ! $zone ) {
			return $this->zone_not_found();
		}

		if ( $name ) {
			$update_params['name'] = $name;
		}

		if ( $slug ) {
			$update_params['slug'] = $slug;
		}

		// An empty description clears it. Leave it out to keep the current one.
		if ( $request->has_param( 'description' ) ) {
			$update_params['details'] = array( 'description' => (string) $request->get_param( 'description' ) );
		}

		try {
			$this->manager->update( get_current_user_id(), $zone_id, $update_params );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Delete a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_zone( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );

		$zone = $this->zones->get( $zone_id );

		if ( ! $zone ) {
			return $this->zone_not_found();
		}

		try {
			$this->manager->remove( get_current_user_id(), $zone_id );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Get zone posts
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_zone_posts( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );

		if ( empty( $zone_id ) || false === $this->zones->get( $zone_id ) ) {
			return $this->zone_not_found();
		}

		$results = $this->feed->get( $zone_id );

		if ( is_wp_error( $results ) ) {
			return $this->bad_request( self::ZONE_FEED_ERROR, $results->get_error_message() );
		}

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Sets the posts for a zone
	 *
	 * @param WP_REST_Request $request Full data about the request.]
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_zone_posts( $request ) {
		$zone_id  = $this->get_param( $request, 'zone_id', 0, 'absint' );
		$post_ids = $this->get_param( $request, 'post_ids', array() );

		if ( ! $this->zones->get( $zone_id ) ) {
			return $this->zone_not_found();
		}

		try {
			$this->curator->set_posts( get_current_user_id(), $zone_id, array_map( 'absint', (array) $post_ids ) );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Add a post to the end of a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response 201 with the post's row for the Zones screen, as HTML.
	 */
	public function add_zone_post( $request ) {
		$zone    = $this->zones->get( (int) $request['zone_id'] );
		$post_id = (int) $request['post_id'];

		if ( ! $zone ) {
			return $this->zone_not_found();
		}

		try {
			$this->curator->add_posts( get_current_user_id(), (int) $zone->term_id, array( $post_id ) );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, (int) $zone->term_id );
		}

		// Render the row with the zoninator_zone_post_columns callbacks, so columns sites add still show.
		ob_start();
		$this->screen->render_zone_post( get_post( $post_id ), $zone );

		return $this->created( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Remove a post from a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function remove_zone_post( $request ) {
		$zone    = $this->zones->get( (int) $request['zone_id'] );
		$post_id = (int) $request['post_id'];

		if ( ! $zone ) {
			return $this->zone_not_found();
		}

		try {
			$this->curator->remove_posts( get_current_user_id(), (int) $zone->term_id, array( $post_id ) );
		} catch ( Throwable $exception ) {
			return $this->error_response( $exception, (int) $zone->term_id );
		}

		return $this->ok( array( 'success' => true ) );
	}

	/**
	 * Search for posts to add to a zone, or list the latest content when there's no term.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public function search_posts( $request ) {
		return $this->ok(
			$this->search->find_posts(
				$request['term'],
				$request['cat'],
				$request['date'],
				$request['exclude'],
				(int) $request['limit'],
				(int) $request['zone_id']
			)
		);
	}

	/**
	 * Update the zone's lock
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function zone_update_lock( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );
		if ( ! $zone_id ) {
			return $this->bad_request( self::ZONE_ID_REQUIRED, __( 'Zone ID is required.', 'zoninator' ) );
		}

		$zone = $this->zones->get( $zone_id );
		if ( ! $zone ) {
			return $this->zone_not_found();
		}

		try {
			$this->locker->lock( $zone_id, get_current_user_id() );
		} catch ( ZoneLockedException $exception ) {
			return $this->error_response( $exception, $zone_id );
		}

		return new WP_REST_Response(
			array(
				'zone_id'         => $this->zones->get_id( $zone ),
				'timeout'         => $this->settings->get_lock_period(),
				'max_lock_period' => $this->settings->get_max_lock_period(),
			),
			200
		);
	}

	/**
	 * Check if a given request has access to the zones index.
	 *
	 * Defaults to requiring a logged-in user to avoid leaking zone names and
	 * descriptions to anonymous callers. Sites that integrated against the
	 * historical unauthenticated behaviour can restore it via the
	 * zoninator_rest_get_zones_permissions_check filter.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function get_zones_permissions_check( $request ) {
		/**
		 * Filter whether the GET /zones REST endpoint authorises the request.
		 *
		 * @param bool            $authorized Whether the caller may list zones.
		 * @param WP_REST_Request $request    The current REST request.
		 */
		return (bool) apply_filters( 'zoninator_rest_get_zones_permissions_check', is_user_logged_in(), $request );
	}

	/**
	 * Check if a given request has access to add new zones.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function add_zone_permissions_check( $request ) {
		return $this->_permissions_check( 'insert' );
	}

	/**
	 * Check if a given request has access to get zone posts.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_zone_posts_permissions_check( $request ) {
		return true;
	}

	/**
	 * Check if a given request has access to update a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function update_zone_permissions_check( $request ) {
		$zone_id = $this->get_param( $request, 'zone_id', 0, 'absint' );
		return $this->_permissions_check( 'update', $zone_id );
	}

	/**
	 * Check if a given request may curate a zone: change its posts, search for
	 * posts to add, or take its lock.
	 *
	 * This is the capability the Zones screen needs to open and curate a zone,
	 * not the one for editing its name and description.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function manage_zone_permissions_check( $request ) {
		return $this->_permissions_check( 'manage' );
	}

	public function is_numeric( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly.
		return is_numeric( $item );
	}

	public function is_numeric_array( $items ) {
		return is_array( $items ) && count( $items ) === count( array_filter( $items, 'is_numeric' ) );
	}

	public function sanitize_string( $item ) {
		// Core has already unslashed REST input, so don't unslash it again.
		// See Request::sanitize_value() for why this isn't htmlentities().
		return htmlspecialchars( $item );
	}

	/**
	 * @param WP_REST_Request $rest_request
	 * @param string          $key               Parameter name.
	 * @param string          $default_value
	 * @param string          $sanitize_callback
	 *
	 * @return array|mixed|null|string
	 */
	private function get_param( $rest_request, $key, $default_value = '', $sanitize_callback = '' ) {
		$value = $rest_request->get_param( $key );
		$value = empty( $value ) ? $default_value : $value;

		if ( is_callable( $sanitize_callback ) ) {
			$value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	public function _params_for_create_zone() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return array(
			'name'        => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default'           => '',
				'required'          => false,
			),
			'slug'        => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default'           => '',
				'required'          => false,
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'default'           => '',
				'required'          => false,
			),
		);
	}

	public function _params_for_update_zone() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return array(
			'name'        => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required'          => false,
			),
			'slug'        => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required'          => false,
			),
			'description' => array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_string' ),
				'required'          => false,
			),
		);
	}

	public function _get_zone_id_param() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		return array(
			'zone_id' => array(
				'type'              => 'integer',
				'validate_callback' => array( $this, 'is_numeric' ),
				'sanitize_callback' => 'absint',
				'required'          => true,
			),
		);
	}

	public function _get_zone_post_rest_route_params() { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$zone_params = $this->_get_zone_id_param();
		return array_merge(
			array(
				'post_ids' => array(
					'type'              => 'array',
					'validate_callback' => array( $this, 'is_numeric_array' ),
					'required'          => true,
					'items'             => array( 'type' => 'integer' ),
				),
			),
			$zone_params
		);
	}

	/**
	 * Args for a single post in a zone.
	 *
	 * @return array
	 */
	private function get_zone_post_item_params() {
		return array_merge(
			$this->_get_zone_id_param(),
			array(
				'post_id' => array(
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'required'          => true,
				),
			)
		);
	}

	/**
	 * Args for the post search. They keep the names the old admin-ajax action
	 * used, so the Zones screen's filters, and any a site adds, keep working.
	 *
	 * @return array
	 */
	private function get_search_params() {
		return array(
			'term'    => array(
				'type'    => 'string',
				'default' => '',
			),
			// Not typed as an integer: a site may filter the category dropdown to send
			// something else, which the search ignores rather than rejects.
			'cat'     => array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
			'date'    => array(
				'type'    => 'string',
				'default' => '',
			),
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A route arg, not a query arg. get_posts_excluding() drops these in PHP.
			'exclude' => array(
				'type'    => 'array',
				'items'   => array( 'type' => 'integer' ),
				'default' => array(),
			),
			'limit'   => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
			),
			// The zone being edited, for sites that focus the search on it. Not checked
			// against the zones: it only reaches the query args filters.
			'zone_id' => array(
				'type'    => 'integer',
				'minimum' => 0,
				'default' => 0,
			),
		);
	}

	public function _filter_zone_properties( $zone ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		$data = $zone->to_array();

		return array(
			'term_id'     => $data['term_id'],
			'slug'        => $data['slug'],
			'name'        => $data['name'],
			'description' => $data['description'],
		);
	}

	/**
	 * Respond with a 200.
	 *
	 * @param mixed $data Body.
	 * @return WP_REST_Response
	 */
	private function ok( $data ) {
		return $this->respond( $data, 200 );
	}

	/**
	 * Respond with a 201.
	 *
	 * @param mixed $data Body.
	 * @return WP_REST_Response
	 */
	private function created( $data ) {
		return $this->respond( $data, 201 );
	}

	/**
	 * An error with a code and a 400 status.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function bad_request( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * Turns what the Application layer threw into this API's response.
	 *
	 * @param Throwable $exception What was thrown.
	 * @param int       $zone_id   The zone being changed, for the locked response.
	 * @return WP_Error|WP_REST_Response
	 *
	 * @throws Throwable If it isn't one of Zoninator's own exceptions.
	 */
	private function error_response( Throwable $exception, $zone_id ) {
		if ( $exception instanceof ZoneLockedException ) {
			// Core's REST error shape, keeping zone_id and blocked, which the lock
			// route's response had before it had a code.
			$locking_user = get_userdata( $exception->get_holder_id() );

			return new WP_REST_Response(
				array(
					'code'    => self::ZONE_LOCKED,
					'message' => sprintf(
						$this->settings->get_message( 'error-zone-lock' ),
						$locking_user ? $locking_user->display_name : __( 'another user', 'zoninator' )
					),
					'data'    => array( 'status' => 400 ),
					'zone_id' => $zone_id,
					'blocked' => true,
				),
				400
			);
		}

		if ( $exception instanceof ZoneNotFoundException ) {
			return $this->zone_not_found();
		}

		if ( $exception instanceof PostNotAllowedException ) {
			return $this->bad_request( self::INVALID_POST_ID, $this->translations[ self::INVALID_POST_ID ] );
		}

		if ( $exception instanceof AccessDeniedException ) {
			return new WP_Error( self::PERMISSION_DENIED, __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( $exception instanceof InvalidZoneException ) {
			return $this->bad_request( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
		}

		if ( $exception instanceof ZoneNotSavedException ) {
			return $this->bad_request( $exception->get_error_code(), $exception->getMessage() );
		}

		// Not one of ours, so not something to turn into a response.
		throw $exception;
	}

	/**
	 * Create a 404 response
	 *
	 * @return WP_Error
	 */
	private function zone_not_found() {
		return new WP_Error( self::ZONE_NOT_FOUND, $this->translations[ self::ZONE_NOT_FOUND ], array( 'status' => 404 ) );
	}

	/**
	 * Wrap data in a response, unless it's already one.
	 *
	 * @param mixed $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private function respond( $data, $status ) {
		if ( $data instanceof WP_REST_Response ) {
			return $data;
		}

		return new WP_REST_Response( $data, $status );
	}

	/**
	 * @param $zone_id
	 * @return bool|WP_Error
	 */
	private function _permissions_check( $action, $zone_id = null ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
		if ( ! $this->capabilities->check( $action, $zone_id ) ) {
			return new WP_Error( self::PERMISSION_DENIED, __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
}
