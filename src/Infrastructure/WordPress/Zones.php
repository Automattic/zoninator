<?php
/**
 * Zones and the posts in them.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Reads zones and their posts for WordPress code, as WP_Term, WP_Post and
 * WP_Query. The template tags are built on it.
 *
 * To change a zone, use the Application layer: ZoneManager, ZoneRemover and
 * ZoneCurator.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Zones {

	/**
	 * Prefix of the post meta key that holds a post's position in a zone. The
	 * zone's term ID follows it. Stored zone posts depend on it, so it can't change.
	 */
	public const META_PREFIX = '_zoninator_order_';

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
	 * Zone terms, for each zone's details.
	 *
	 * @var TermZoneRepository
	 */
	private $terms;

	/**
	 * Constructor.
	 *
	 * @param Taxonomy           $taxonomy     The zone taxonomy.
	 * @param Capabilities       $capabilities The capability checks.
	 * @param TermZoneRepository $terms        Zone terms.
	 */
	public function __construct( Taxonomy $taxonomy, Capabilities $capabilities, TermZoneRepository $terms ) {
		$this->taxonomy     = $taxonomy;
		$this->capabilities = $capabilities;
		$this->terms        = $terms;
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
	 * @return WP_Term[] The zones, by zone ID.
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
		return new WP_Query( $this->get_query_args( $zone, $args ) );
	}

	/**
	 * Gets the WP_Query args for a zone's posts, in zone order.
	 *
	 * The zone Query Loop block uses these too, so every way of listing a
	 * zone follows the same rules.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string|WP_Term $zone Zone.
	 * @param array              $args Optional. Query args to change the defaults.
	 * @return array
	 */
	public function get_query_args( $zone, $args = array() ) {
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

		return $args;
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

	public function get_post_order( $post, $zone ) {
		$post_id  = $this->get_post_id( $post );
		$meta_key = $this->get_meta_key( $zone );

		return get_metadata( 'post', $post_id, $meta_key, true );
	}

	public function fill_details( $zone ) {
		return $this->terms->fill_details( $zone );
	}
}
