<?php
/**
 * Zones and the posts in them.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Application\ZoneRemover;
use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Reads zones and their posts for WordPress code, as WP_Term, WP_Post and
 * WP_Query, and handles a shared term being split.
 *
 * Its methods that change a zone hand the work to the Application layer
 * (ZoneManager, ZoneRemover and ZoneCurator) and turn the result back into
 * what WordPress code expects: arrays and WP_Error. They stay only until
 * every caller uses those classes.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( Zones::class ) to unhook it.
 *
 * @since 0.12.0
 * @internal Use Zoninator(), the template tags and the hooks instead.
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
	 * Creates and changes zones.
	 *
	 * @var ZoneManager
	 */
	private $manager;

	/**
	 * Deletes zones.
	 *
	 * @var ZoneRemover
	 */
	private $remover;

	/**
	 * Changes the posts in a zone.
	 *
	 * @var ZoneCurator
	 */
	private $curator;

	/**
	 * Constructor.
	 *
	 * @param Taxonomy           $taxonomy     The zone taxonomy.
	 * @param Capabilities       $capabilities The capability checks.
	 * @param TermZoneRepository $terms        Zone terms.
	 * @param ZoneManager        $manager      Creates and changes zones.
	 * @param ZoneRemover        $remover      Deletes zones.
	 * @param ZoneCurator        $curator      Changes the posts in a zone.
	 */
	public function __construct( Taxonomy $taxonomy, Capabilities $capabilities, TermZoneRepository $terms, ZoneManager $manager, ZoneRemover $remover, ZoneCurator $curator ) {
		$this->taxonomy     = $taxonomy;
		$this->capabilities = $capabilities;
		$this->terms        = $terms;
		$this->manager      = $manager;
		$this->remover      = $remover;
		$this->curator      = $curator;
	}

	/**
	 * Hooks the handler that keeps zone posts when core splits a shared term. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		add_action( 'split_shared_term', array( $this, 'split_shared_term' ), 10, 4 );
	}

	/**
	 * Creates a zone.
	 *
	 * @param string $slug    The zone's slug.
	 * @param string $name    The zone's name, or '' to use the slug.
	 * @param array  $details Details to set, by name.
	 * @return array|WP_Error The new term's term_id and term_taxonomy_id, or an error.
	 */
	public function insert( $slug, $name = '', $details = array() ) {
		try {
			$zone = $this->manager->create( (string) $slug, (string) $name, (array) $details );
		} catch ( InvalidZoneException $exception ) {
			return new WP_Error( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
		} catch ( ZoneNotSavedException $exception ) {
			return new WP_Error( $exception->get_error_code(), $exception->getMessage() );
		}

		return $this->get_term_ids( (int) $zone->get_id() );
	}

	/**
	 * Changes a zone.
	 *
	 * @param int|string|WP_Term $zone Zone.
	 * @param array              $data The name, slug and details to change.
	 * @return array|WP_Error The term's term_id and term_taxonomy_id, or an error.
	 */
	public function update( $zone, $data = array() ) {
		$zone_id = $this->get_id( $zone );

		try {
			$this->manager->update( $zone_id, (array) $data );
		} catch ( ZoneNotFoundException $exception ) {
			return $this->invalid_zone();
		} catch ( ZoneNotSavedException $exception ) {
			return new WP_Error( $exception->get_error_code(), $exception->getMessage() );
		}

		return $this->get_term_ids( $zone_id );
	}

	/**
	 * Deletes a zone, and takes its posts out of it.
	 *
	 * @param int|string|WP_Term $zone Zone.
	 * @return true|WP_Error
	 */
	public function delete( $zone ) {
		try {
			$this->remover->remove( $this->get_id( $zone ) );
		} catch ( ZoneNotFoundException $exception ) {
			return $this->invalid_zone();
		} catch ( ZoneNotSavedException $exception ) {
			return new WP_Error( 'delete-zone', __( "Sorry, we couldn't delete the zone.", 'zoninator' ) );
		}

		return true;
	}

	/**
	 * Adds posts to a zone, or sets the zone's posts.
	 *
	 * @param int|string|WP_Term $zone   Zone.
	 * @param mixed              $posts  A post or posts, as IDs, WP_Post objects or arrays with an ID.
	 * @param bool               $append Whether to add the posts to the end. Otherwise they replace the zone's posts.
	 * @return true|WP_Error
	 */
	public function add_posts( $zone, $posts, $append = false ) {
		$zone_id  = $this->get_id( $zone );
		$post_ids = array_map( array( $this, 'get_post_id' ), (array) $posts );

		try {
			if ( $append ) {
				$this->curator->add_posts( $zone_id, $post_ids );
			} else {
				$this->curator->set_posts( $zone_id, $post_ids );
			}
		} catch ( ZoneNotFoundException $exception ) {
			return $this->invalid_zone();
		}

		return true;
	}

	/**
	 * Takes posts out of a zone.
	 *
	 * @param int|string|WP_Term $zone  Zone.
	 * @param mixed              $posts A post or posts, as IDs, WP_Post objects or arrays with an ID. Leave out to empty the zone.
	 */
	public function remove_posts( $zone, $posts = null ) {
		$zone_id = $this->get_id( $zone );

		try {
			if ( $posts ) {
				$this->curator->remove_posts( $zone_id, array_map( array( $this, 'get_post_id' ), (array) $posts ) );
			} else {
				$this->curator->clear_posts( $zone_id );
			}
		} catch ( ZoneNotFoundException $exception ) {
			return;
		}
	}

	/**
	 * @return WP_Error The error for a zone that doesn't exist.
	 */
	private function invalid_zone() {
		return new WP_Error( 'invalid-zone', __( "Sorry, that zone doesn't exist.", 'zoninator' ) );
	}

	/**
	 * @param int $zone_id Zone ID.
	 * @return array The zone's term_id and term_taxonomy_id, as wp_insert_term() and wp_update_term() return them.
	 */
	private function get_term_ids( $zone_id ) {
		$term = get_term( $zone_id, Taxonomy::NAME );

		return array(
			'term_id'          => $term->term_id,
			'term_taxonomy_id' => $term->term_taxonomy_id,
		);
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

	/**
	 * Gets the label for a post's status, such as "Published", falling back to the status itself.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public function get_post_status_label( $post ) {
		$status = get_post_status_object( $post->post_status );
		return $status ? $status->label : $post->post_status;
	}

	/**
	 * Gets the singular label for a post's type, such as "Post", falling back to the type itself.
	 *
	 * @param WP_Post $post Post.
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
		return $this->terms->fill_details( $zone );
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
