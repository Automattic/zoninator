<?php
/**
 * Zone abilities for the WordPress Abilities API.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

use WP_Error;

/**
 * Registers read-only abilities for what editors do on the Zones screen:
 * list zones, get a zone's posts and search for posts to add.
 *
 * Zoninator registers these only when the Abilities API exists (WordPress 6.9
 * and later). Its hooks stay registered on Zoninator.
 *
 * @since 0.12.0
 */
final class Abilities {

	/**
	 * Slug of the ability category.
	 */
	public const CATEGORY = 'zoninator';

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * The JSON feed, which gives a zone's posts.
	 *
	 * @var Feed
	 */
	private $feed;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Returns the REST controller, whose search the search ability uses.
	 *
	 * @var callable
	 */
	private $search_controller;

	/**
	 * Constructor.
	 *
	 * @param Zones        $zones             Zones and the posts in them.
	 * @param Feed         $feed              The JSON feed.
	 * @param Capabilities $capabilities      The capability checks.
	 * @param callable     $search_controller Returns the REST controller, whose search the search ability uses.
	 */
	public function __construct( Zones $zones, Feed $feed, Capabilities $capabilities, callable $search_controller ) {
		$this->zones             = $zones;
		$this->feed              = $feed;
		$this->capabilities      = $capabilities;
		$this->search_controller = $search_controller;
	}

	/**
	 * Register the ability category.
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Zones', 'zoninator' ),
				'description' => __( 'Read zones and the posts in them.', 'zoninator' ),
			)
		);
	}

	/**
	 * Register the abilities.
	 */
	public function register() {
		$meta = array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'show_in_rest' => true,
		);

		wp_register_ability(
			'zoninator/list-zones',
			array(
				'label'               => __( 'List zones', 'zoninator' ),
				'description'         => __( 'Lists every zone with its ID, slug, name and description.', 'zoninator' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array(
					'type'  => 'array',
					'items' => $this->get_zone_schema(),
				),
				'execute_callback'    => array( $this, 'list_zones' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'zoninator/get-zone-posts',
			array(
				'label'               => __( 'Get zone posts', 'zoninator' ),
				'description'         => __( 'Gets the posts in a zone, in their zone order.', 'zoninator' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'zone_id' => array(
							'type'        => 'integer',
							'description' => __( 'The zone ID.', 'zoninator' ),
							'minimum'     => 1,
						),
					),
					'required'             => array( 'zone_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					// Sites choose the fields with the zoninator_zone_feed_fields filter.
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'ID'          => array( 'type' => 'integer' ),
							'post_date'   => array( 'type' => 'string' ),
							'post_title'  => array( 'type' => 'string' ),
							'post_status' => array( 'type' => 'string' ),
						),
						'additionalProperties' => true,
					),
				),
				'execute_callback'    => array( $this, 'get_zone_posts' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'meta'                => $meta,
			)
		);

		wp_register_ability(
			'zoninator/search-posts',
			array(
				'label'               => __( 'Search posts for a zone', 'zoninator' ),
				'description'         => __( 'Searches published and scheduled posts that can be added to a zone. A post ID as the term finds that post. With no term, lists the latest posts.', 'zoninator' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					// Lets the ability run with no input, for the latest posts.
					'default'              => array(),
					'properties'           => array(
						'term'    => array(
							'type'        => 'string',
							'description' => __( 'Text or post ID to search for. Leave empty for the latest posts.', 'zoninator' ),
							'default'     => '',
						),
						'cat'     => array(
							'type'        => 'integer',
							'description' => __( 'Category ID to filter by, or 0 for all.', 'zoninator' ),
							'minimum'     => 0,
							'default'     => 0,
						),
						'date'    => array(
							'type'        => 'string',
							'description' => __( 'Date to filter by, as YYYY-MM-DD.', 'zoninator' ),
							'default'     => '',
						),
						// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- An ability input, not a query arg. find_posts() drops these in PHP.
						'exclude' => array(
							'type'        => 'array',
							'description' => __( 'Post IDs to leave out, such as the posts already in the zone.', 'zoninator' ),
							'items'       => array( 'type' => 'integer' ),
							'default'     => array(),
						),
						'limit'   => array(
							'type'        => 'integer',
							'description' => __( 'Number of search results, up to 100. 0 for the default. The latest posts list ignores it.', 'zoninator' ),
							'minimum'     => 0,
							'maximum'     => 100,
							'default'     => 0,
						),
						'zone_id' => array(
							'type'        => 'integer',
							'description' => __( 'The zone being edited, or 0. Passed to the search filters.', 'zoninator' ),
							'minimum'     => 0,
							'default'     => 0,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					// Sites can change each result with the zoninator_search_results_post filter.
					'items' => array(
						'type'                 => 'object',
						'properties'           => array(
							'title'             => array( 'type' => 'string' ),
							'post_id'           => array( 'type' => 'integer' ),
							'date'              => array( 'type' => 'string' ),
							'post_type'         => array( 'type' => 'string' ),
							'post_type_label'   => array( 'type' => 'string' ),
							'post_status'       => array( 'type' => 'string' ),
							'post_status_label' => array( 'type' => 'string' ),
						),
						'additionalProperties' => true,
					),
				),
				'execute_callback'    => array( $this, 'search_posts' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'meta'                => $meta,
			)
		);
	}

	/**
	 * Whether the current user may use the abilities: the same capability the
	 * Zones screen needs.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return $this->capabilities->current_user_can_manage_zones();
	}

	/**
	 * List every zone.
	 *
	 * @return array[]|WP_Error
	 */
	public function list_zones() {
		$zones = $this->zones->get_all();

		// Zones::get_all() returns false, not a WP_Error, when the zones can't be read.
		if ( ! is_array( $zones ) ) {
			return new WP_Error( 'zones-read-error', __( 'Zones could not be read.', 'zoninator' ) );
		}

		return array_map(
			static function ( $zone ) {
				return array(
					'term_id'     => (int) $zone->term_id,
					'slug'        => $zone->slug,
					'name'        => $zone->name,
					'description' => $zone->description,
				);
			},
			array_values( $zones )
		);
	}

	/**
	 * Get a zone's posts.
	 *
	 * @param array $input Ability input.
	 * @return array[]|WP_Error
	 */
	public function get_zone_posts( $input ) {
		$input   = (array) $input;
		$zone_id = (int) $input['zone_id'];

		if ( ! $this->zones->get( $zone_id ) ) {
			return $this->zone_not_found();
		}

		$posts = $this->feed->get( $zone_id );

		if ( is_wp_error( $posts ) ) {
			return $posts;
		}

		// The feed gives objects, or arrays if a site filters it; the output schema wants arrays.
		return array_map(
			static function ( $post ) {
				return (array) $post;
			},
			array_values( $posts )
		);
	}

	/**
	 * Search for posts to add to a zone.
	 *
	 * @param array $input Ability input.
	 * @return array[]|WP_Error
	 */
	public function search_posts( $input = array() ) {
		$input = wp_parse_args(
			(array) $input,
			array(
				'term'    => '',
				'cat'     => 0,
				'date'    => '',
				'exclude' => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Not a query arg.
				'limit'   => 0,
				'zone_id' => 0,
			)
		);

		if ( $input['zone_id'] && ! $this->zones->get( (int) $input['zone_id'] ) ) {
			return $this->zone_not_found();
		}

		$controller = call_user_func( $this->search_controller );

		return $controller->find_posts(
			(string) $input['term'],
			(int) $input['cat'],
			$input['date'],
			wp_parse_id_list( $input['exclude'] ),
			(int) $input['limit'],
			(int) $input['zone_id']
		);
	}

	/**
	 * The schema of a zone in the list.
	 *
	 * @return array
	 */
	private function get_zone_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'term_id'     => array( 'type' => 'integer' ),
				'slug'        => array( 'type' => 'string' ),
				'name'        => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		);
	}

	/**
	 * The error for a zone ID that doesn't exist.
	 *
	 * @return WP_Error
	 */
	private function zone_not_found() {
		return new WP_Error( 'zone-not-found', __( 'Zone not found.', 'zoninator' ), array( 'status' => 404 ) );
	}
}
