<?php
declare( strict_types = 1 );

/**
 * Unit tests for deleting zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneRemover;
use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryLockStore;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZonePostsRepository;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneRemoverTest extends TestCase {

	public function test_remove_deletes_the_zone_with_its_posts_and_its_lock(): void {
		$zones      = new InMemoryZoneRepository();
		$zone_posts = new InMemoryZonePostsRepository();
		$locker     = new ZoneLocker( new InMemoryLockStore(), new FixedSettings(), $zones );
		$zone_id    = (int) $zones->insert( new Zone( null, 'homepage', 'Home page', array() ) )->get_id();
		$zone_posts->replace( $zone_id, array( 5, 6 ) );
		$locker->lock( $zone_id, 11 );

		( new ZoneRemover( $zones, $zone_posts, $locker ) )->remove( $zone_id );

		$this->assertNull( $zones->find( $zone_id ) );
		$this->assertArrayNotHasKey( $zone_id, $zone_posts->post_ids );
		$this->assertNull( $locker->get_blocker_id( $zone_id, 12 ) );
	}

	public function test_remove_refuses_a_zone_that_does_not_exist(): void {
		$zone_posts = new InMemoryZonePostsRepository();
		$zone_posts->replace( 9, array( 5 ) );
		$zones   = new InMemoryZoneRepository();
		$remover = new ZoneRemover( $zones, $zone_posts, new ZoneLocker( new InMemoryLockStore(), new FixedSettings(), $zones ) );

		try {
			$remover->remove( 9 );
			$this->fail( 'Expected the missing zone to be refused.' );
		} catch ( ZoneNotFoundException $exception ) {
			$this->assertSame( array( 5 ), $zone_posts->post_ids[9] );
		}
	}
}
