<?php
/**
 * REST controller for the zoninator/v1 namespace.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\REST;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Automattic\Zoninator\Admin\Screen;
use Automattic\Zoninator\Capabilities;
use Automattic\Zoninator\Feed;
use Automattic\Zoninator\Locks;
use Automattic\Zoninator\Request;
use Automattic\Zoninator\Settings;
use Automattic\Zoninator\Taxonomy;
use Automattic\Zoninator\Zones;

/**
 * Zones, their posts and their locks.
 *
 * @since 0.12.0
 */
final class Zones_Controller extends WP_REST_Controller {
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
	 * The zone taxonomy.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Zone locks.
	 *
	 * @var Locks
	 */
	private $locks;

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
	 * @param Taxonomy     $taxonomy     The zone taxonomy.
	 * @param Locks        $locks        Zone locks.
	 * @param Feed         $feed         The JSON feed.
	 * @param Settings     $settings     Zoninator's settings.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Screen       $screen       The Zones screen.
	 */
	public function __construct( Zones $zones, Taxonomy $taxonomy, Locks $locks, Feed $feed, Settings $settings, Capabilities $capabilities, Screen $screen ) {
		$this->zones        = $zones;
		$this->taxonomy     = $taxonomy;
		$this->locks        = $locks;
		$this->feed         = $feed;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->screen       = $screen;
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

		// Zoninator::get_zones() returns false, not a WP_Error, when the zones can't be read.
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

		$result = $this->zones->insert(
			$slug,
			$name,
			array(
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( $result->get_error_code(), $result->get_error_message() );
		}

		$zone = $this->zones->get( $result['term_id'] );

		return $this->created( $this->_filter_zone_properties( $zone ) );
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

		$result = $this->zones->update( $zone, $update_params );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( $result->get_error_code(), $result->get_error_message() );
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

		$result = $this->zones->delete( $zone );

		if ( is_wp_error( $result ) ) {
			return $this->bad_request( $result->get_error_code(), $result->get_error_message() );
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

		$posts = array_map( 'get_post', $post_ids );

		if ( count( $posts ) !== count( array_filter( $posts, array( $this->capabilities, 'current_user_can_add_post_to_zone' ) ) ) ) {
			return $this->bad_request( self::INVALID_POST_ID, $this->translations[ self::INVALID_POST_ID ] );
		}

		$result = $this->zones->add_posts( $zone_id, $posts );

		if ( is_wp_error( $result ) ) {
			return $this->respond( $result, 500 );
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

		if ( ! $this->capabilities->current_user_can_add_post_to_zone( $post_id ) ) {
			return $this->bad_request( self::INVALID_POST_ID, $this->translations[ self::INVALID_POST_ID ] );
		}

		$this->zones->add_posts( $zone, $post_id, true );

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

		if ( ! $this->capabilities->current_user_can_add_post_to_zone( $post_id ) ) {
			return $this->bad_request( self::INVALID_POST_ID, $this->translations[ self::INVALID_POST_ID ] );
		}

		$this->zones->remove_posts( $zone, $post_id );

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
			$this->find_posts(
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
	 * Find posts to offer for a zone. The search route and the deprecated
	 * zoninator_search_posts AJAX action both use this.
	 *
	 * @param string $term    Search term. Empty for the latest content.
	 * @param int    $cat     Category ID to filter by, or 0.
	 * @param string $date    Date to filter by, as Y-m-d, or ''.
	 * @param int[]  $exclude Post IDs to leave out, usually those already in the zone.
	 * @param int    $limit   Number of search results, up to 100. 0 for the default. The latest content ignores it.
	 * @param int    $zone_id Zone being edited, or 0. Passed to the query args filters.
	 * @return array[] Results, each passed through the zoninator_search_results_post filter.
	 */
	public function find_posts( $term, $cat, $date, $exclude, $limit = 0, $zone_id = 0 ) {
		$post_types = $this->taxonomy->get_post_types();

		if ( '' === $term ) {
			// With nothing typed yet, offer the latest content.
			$args = apply_filters(
				'zoninator_recent_posts_args',
				array(
					'posts_per_page'      => $this->settings->get_posts_per_page(),
					'order'               => 'DESC',
					'orderby'             => 'post_date',
					'post_type'           => $post_types,
					'ignore_sticky_posts' => true,
					'post_status'         => array( 'publish', 'future' ),
					'suppress_filters'    => false,
				),
				$zone_id
			);
		} else {
			$limit = $limit > 0 ? min( $limit, 100 ) : $this->settings->get_posts_per_page();

			$args = apply_filters(
				'zoninator_search_args',
				array(
					's'                => $term,
					'posts_per_page'   => $limit,
					'post_type'        => $post_types,
					'post_status'      => array( 'publish', 'future' ),
					'order'            => 'DESC',
					'orderby'          => 'post_date',
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
					'suppress_filters' => true,
					'no_found_rows'    => true,
				),
				$zone_id
			);
		}

		if ( Request::is_valid_category_filter( $cat ) ) {
			$args['cat'] = $cat;
		}

		if ( Request::is_valid_date_filter( $date ) ) {
			$date_parts       = explode( '-', $date );
			$args['year']     = $date_parts[0];
			$args['monthnum'] = $date_parts[1];
			$args['day']      = $date_parts[2];
		}

		// A post ID finds that post directly, which skips the slow LIKE search. It
		// uses the filtered search args, so it only finds a post the text search
		// could offer. If it finds nothing, search the text as usual, so a number
		// in a title, such as "1984", still matches.
		if ( ctype_digit( $term ) ) {
			$by_id = $args;
			unset( $by_id['s'] );
			// post__in, not p: p makes a singular query, which ignores taxonomy args.
			$by_id['post__in']       = array( (int) $term );
			$by_id['posts_per_page'] = 1;

			$posts = $this->get_posts_excluding( $by_id, $exclude );
			if ( $posts ) {
				return array_map( array( $this, 'format_search_result' ), $posts );
			}
		}

		return array_map( array( $this, 'format_search_result' ), $this->get_posts_excluding( $args, $exclude ) );
	}

	/**
	 * Formats a post as a search result.
	 *
	 * @param \WP_Post $post Post.
	 * @return array The result, passed through the zoninator_search_results_post filter.
	 */
	private function format_search_result( $post ) {
		return apply_filters(
			'zoninator_search_results_post',
			array(
				'title'             => empty( $post->post_title ) ? __( '(no title)', 'zoninator' ) : $post->post_title,
				'post_id'           => $post->ID,
				'date'              => get_the_time( get_option( 'date_format' ), $post ),
				'post_type'         => $post->post_type,
				'post_type_label'   => $this->zones->get_post_type_label( $post ),
				'post_status'       => $post->post_status,
				'post_status_label' => $this->zones->get_post_status_label( $post ),
			),
			$post
		);
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

		// The query only knows post types and statuses, so leave out posts the user may not read,
		// such as ones a membership plugin restricts. This can return fewer than the limit.
		$posts = array_values(
			array_filter(
				get_posts( $args ),
				function ( $post ) use ( $exclude ) {
					return ! in_array( $post->ID, $exclude, true ) && $this->capabilities->current_user_can_add_post_to_zone( $post );
				}
			)
		);

		return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
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

		$zone_locked = $this->locks->is_locked( $zone );
		if ( $zone_locked ) {
			// Core's REST error shape, keeping zone_id and blocked, which this
			// response had before it had a code.
			$locking_user = get_userdata( $zone_locked );
			return new WP_REST_Response(
				array(
					'code'    => self::ZONE_LOCKED,
					'message' => sprintf(
						$this->settings->get_message( 'error-zone-lock' ),
						$locking_user ? $locking_user->display_name : __( 'another user', 'zoninator' )
					),
					'data'    => array( 'status' => 400 ),
					'zone_id' => $this->zones->get_id( $zone ),
					'blocked' => true,
				),
				400
			);
		}

		$this->locks->lock( $zone_id );
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
	 * Args for the post search. They match the deprecated AJAX action's, so the
	 * Zones screen's filters, and any a site adds, keep working.
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
	 * The error for a zone that doesn't exist.
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
