<?php
declare( strict_types = 1 );

/**
 * A zone posts repository for unit tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;

/**
 * Keeps each zone's post IDs in an array, in order.
 */
final class InMemoryZonePostsRepository implements ZonePostsRepositoryInterface {

	/**
	 * Post IDs in each zone, in order, by zone ID.
	 *
	 * @var array<int, int[]>
	 */
	public $post_ids = array();

	public function get_post_ids( int $zone_id ): array {
		return $this->post_ids[ $zone_id ] ?? array();
	}

	public function append( int $zone_id, array $post_ids ): void {
		$this->post_ids[ $zone_id ] = array_merge( $this->post_ids[ $zone_id ] ?? array(), $post_ids );
	}

	public function replace( int $zone_id, array $post_ids ): void {
		$this->post_ids[ $zone_id ] = $post_ids;
	}

	public function remove( int $zone_id, array $post_ids ): void {
		$this->post_ids[ $zone_id ] = array_values( array_diff( $this->post_ids[ $zone_id ] ?? array(), $post_ids ) );
	}

	public function clear( int $zone_id ): void {
		unset( $this->post_ids[ $zone_id ] );
	}
}
