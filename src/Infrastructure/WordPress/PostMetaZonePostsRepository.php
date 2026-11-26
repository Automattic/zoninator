<?php
/**
 * The posts in each zone, kept as post meta.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use WP_Query;
use WP_Term;

/**
 * Keeps a post's place in a zone as post meta: the key is Zones::META_PREFIX
 * and the zone's ID, and the value is the post's position.
 *
 * It fires zoninator_add_zone_posts and zoninator_remove_zone_posts, so
 * every way of changing a zone's posts fires them.
 *
 * @since 1.0.0
 * @internal Use Automattic\Zoninator\Application\ZoneCurator instead.
 */
final class PostMetaZonePostsRepository implements ZonePostsRepositoryInterface {

	/**
	 * Constructor.
	 *
	 * @param TermZoneRepository $terms    Zone terms.
	 * @param Taxonomy           $taxonomy The zone taxonomy.
	 */
	public function __construct(
		private readonly TermZoneRepository $terms,
		private readonly Taxonomy $taxonomy,
	) {
	}

	/**
	 * Gets every post in a zone, in order, whatever its status.
	 *
	 * @param int $zone_id Zone ID.
	 * @return int[] Post IDs.
	 */
	public function get_post_ids( int $zone_id ): array {
		return $this->find_post_ids( $zone_id, 'ASC', -1 );
	}

	/**
	 * Adds posts to the end of a zone, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs, none of them in the zone already.
	 */
	public function append( int $zone_id, array $post_ids ): void {
		// Start after the highest position, counting every post in the zone, so
		// a new post never shares a position with one already there.
		$last = $this->find_post_ids( $zone_id, 'DESC', 1 );

		$this->write( $zone_id, $post_ids, $last ? (int) get_metadata( 'post', $last[0], $this->get_meta_key( $zone_id ), true ) : 0 );
	}

	/**
	 * Makes a zone hold these posts and no others, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function replace( int $zone_id, array $post_ids ): void {
		$current = $this->get_post_ids( $zone_id );

		// Only the posts leaving the zone lose their place. The rest keep their meta, and
		// write() leaves alone any position that hasn't changed, so a reorder writes only
		// the posts that move. The actions still pass every post, as they did when the
		// whole zone was cleared first.
		foreach ( array_diff( $current, $post_ids ) as $post_id ) {
			delete_metadata( 'post', $post_id, $this->get_meta_key( $zone_id ) );
		}

		$this->changed( 'zoninator_remove_zone_posts', $zone_id, $current );
		$this->write( $zone_id, $post_ids, 0 );
	}

	/**
	 * Takes posts out of a zone.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 */
	public function remove( int $zone_id, array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			delete_metadata( 'post', $post_id, $this->get_meta_key( $zone_id ) );
		}

		$this->changed( 'zoninator_remove_zone_posts', $zone_id, $post_ids );
	}

	/**
	 * Takes every post out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 */
	public function clear( int $zone_id ): void {
		$this->remove( $zone_id, $this->get_post_ids( $zone_id ) );
	}

	/**
	 * Gives each post the next position in a zone.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 * @param int   $position The highest position already taken.
	 */
	private function write( int $zone_id, array $post_ids, int $position ): void {
		foreach ( $post_ids as $post_id ) {
			++$position;
			// A string, as meta is stored, so update_metadata() skips a position that's unchanged.
			update_metadata( 'post', $post_id, $this->get_meta_key( $zone_id ), (string) $position );
		}

		$this->changed( 'zoninator_add_zone_posts', $zone_id, $post_ids );
	}

	/**
	 * Clears the zone's cache and tells the site its posts changed.
	 *
	 * @param string $action   The action to fire: zoninator_add_zone_posts or zoninator_remove_zone_posts.
	 * @param int    $zone_id  Zone ID.
	 * @param int[]  $post_ids The posts added or removed.
	 */
	private function changed( string $action, int $zone_id, array $post_ids ): void {
		clean_term_cache( $zone_id, Taxonomy::NAME ); // flush cache for our zone term and related APC caches

		$zone = $this->terms->get_term( $zone_id );

		if ( 'zoninator_add_zone_posts' === $action ) {
			/**
			 * Fires after posts are added to a zone.
			 *
			 * When posts are appended, it's given only those that weren't in the zone
			 * already, and doesn't fire if there were none. When the zone's posts are
			 * replaced, it's given every post now in the zone, in order.
			 *
			 * @since 0.5
			 * @since 1.0.0 Always given post IDs.
			 *
			 * @param int[]   $post_ids IDs of the posts added.
			 * @param WP_Term $zone     The zone.
			 */
			do_action( 'zoninator_add_zone_posts', $post_ids, $zone );
			return;
		}

		/**
		 * Fires after posts are removed from a zone.
		 *
		 * When the zone's posts are replaced, it's given every post that was in the
		 * zone, and the posts staying in the zone still have their places while it
		 * runs. zoninator_add_zone_posts then fires with every post now in it.
		 *
		 * @since 0.5
		 * @since 1.0.0 Always given post IDs.
		 *
		 * @param int[]   $post_ids IDs of the posts asked to be removed, whether or not they were in the zone.
		 * @param WP_Term $zone     The zone.
		 */
		do_action( 'zoninator_remove_zone_posts', $post_ids, $zone );
	}

	/**
	 * Finds the posts in a zone, whatever their status and whatever query
	 * filters other plugins add. A post left out here would keep its old
	 * position when the zone's posts change.
	 *
	 * @param int    $zone_id Zone ID.
	 * @param string $order   'ASC' for zone order, 'DESC' for the reverse.
	 * @param int    $limit   Number of posts, or -1 for all of them.
	 * @return int[] Post IDs.
	 */
	private function find_post_ids( int $zone_id, string $order, int $limit ): array {
		$query = new WP_Query(
			array(
				'fields'              => 'ids',
				'post_type'           => $this->taxonomy->get_post_types(),
				'post_status'         => 'any',
				'orderby'             => 'meta_value_num',
				'meta_key'            => $this->get_meta_key( $zone_id ),
				'order'               => $order,
				// phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.posts_per_page_posts_per_page -- Zones are hand-curated and small, and every post in one must be found.
				'posts_per_page'      => $limit,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
				'suppress_filters'    => true,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * @param int $zone_id Zone ID.
	 * @return string The post meta key that holds a post's position in the zone.
	 */
	private function get_meta_key( int $zone_id ): string {
		return Zones::META_PREFIX . $zone_id;
	}
}
