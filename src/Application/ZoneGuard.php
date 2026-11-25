<?php
/**
 * Checks that a user may change a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneAccessInterface;
use Automattic\Zoninator\Domain\ZoneLockedException;

/**
 * The checks every change to a zone must pass when it's made for a user:
 * the user may make it, may use each post, and isn't kept out by someone
 * else's lock.
 *
 * @since 1.0.0
 */
final class ZoneGuard {

	/**
	 * Says what a user may do.
	 *
	 * @var ZoneAccessInterface
	 */
	private $access;

	/**
	 * Zone locks.
	 *
	 * @var ZoneLocker
	 */
	private $locker;

	/**
	 * Constructor.
	 *
	 * @param ZoneAccessInterface $access Says what a user may do.
	 * @param ZoneLocker          $locker Zone locks.
	 */
	public function __construct( ZoneAccessInterface $access, ZoneLocker $locker ) {
		$this->access = $access;
		$this->locker = $locker;
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
	 * @throws ZoneLockedException   If someone else holds the zone's lock.
	 */
	public function check_edit( int $user_id, int $zone_id ): void {
		if ( ! $this->access->can_edit_zone( $user_id, $zone_id ) ) {
			throw new AccessDeniedException();
		}

		$this->locker->check_unlocked( $zone_id, $user_id );
	}

	/**
	 * Checks that a user may add these posts to a zone, order them in it, or take them out.
	 *
	 * @param int   $user_id  User ID.
	 * @param int   $zone_id  Zone ID.
	 * @param int[] $post_ids Post IDs.
	 *
	 * @throws AccessDeniedException   If they may not curate zones.
	 * @throws PostNotAllowedException If they may not use one of the posts.
	 * @throws ZoneLockedException     If someone else holds the zone's lock.
	 */
	public function check_curate( int $user_id, int $zone_id, array $post_ids ): void {
		if ( ! $this->access->can_curate_zones( $user_id ) ) {
			throw new AccessDeniedException();
		}

		foreach ( $post_ids as $post_id ) {
			if ( ! $this->access->can_use_post( $user_id, (int) $post_id ) ) {
				throw new PostNotAllowedException( (int) $post_id );
			}
		}

		$this->locker->check_unlocked( $zone_id, $user_id );
	}
}
