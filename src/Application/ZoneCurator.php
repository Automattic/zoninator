<?php
/**
 * Curates the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\PostIds;
use Automattic\Zoninator\Domain\ZoneFullException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;
use InvalidArgumentException;

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
	 * Constructor.
	 *
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 * @param ZoneSettingsInterface        $settings   Says how many posts a zone may hold.
	 */
	public function __construct(
		private readonly ZoneRepositoryInterface $zones,
		private readonly ZonePostsRepositoryInterface $zone_posts,
		private readonly ZoneSettingsInterface $settings,
	) {
	}

	/**
	 * Adds posts to the end of a zone, in the order given. A post already in
	 * the zone stays where it is.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneFullException     If the zone would hold more posts than it may.
	 * @throws InvalidArgumentException If a post ID isn't an integer or a string of digits.
	 */
	public function add_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );

		// A post already in the zone keeps its place, so it's neither added again nor counted twice.
		$current  = $this->zone_posts->get_post_ids( $zone_id );
		$post_ids = array_values( array_diff( $this->clean( $post_ids ), $current ) );

		if ( ! $post_ids ) {
			return;
		}

		$this->require_room( $zone_id, array_merge( $current, $post_ids ) );
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
	 * @throws InvalidArgumentException If a post ID isn't an integer or a string of digits.
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
	 * @throws InvalidArgumentException If a post ID isn't an integer or a string of digits.
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
	 * @throws ZoneFullException If that's more than the zone may hold, and more than it holds now.
	 */
	private function require_room( int $zone_id, array $post_ids ): void {
		$limit = $this->settings->get_post_limit( $zone_id );

		// A zone already past its limit can still be reordered or trimmed; it just can't grow.
		if ( $limit > 0 && count( $post_ids ) > $limit && count( $post_ids ) > count( $this->zone_posts->get_post_ids( $zone_id ) ) ) {
			throw new ZoneFullException( $limit );
		}
	}

	/**
	 * A post can be in a zone once, so a repeated ID keeps its first place.
	 * An ID below 1 is no post's, and is left out.
	 *
	 * @param array $post_ids Post IDs, as given.
	 * @return int[] Positive, unique IDs, in the order given.
	 *
	 * @throws InvalidArgumentException If one isn't an integer or a string of digits.
	 */
	private function clean( array $post_ids ): array {
		return array_values(
			array_unique(
				array_filter(
					PostIds::from( $post_ids ),
					static function ( int $post_id ): bool {
						return $post_id > 0;
					}
				)
			)
		);
	}
}
