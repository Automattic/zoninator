<?php
/**
 * Curates the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;

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
	 * Constructor.
	 *
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 */
	public function __construct( ZoneRepositoryInterface $zones, ZonePostsRepositoryInterface $zone_posts ) {
		$this->zones      = $zones;
		$this->zone_posts = $zone_posts;
	}

	/**
	 * Adds posts to the end of a zone, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	public function add_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );
		$this->zone_posts->append( $zone_id, $this->clean( $post_ids ) );
	}

	/**
	 * Makes a zone hold these posts and no others, in the order given.
	 *
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	public function set_posts( int $zone_id, array $post_ids ): void {
		$this->require_zone( $zone_id );
		$this->zone_posts->replace( $zone_id, $this->clean( $post_ids ) );
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
