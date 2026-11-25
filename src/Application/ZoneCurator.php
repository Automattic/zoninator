<?php
/**
 * Curates the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\ZoneFullException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;

/**
 * Adds posts to a zone, sets their order, and takes them out.
 *
 * It decides nothing about who may do so, or which posts suit a zone:
 * callers check those first.
 *
 * @since 1.0.0
 */
final class ZoneCurator {

	/**
	 * Where the zones are kept.
	 *
	 * @var ZoneRepositoryInterface
	 */
	private $zones;

	/**
	 * Where the posts in each zone are kept.
	 *
	 * @var ZonePostsRepositoryInterface
	 */
	private $zone_posts;

	/**
	 * Says how many posts a zone may hold.
	 *
	 * @var ZoneSettingsInterface
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 * @param ZoneSettingsInterface        $settings   Says how many posts a zone may hold.
	 */
	public function __construct( ZoneRepositoryInterface $zones, ZonePostsRepositoryInterface $zone_posts, ZoneSettingsInterface $settings ) {
		$this->zones      = $zones;
		$this->zone_posts = $zone_posts;
		$this->settings   = $settings;
	}

	/**
	 * Adds posts to the end of a zone, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneFullException     If the zone would hold more posts than it may.
	 */
	public function add_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );

		$post_ids = $this->clean( $post_ids );

		// A post already in the zone keeps its place, so it doesn't add to the count.
		$this->require_room( $zone_id, array_unique( array_merge( $this->zone_posts->get_post_ids( $zone_id ), $post_ids ) ) );
		$this->zone_posts->append( $zone_id, $post_ids );
	}

	/**
	 * Makes a zone hold these posts and no others, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneFullException     If the zone would hold more posts than it may.
	 */
	public function set_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );

		$post_ids = $this->clean( $post_ids );

		$this->require_room( $zone_id, $post_ids );
		$this->zone_posts->replace( $zone_id, $post_ids );
	}

	/**
	 * Takes posts out of a zone.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	public function remove_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );
		$this->zone_posts->remove( $zone_id, $this->clean( $post_ids ) );
	}

	/**
	 * Takes every post out of a zone.
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	public function clear_posts( int $zone_id ): void {
		$this->require_zone( $zone_id );
		$this->zone_posts->clear( $zone_id );
	}

	/**
	 * Moves every post in one zone to another, replacing what the other held,
	 * and keeping their order. Does nothing if there are no posts to move. The
	 * zone's post limit doesn't apply: the posts were already in the zone.
	 *
	 * The posts' old home needn't be a zone any more: this is how a zone's
	 * posts follow it when its ID changes.
	 *
	 * @param int $from_zone_id ID the posts are filed under.
	 * @param int $to_zone_id   Zone ID to move them to.
	 *
	 * @throws ZoneNotFoundException If there's no zone to move them to.
	 */
	public function move_posts( int $from_zone_id, int $to_zone_id ): void {
		$this->require_zone( $to_zone_id );

		$post_ids = $this->zone_posts->get_post_ids( $from_zone_id );

		if ( $post_ids ) {
			$this->zone_posts->replace( $to_zone_id, $post_ids );
			$this->zone_posts->clear( $from_zone_id );
		}
	}

	/**
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	private function require_zone( int $zone_id ): void {
		if ( ! $this->zones->find( $zone_id ) ) {
			throw new ZoneNotFoundException( $zone_id );
		}
	}

	/**
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Every post the zone would hold.
	 *
	 * @throws ZoneFullException If that's more than the zone may hold.
	 */
	private function require_room( int $zone_id, array $post_ids ): void {
		$limit = $this->settings->get_post_limit( $zone_id );

		if ( $limit > 0 && count( $post_ids ) > $limit ) {
			throw new ZoneFullException( $limit );
		}
	}

	/**
	 * A post can be in a zone once, so a repeated ID keeps its first place.
	 * Anything that isn't a post ID is left out.
	 *
	 * @param array $post_ids Post IDs, as given.
	 * @return int[] Positive, unique IDs, in the order given.
	 */
	private function clean( array $post_ids ): array {
		$post_ids = array_map( 'intval', $post_ids );

		return array_values(
			array_unique(
				array_filter(
					$post_ids,
					static function ( int $post_id ): bool {
						return $post_id > 0;
					}
				)
			)
		);
	}
}
