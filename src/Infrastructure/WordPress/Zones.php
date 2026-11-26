<?php
/**
 * Zones and the posts in them.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Reads zones and their posts for WordPress code, as WP_Term, WP_Post and
 * WP_Query. The template tags are built on it.
 *
 * A zone argument is an ID, a slug or the zone's term, and is left untyped,
 * as are query args: themes pass them straight from the template tags, and
 * anything that isn't a zone should give an empty result, not a fatal error.
 *
 * To change a zone, use the Application layer: ZoneManager and ZoneCurator.
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
	 * Constructor.
	 *
	 * @param Taxonomy           $taxonomy     The zone taxonomy.
	 * @param Capabilities       $capabilities The capability checks.
	 * @param TermZoneRepository $terms        Zone terms.
	 */
	public function __construct(
		private readonly Taxonomy $taxonomy,
		private readonly Capabilities $capabilities,
		private readonly TermZoneRepository $terms,
	) {
	}

	/**
	 * Gets every zone.
	 *
	 * @param array|string $args Optional. get_terms() args to change the defaults.
	 * @return WP_Term[]|false The zones, each with its details as properties, or false if they can't be read. With a fields arg, whatever get_terms() gives.
	 */
	public function get_all( $args = array() ): array|false {
		$args = wp_parse_args(
			$args,
			array(
				'orderby'    => 'id',
				'order'      => 'ASC',
				'hide_empty' => 0,
			)
		);

		// Always the zone taxonomy, whatever the args say.
		$args['taxonomy'] = Taxonomy::NAME;

		$zones = get_terms( $args );

		// A WP_Error, or the count a fields arg of 'count' gives.
		if ( ! is_array( $zones ) ) {
			return false;
		}

		return array_map( array( $this->terms, 'fill_details' ), $zones );
	}

	/**
	 * Gets a zone.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term. A string is always a slug, even if it's all digits.
	 * @return WP_Term|false The zone, with its details as properties, or false if there's no such zone.
	 */
	public function get( $zone ): WP_Term|false {
		if ( is_int( $zone ) ) {
			$zone = get_term_by( 'id', $zone, Taxonomy::NAME );
		} elseif ( is_string( $zone ) ) {
			$zone = get_term_by( 'slug', $zone, Taxonomy::NAME );
		}

		return $zone instanceof WP_Term ? $this->terms->fill_details( $zone ) : false;
	}

	/**
	 * Gets a zone's ID.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term. An ID is returned as it is, without checking there's such a zone.
	 * @return int The ID, or 0 if there's no such zone.
	 */
	public function get_id( $zone ): int {
		if ( is_int( $zone ) ) {
			return $zone;
		}

		$zone = $this->get( $zone );

		return $zone ? (int) $zone->term_id : 0;
	}

	/**
	 * Gets the post meta key that holds each post's position in a zone.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @return string
	 */
	public function get_meta_key( $zone ): string {
		return self::META_PREFIX . $this->get_id( $zone );
	}

	/**
	 * Gets the posts in a zone, in zone order.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @param array|string       $args Optional. WP_Query args to change the defaults.
	 * @return WP_Post[] The posts.
	 */
	public function get_posts( $zone, $args = array() ): array {
		return $this->get_query( $zone, $args )->posts;
	}

	/**
	 * Gets a query for the posts in a zone, in zone order.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @param array|string       $args Optional. WP_Query args to change the defaults.
	 * @return WP_Query
	 */
	public function get_query( $zone, $args = array() ): WP_Query {
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
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @param array|string       $args Optional. Query args to change the defaults.
	 * @return array
	 */
	public function get_query_args( $zone, $args = array() ): array {
		$defaults = array(
			'order'               => 'ASC',
			// phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.posts_per_page_posts_per_page -- Zones are hand-curated and small; callers expect every post in the zone, and can pass their own limit.
			'posts_per_page'      => -1,
			'post_type'           => $this->taxonomy->get_post_types(),
			'ignore_sticky_posts' => '1', // don't want sticky posts messing up our order
		);

		if ( ! $this->shows_unpublished_posts() ) {
			$defaults['post_status'] = array( 'publish' );
		}

		if ( is_admin() ) { // skip APC in the admin
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
			$defaults['suppress_filters'] = true;
		}

		$args = wp_parse_args( $args, $defaults );

		// Un-overridable args
		$args['orderby']  = 'meta_value_num';
		$args['meta_key'] = $this->get_meta_key( $zone );

		return $args;
	}

	/**
	 * Gets every zone a post is in.
	 *
	 * Reads the post's meta once, rather than querying each zone, so the cost
	 * doesn't grow with the number of zones. A post only counts as in a zone
	 * where get_posts() would list it: a supported post type, and published
	 * unless a zone manager is in the admin.
	 *
	 * @param int|WP_Post $post Post ID or post.
	 * @return WP_Term[] The zones, in order of zone ID.
	 */
	public function get_for_post( $post ): array {
		$post = get_post( $post );
		if ( ! $post || ! in_array( $post->post_type, $this->taxonomy->get_post_types(), true ) ) {
			return array();
		}

		if ( 'publish' !== $post->post_status && ! $this->shows_unpublished_posts() ) {
			return array();
		}

		$zone_ids = array();
		foreach ( array_keys( get_post_meta( $post->ID ) ) as $meta_key ) {
			// PHP turns an all-digit array key into an integer, so a meta key such as "2024" arrives as one.
			$meta_key = (string) $meta_key;

			if ( str_starts_with( $meta_key, self::META_PREFIX ) ) {
				$zone_ids[] = absint( substr( $meta_key, strlen( self::META_PREFIX ) ) );
			}
		}
		sort( $zone_ids );

		return array_values( array_filter( array_map( array( $this, 'get' ), $zone_ids ) ) );
	}

	/**
	 * Gets the post before a post in a zone.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @param int|WP_Post        $post Post ID or post.
	 * @return WP_Post|false The previous post, or false if there's none or the post isn't in the zone.
	 */
	public function get_prev_post( $zone, $post ): WP_Post|false {
		return $this->get_adjacent_post( $zone, $post, '<', 'DESC' );
	}

	/**
	 * Gets the post after a post in a zone.
	 *
	 * @param int|string|WP_Term $zone Zone ID, slug or term.
	 * @param int|WP_Post        $post Post ID or post.
	 * @return WP_Post|false The next post, or false if there's none or the post isn't in the zone.
	 */
	public function get_next_post( $zone, $post ): WP_Post|false {
		return $this->get_adjacent_post( $zone, $post, '>', 'ASC' );
	}

	/**
	 * Gets a post's position in a zone.
	 *
	 * @param int                $post_id Post ID.
	 * @param int|string|WP_Term $zone    Zone ID, slug or term.
	 * @return string The position, counted from 1, or '' if the post isn't in the zone.
	 */
	public function get_post_order( int $post_id, $zone ): string {
		return (string) get_metadata( 'post', $post_id, $this->get_meta_key( $zone ), true );
	}

	/**
	 * Gets a zone as the REST API and the abilities list it: its ID, slug, name and description.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Term $zone The zone, from get() or get_all().
	 * @return array{term_id: int, slug: string, name: string, description: string}
	 */
	public function get_data( WP_Term $zone ): array {
		return array(
			'term_id'     => (int) $zone->term_id,
			'slug'        => $zone->slug,
			'name'        => $zone->name,
			'description' => $zone->description,
		);
	}

	/**
	 * Whether a zone's unpublished posts are listed with its published ones:
	 * only in the admin, for a user who may curate zones.
	 *
	 * This asks WordPress about the request, where the caller could have been
	 * made to say. It can't usefully: with no post_status, WP_Query decides
	 * which statuses the admin lists by asking is_admin() itself, so a caller
	 * that set them here would have to copy that logic and keep it in step.
	 *
	 * is_admin() alone is true for every admin-ajax.php and admin-post.php
	 * request, including logged-out ones, so the user is checked too.
	 *
	 * @return bool
	 */
	private function shows_unpublished_posts(): bool {
		return is_admin() && $this->capabilities->can_curate_zones( get_current_user_id() );
	}

	/**
	 * Gets the post just before or after a post in a zone.
	 *
	 * @param int|string|WP_Term $zone    Zone ID, slug or term.
	 * @param int|WP_Post        $post    Post ID or post.
	 * @param string             $compare '<' for the post before, '>' for the post after.
	 * @param string             $order   'DESC' for the post before, 'ASC' for the post after.
	 * @return WP_Post|false The adjacent post, or false if there's none or the post isn't in the zone.
	 */
	private function get_adjacent_post( $zone, $post, string $compare, string $order ): WP_Post|false {
		$post     = get_post( $post );
		$position = $post ? $this->get_post_order( $post->ID, $zone ) : '';

		if ( '' === $position ) {
			return false;
		}

		$posts = $this->get_posts(
			$zone,
			array(
				'order'          => $order,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => (int) $position,
				'meta_compare'   => $compare,
				'meta_type'      => 'NUMERIC',
				'posts_per_page' => 1,
				// One post is returned, so counting every match is wasted work.
				'no_found_rows'  => true,
			)
		);

		return $posts ? $posts[0] : false;
	}
}
