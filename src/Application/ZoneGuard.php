<?php
/**
 * Checks that a user may change a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\PostIds;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneAccessInterface;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;
use InvalidArgumentException;

/**
 * The checks every change to a zone must pass when it's made for a user:
 * the user may make it, the zone exists, the user may use each post they put
 * in it, and no one else's lock keeps them out.
 *
 * @since 1.0.0
 */
final class ZoneGuard {

	/**
	 * Constructor.
	 *
	 * @param ZoneAccessInterface          $access     Says what a user may do.
	 * @param ZoneLocker                   $locker     Zone locks.
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 */
	public function __construct(
		private readonly ZoneAccessInterface $access,
		private readonly ZoneLocker $locker,
		private readonly ZoneRepositoryInterface $zones,
		private readonly ZonePostsRepositoryInterface $zone_posts,
	) {
	}

	/**
	 * Checks that a user may create zones.
	 *
	 * @param int $user_id User ID.
	 *
	 * @throws AccessDeniedException If they may not.
	 */
	public function check_create( int $user_id ): void {
		if ( ! $this->access->can_create_zones( $user_id ) ) {
			throw new AccessDeniedException();
		}
	}

	/**
	 * Checks that a user may change or delete a zone.
	 *
	 * @param int $user_id User ID.
	 * @param int $zone_id Zone ID.
	 *
	 * @throws AccessDeniedException If they may not.
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneLockedException   If someone else holds the zone's lock.
	 */
	public function check_edit( int $user_id, int $zone_id ): void {
		if ( ! $this->access->can_edit_zone( $user_id, $zone_id ) ) {
			throw new AccessDeniedException();
		}

		$this->check_exists( $zone_id );
		$this->locker->check_unlocked( $zone_id, $user_id );
	}

	/**
	 * Checks that a user may add these posts to a zone, order them in it, or take them out.
	 *
	 * The user must be able to use each post they put in the zone. A post
	 * already there isn't being put in, so it may keep its place, move or be
	 * taken out whether or not the user may use it. Otherwise one such post
	 * would stop them ordering the zone, and they couldn't take it out.
	 *
	 * @param int   $user_id  User ID.
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws AccessDeniedException   If they may not curate zones.
	 * @throws ZoneNotFoundException   If there's no such zone.
	 * @throws PostNotAllowedException If they may not use one of the posts, and it isn't in the zone.
	 * @throws ZoneLockedException     If someone else holds the zone's lock.
	 * @throws InvalidArgumentException If a post ID isn't an integer or a string of digits.
	 */
	public function check_curate( int $user_id, int $zone_id, array $post_ids ): void {
		if ( ! $this->access->can_curate_zones( $user_id ) ) {
			throw new AccessDeniedException();
		}

		$this->check_exists( $zone_id );

		foreach ( array_diff( PostIds::from( $post_ids ), $this->zone_posts->get_post_ids( $zone_id ) ) as $post_id ) {
			if ( ! $this->access->can_use_post( $user_id, $post_id ) ) {
				throw new PostNotAllowedException( $post_id );
			}
		}

		$this->locker->check_unlocked( $zone_id, $user_id );
	}

	/**
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 */
	private function check_exists( int $zone_id ): void {
		if ( ! $this->zones->find( $zone_id ) ) {
			throw new ZoneNotFoundException( $zone_id );
		}
	}
}
