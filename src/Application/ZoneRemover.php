<?php
/**
 * Deletes zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Application;

use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Domain\ZoneRepositoryInterface;

/**
 * Deletes a zone and everything kept for it: its posts' places in it, and its lock.
 *
 * @since 1.0.0
 */
final class ZoneRemover {

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
	 * Zone locks.
	 *
	 * @var ZoneLocker
	 */
	private $locker;

	/**
	 * Constructor.
	 *
	 * @param ZoneRepositoryInterface      $zones      Where the zones are kept.
	 * @param ZonePostsRepositoryInterface $zone_posts Where the posts in each zone are kept.
	 * @param ZoneLocker                   $locker     Zone locks.
	 */
	public function __construct( ZoneRepositoryInterface $zones, ZonePostsRepositoryInterface $zone_posts, ZoneLocker $locker ) {
		$this->zones      = $zones;
		$this->zone_posts = $zone_posts;
		$this->locker     = $locker;
	}

	/**
	 * Deletes a zone. The posts that were in it are left as they are, out of the zone.
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @throws ZoneNotFoundException If there's no such zone.
	 * @throws ZoneNotSavedException If the zone couldn't be deleted. Its posts have already been taken out.
	 */
	public function remove( int $zone_id ): void {
		if ( ! $this->zones->find( $zone_id ) ) {
			throw new ZoneNotFoundException( $zone_id );
		}

		$this->zone_posts->clear( $zone_id );
		$this->zones->delete( $zone_id );
		$this->locker->unlock( $zone_id );
	}
}
