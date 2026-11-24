<?php
/**
 * Zones and the posts in them.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

use WP_Error;
use WP_Query;

/**
 * Creates, reads, updates and deletes zones, and stores the posts in each
 * zone in order.
 *
 * A zone is a term in the zoninator_zones taxonomy, with its details
 * serialised into the term description. A post's position in a zone is
 * post meta keyed by the zone's ID.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (insert_zone() for insert(), get_zone_posts() for get_posts(), and so on),
 * which delegates here.
 *
 * @since 0.12.0
 */
final class Zones {

	/**
	 * Prefix of the post meta key that holds a post's position in a zone. The
	 * zone's term ID follows it. Zoninator::ZONE_META_PREFIX is its public name.
	 */
	public const META_PREFIX = '_zoninator_order_';

	/**
	 * Query args that find every post in a zone, for code that changes a
	 * zone's posts. Whoever is asking, and whatever query filters other plugins
	 * add, a post left out here would keep its old position in the zone.
	 */
	private const EVERY_POST = array(
		'post_status'      => 'any',
		// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
		'suppress_filters' => true,
	);

	/**
	 * Zoninator's settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The zone taxonomy.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Zoninator's settings.
	 * @param Taxonomy $taxonomy The zone taxonomy.
	 * @param Capabilities $capabilities The capability checks.
	 */
	public function __construct( Settings $settings, Taxonomy $taxonomy, Capabilities $capabilities ) {
		$this->settings     = $settings;
		$this->taxonomy     = $taxonomy;
		$this->capabilities = $capabilities;
	}

	public function insert( $slug, $name = '', $details = array() ) {

		// slug cannot be empty
		if ( empty( $slug ) ) {
			return new WP_Error( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
		}

		$name = empty( $name ) ? $slug : $name;

		$details = wp_parse_args( $details, $this->settings->get_detail_defaults() );
		$details = maybe_serialize( $details );

		$args = array(
			'slug'        => $slug,
			'description' => $details,
		);

		// Filterize to allow other inputs
		$args = apply_filters( 'zoninator_insert_zone', $args );

		// wp_insert_term() expects slashed data, as it unslashes the name and description.
		return wp_insert_term( wp_slash( $name ), Taxonomy::NAME, wp_slash( $args ) );
	}

	public function update( $zone, $data = array() ) {
		$zone_id = $this->get_id( $zone );

		if ( $this->exists( $zone_id ) ) {
			$zone = $this->get( $zone );

			$name    = Request::get_value_or_default( 'name', $data, $zone->name );
			$slug    = Request::get_value_or_default( 'slug', $data, $zone->slug );
			$details = Request::get_value_or_default( 'details', $data, array() );

			// Keep the zone's current value for any detail the caller didn't send.
			$defaults = $this->settings->get_detail_defaults();
			$current  = array_intersect_key( get_object_vars( $zone ), $defaults );
			$details  = wp_parse_args( $details, wp_parse_args( $current, $defaults ) );
			$details  = maybe_serialize( $details );

			$args = array(
				'name'        => $name,
				'slug'        => $slug,
				'description' => $details,
			);

			// Filterize to allow other inputs
			$args = apply_filters( 'zoninator_update_zone', $args, $zone_id, $zone );

			// wp_update_term() expects slashed data, as it unslashes the name and description.
			return wp_update_term( $zone_id, Taxonomy::NAME, wp_slash( $args ) );
		}

		return new WP_Error( 'invalid-zone', __( "Sorry, that zone doesn't exist.", 'zoninator' ) );
	}

	public function delete( $zone ) {
		$zone_id = $this->get_id( $zone );

		if ( $this->exists( $zone_id ) ) {
			// Delete all post associations for the zone
			$this->remove_posts( $zone_id );

			// Delete the term
			$delete = wp_delete_term( $zone_id, Taxonomy::NAME );

			if ( ! $delete ) {
				return new WP_Error( 'delete-zone', __( "Sorry, we couldn't delete the zone.", 'zoninator' ) );
			} else {
				do_action( 'zoninator_delete_zone', $zone_id );
				return $delete;
			}
		}

		return new WP_Error( 'invalid-zone', __( "Sorry, that zone doesn't exist.", 'zoninator' ) );
	}

	/**
	 * @param $zone
	 * @param $posts
	 * @param bool  $append
	 * @return bool|WP_Error
	 */
	public function add_posts( $zone, $posts, $append = false ) {
		$zone     = $this->get( $zone );
		$meta_key = $this->get_meta_key( $zone );

		if ( $append ) {
			// Order should be the highest post order, counting every post in the
			// zone, so a new post never shares a position with one already there.
			$last_post = $this->get_single_post( $zone, array( 'order' => 'DESC' ) + self::EVERY_POST );
			$order     = $last_post ? $this->get_post_order( $last_post, $zone ) : 0;
		} else {
			$order = 0;
			$this->remove_posts( $zone );
		}

		foreach ( (array) $posts as $post ) {
			$post_id = $this->get_post_id( $post );
			if ( $post_id ) {
				++$order;
				update_metadata( 'post', $post_id, $meta_key, $order, true );
			}
		}

		clean_term_cache( $this->get_id( $zone ), Taxonomy::NAME ); // flush cache for our zone term and related APC caches

		do_action( 'zoninator_add_zone_posts', $posts, $zone );

		return true;
	}

	/**
	 * @param mixed $zone
	 * @param mixed $posts
	 *
	 * @return bool|WP_Error
	 */
	public function remove_posts( $zone, $posts = null ) {
		$zone     = $this->get( $zone );
		$meta_key = $this->get_meta_key( $zone );

		// If null, remove every post in the zone.
		if ( ! $posts ) {
			$posts = $this->get_posts( $zone, self::EVERY_POST );
		}

		foreach ( (array) $posts as $post ) {
			$post_id = $this->get_post_id( $post );
			if ( $post_id ) {
				delete_metadata( 'post', $post_id, $meta_key );
			}
		}

		clean_term_cache( $this->get_id( $zone ), Taxonomy::NAME ); // flush cache for our zone term and related APC caches

		do_action( 'zoninator_remove_zone_posts', $posts, $zone );
	}

	public function get_posts( $zone, $args = array() ) {
		return $this->get_query( $zone, $args )->posts;
	}

	/**
	 * Gets every zone a post is in.
	 *
	 * Reads the post's meta once, rather than querying each zone, so the cost
	 * doesn't grow with the number of zones. A post only counts as in a zone
	 * where get_posts() would list it: a supported post type, and published
	 * unless a zone manager is in the admin.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Term[] The zones, by zone ID.
	 */
	public function get_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, $this->taxonomy->get_post_types(), true ) ) {
			return array();
		}

		if ( 'publish' !== $post->post_status && ( ! is_admin() || ! $this->capabilities->current_user_can_manage_zones() ) ) {
			return array();
		}

		$zone_ids = array();
		foreach ( array_keys( get_post_meta( $post->ID ) ) as $meta_key ) {
			if ( 0 === strpos( $meta_key, self::META_PREFIX ) ) {
				$zone_ids[] = absint( substr( $meta_key, strlen( self::META_PREFIX ) ) );
			}
		}
		sort( $zone_ids );

		return array_values( array_filter( array_map( array( $this, 'get' ), $zone_ids ) ) );
	}

	public function get_query( $zone, $args = array() ) {
		$meta_key = $this->get_meta_key( $zone );

		$defaults = array(
			'order'               => 'ASC',
			// phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.posts_per_page_posts_per_page -- Zones are hand-curated and small; callers expect every post in the zone, and can pass their own limit.
			'posts_per_page'      => -1,
			'post_type'           => $this->taxonomy->get_post_types(),
			'ignore_sticky_posts' => '1', // don't want sticky posts messing up our order
		);

		// Default to published posts, except for zone managers in the admin.
		// is_admin() alone is true for every admin-ajax.php and admin-post.php
		// request, including logged-out ones.
		if ( ! is_admin() || ! $this->capabilities->current_user_can_manage_zones() ) {
			$defaults['post_status'] = array( 'publish' );
		}

		if ( is_admin() ) { // skip APC in the admin
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
			$defaults['suppress_filters'] = true;
		}

		$args = wp_parse_args( $args, $defaults );

		// Un-overridable args
		$args['orderby']  = 'meta_value_num';
		$args['meta_key'] = $meta_key;

		return new WP_Query( $args );
	}

	public function get_last_post( $zone ) {
		return $this->get_single_post(
			$zone,
			array(
				'order' => 'DESC',
			)
		);
	}

	public function get_first_post( $zone ) {
		return $this->get_single_post( $zone );
	}

	public function get_prev_post( $zone, $post_id ) {
		return $this->get_adjacent_post( $zone, $post_id, '<', 'DESC' );
	}

	public function get_next_post( $zone, $post_id ) {
		return $this->get_adjacent_post( $zone, $post_id, '>', 'ASC' );
	}

	/**
	 * Get the post just before or after a post in a zone.
	 *
	 * @param int|string|WP_Term $zone    Zone.
	 * @param int|WP_Post        $post_id Post in the zone.
	 * @param string             $compare '<' for the post before, '>' for the post after.
	 * @param string             $order   'DESC' for the post before, 'ASC' for the post after.
	 * @return WP_Post|false The adjacent post, or false if there's none or the post isn't in the zone.
	 */
	private function get_adjacent_post( $zone, $post_id, $compare, $order ) {
		$position = $this->get_post_order( $post_id, $zone );

		if ( '' === $position || false === $position ) {
			return false;
		}

		return $this->get_single_post(
			$zone,
			array(
				'order'        => $order,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'   => (int) $position,
				'meta_compare' => $compare,
				'meta_type'    => 'NUMERIC',
			)
		);
	}

	public function get_single_post( $zone, $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'posts_per_page' => 1,
				'showposts'      => 1,
				// One post is returned, so counting every match is wasted work.
				'no_found_rows'  => true,
			)
		);

		$post = $this->get_posts( $zone, $args );

		if ( is_array( $post ) && array() !== $post ) {
			return array_pop( $post );
		}

		return false;
	}

	public function get_all( $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => 0,
			)
		);

		// Always the zone taxonomy, as the old get_terms( $taxonomy, $args ) call ensured.
		$args['taxonomy'] = Taxonomy::NAME;

		$zones = get_terms( $args );

		if ( ! is_wp_error( $zones ) ) {
			// Add extra fields in description as properties
			foreach ( $zones as $key => $zone ) {
				$zones[ $key ] = $this->fill_details( $zone );
			}

			return $zones;
		}

		return false;
	}

	public function get( $zone ) {
		if ( is_int( $zone ) ) {
			$field = 'id';
		} elseif ( is_string( $zone ) ) {
			$field = 'slug';
			$zone  = $this->get_slug( $zone );
		} elseif ( is_object( $zone ) ) {
			return $zone;
		} else {
			return false;
		}

		$zone = get_term_by( $field, $zone, Taxonomy::NAME );

		if ( ! $zone ) {
			return false;
		}

		return $this->fill_details( $zone );
	}

	public function exists( $zone ) {
		$zone_id = $this->get_id( $zone );
		return (bool) term_exists( $zone_id, Taxonomy::NAME );
	}

	public function get_id( $zone ) {
		if ( is_int( $zone ) ) {
			return $zone;
		}

		$zone = $this->get( $zone );
		if ( is_object( $zone ) ) {
			$zone = $zone->term_id;
		}

		return (int) $zone;
	}

	public function get_meta_key( $zone ) {
		$zone_id = $this->get_id( $zone );
		return self::META_PREFIX . $zone_id;
	}

	public function get_slug( $zone ) {
		if ( is_int( $zone ) ) {
			$zone = $this->get( $zone );
		}

		if ( is_object( $zone ) ) {
			$zone = $zone->slug;
		}

		return $zone;
	}

	public function get_post_id( $post ) {
		if ( is_int( $post ) ) {
			return $post;
		} elseif ( is_array( $post ) ) {
			return absint( $post['ID'] );
		} elseif ( is_object( $post ) ) {
			return $post->ID;
		}

		return false;
	}

	/**
	 * Gets the label for a post's status, such as "Published", falling back to the status itself.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function get_post_status_label( $post ) {
		$status = get_post_status_object( $post->post_status );
		return $status ? $status->label : $post->post_status;
	}

	/**
	 * Gets the singular label for a post's type, such as "Post", falling back to the type itself.
	 *
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function get_post_type_label( $post ) {
		$type = get_post_type_object( $post->post_type );
		return $type ? $type->labels->singular_name : $post->post_type;
	}

	public function get_post_order( $post, $zone ) {
		$post_id  = $this->get_post_id( $post );
		$meta_key = $this->get_meta_key( $zone );

		return get_metadata( 'post', $post_id, $meta_key, true );
	}

	public function fill_details( $zone ) {
		// get_terms() gives ids or names instead of terms when the fields arg is set.
		if ( ! is_object( $zone ) ) {
			return $zone;
		}

		if ( ! empty( $zone->zoninator_parsed ) && $zone->zoninator_parsed ) {
			return $zone;
		}

		$details = array();

		if ( ! empty( $zone->description ) && is_serialized( $zone->description ) ) {
			// Disallow object instantiation to prevent PHP object injection if a
			// crafted serialized payload reaches the term description via any path.
			// Silenced because unserialize() can still emit notices on truncated or
			// malformed payloads that pass is_serialized().
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$unserialized = @unserialize( trim( $zone->description ), array( 'allowed_classes' => false ) );
			if ( false === $unserialized ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$unserialized = @unserialize( $this->repair_serialized_lengths( trim( $zone->description ) ), array( 'allowed_classes' => false ) );
			}
			if ( is_array( $unserialized ) ) {
				$details = $unserialized;
			}
		}

		$details = wp_parse_args( $details, $this->settings->get_detail_defaults() );

		foreach ( $details as $detail_key => $detail_value ) {
			$zone->$detail_key = $detail_value;
		}

		$zone->zoninator_parsed = true;

		return $zone;
	}

	/**
	 * Recount the string lengths in a serialised value.
	 *
	 * Earlier versions stored a description with non-ASCII characters with its
	 * length counted before core decoded its HTML entities, so it no longer
	 * unserialised. The text is intact, so recounting recovers it.
	 *
	 * Limitation: this assumes no stored string contains `";`. The sanitisers
	 * encode `"`, so saved zone details don't; if one does, unserialize() still
	 * fails safely and the description reads as empty, as before.
	 *
	 * @param string $serialized Serialised value.
	 * @return string
	 */
	private function repair_serialized_lengths( $serialized ) {
		return preg_replace_callback(
			'/s:\d+:"(.*?)";/s',
			static function ( $matches ) {
				return 's:' . strlen( $matches[1] ) . ':"' . $matches[1] . '";';
			},
			$serialized
		);
	}

	// Handle 4.2 term-splitting
	public function split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
		if ( Taxonomy::NAME === $taxonomy ) {
			do_action( 'zoninator_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );

			// Quick, easy switcheroo; add posts to new zone id and remove from the old one.
			$posts = $this->get_posts( $old_term_id, self::EVERY_POST );
			if ( ! empty( $posts ) ) {
				$this->add_posts( $new_term_id, $posts );
				$this->remove_posts( $old_term_id );
			}

			do_action( 'zoninator_did_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );
		}
	}
}
