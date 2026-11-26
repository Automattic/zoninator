<?php
declare( strict_types = 1 );

/**
 * Unit tests for the checks every change to a zone must pass.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneGuard;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Tests\Unit\Doubles\FakeZoneAccess;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryLockStore;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZonePostsRepository;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneGuardTest extends TestCase {

	private const EDITOR = 11;
	private const OTHER  = 12;

	/**
	 * @var FakeZoneAccess
	 */
	private $access;

	/**
	 * @var ZoneLocker
	 */
	private $locker;

	/**
	 * @var InMemoryZoneRepository
	 */
	private $zones;

	/**
	 * @var InMemoryZonePostsRepository
	 */
	private $zone_posts;

	/**
	 * @var GuardedZoneManager
	 */
	private $manager;

	/**
	 * @var GuardedZoneCurator
	 */
	private $curator;

	protected function setUp(): void {
		$settings         = new FixedSettings();
		$this->access     = new FakeZoneAccess();
		$this->zones      = new InMemoryZoneRepository();
		$this->locker     = new ZoneLocker( new InMemoryLockStore(), $settings, $this->zones );
		$this->zone_posts = new InMemoryZonePostsRepository();
		$guard            = new ZoneGuard( $this->access, $this->locker, $this->zones );
		$curator          = new ZoneCurator( $this->zones, $this->zone_posts, $settings );
		$this->manager    = new GuardedZoneManager( new ZoneManager( $this->zones, $settings, $this->zone_posts, $this->locker ), $guard );
		$this->curator    = new GuardedZoneCurator( $curator, $guard );

		$this->access->creators     = array( self::EDITOR );
		$this->access->editors      = array( self::EDITOR, self::OTHER );
		$this->access->curators     = array( self::EDITOR, self::OTHER );
		$this->access->usable_posts = array( 5, 6 );
	}

	public function test_a_user_who_may_can_create_change_and_delete_a_zone(): void {
		$zone_id = (int) $this->manager->create( self::EDITOR, 'homepage' )->get_id();

		$this->assertSame( 'Front page', $this->manager->update( self::EDITOR, $zone_id, array( 'name' => 'Front page' ) )->get_name() );

		$this->manager->remove( self::EDITOR, $zone_id );
		$this->assertNull( $this->zones->find( $zone_id ) );
	}

	public function test_a_user_who_may_not_create_zones_is_refused(): void {
		try {
			$this->manager->create( self::OTHER, 'homepage' );
			$this->fail( 'Expected the user to be refused.' );
		} catch ( AccessDeniedException ) {
			$this->assertNull( $this->zones->find( 1 ) );
		}
	}

	/**
	 * @dataProvider data_edits
	 *
	 * @param string $method   GuardedZoneManager method.
	 * @param array  $more     Its arguments after the user and zone IDs.
	 * @param string $expected Exception expected.
	 * @param bool   $allowed  Whether the user may edit zones.
	 * @param bool   $locked   Whether someone else holds the zone's lock.
	 */
	public function test_an_edit_needs_permission_and_an_unlocked_zone( string $method, array $more, string $expected, bool $allowed, bool $locked ): void {
		$zone_id = (int) $this->manager->create( self::EDITOR, 'homepage', 'Home page' )->get_id();
		if ( ! $allowed ) {
			$this->access->editors = array();
		}
		if ( $locked ) {
			$this->locker->lock( $zone_id, self::OTHER );
		}

		try {
			$this->manager->$method( self::EDITOR, $zone_id, ...$more );
			$this->fail( 'Expected the change to be refused.' );
		} catch ( AccessDeniedException | ZoneLockedException $exception ) {
			$this->assertInstanceOf( $expected, $exception );
		}

		$this->assertSame( 'Home page', $this->zones->find( $zone_id )->get_name() );
	}

	/**
	 * @return array<string, array{string, array, string, bool, bool}>
	 */
	public function data_edits(): array {
		return array(
			'update without permission' => array( 'update', array( array( 'name' => 'New' ) ), AccessDeniedException::class, false, false ),
			'delete without permission' => array( 'remove', array(), AccessDeniedException::class, false, false ),
			'update a locked zone'      => array( 'update', array( array( 'name' => 'New' ) ), ZoneLockedException::class, true, true ),
			'delete a locked zone'      => array( 'remove', array(), ZoneLockedException::class, true, true ),
		);
	}

	/**
	 * A missing zone is reported before anything about the posts, whichever change is asked for.
	 */
	public function test_a_change_to_a_missing_zone_is_refused_as_not_found(): void {
		foreach ( array( 'add_posts', 'set_posts', 'remove_posts' ) as $method ) {
			try {
				$this->curator->$method( self::EDITOR, 9, array( 99 ) );
				$this->fail( 'Expected the missing zone to be refused.' );
			} catch ( ZoneNotFoundException $exception ) {
				$this->assertArrayNotHasKey( 9, $this->zone_posts->post_ids );
			}
		}

		$this->expectException( ZoneNotFoundException::class );
		$this->manager->update( self::EDITOR, 9, array( 'name' => 'New' ) );
	}

	public function test_a_curator_can_add_order_and_remove_posts(): void {
		$zone_id = (int) $this->manager->create( self::EDITOR, 'homepage' )->get_id();

		$this->curator->set_posts( self::EDITOR, $zone_id, array( 5 ) );
		$this->curator->add_posts( self::EDITOR, $zone_id, array( 6 ) );
		$this->curator->remove_posts( self::EDITOR, $zone_id, array( 5 ) );

		$this->assertSame( array( 6 ), $this->zone_posts->post_ids[ $zone_id ] );
	}

	public function test_the_lock_holder_can_still_curate_their_zone(): void {
		$zone_id = (int) $this->manager->create( self::EDITOR, 'homepage' )->get_id();
		$this->locker->lock( $zone_id, self::EDITOR );

		$this->curator->set_posts( self::EDITOR, $zone_id, array( 5 ) );

		$this->assertSame( array( 5 ), $this->zone_posts->post_ids[ $zone_id ] );
	}

	/**
	 * @dataProvider data_refused_curation
	 *
	 * @param string $method   GuardedZoneCurator method.
	 * @param string $expected Exception expected.
	 * @param string $reason   'permission', 'post' or 'lock'.
	 */
	public function test_curating_needs_permission_usable_posts_and_an_unlocked_zone( string $method, string $expected, string $reason ): void {
		$zone_id = (int) $this->manager->create( self::EDITOR, 'homepage' )->get_id();
		$this->curator->set_posts( self::EDITOR, $zone_id, array( 5 ) );
		if ( 'permission' === $reason ) {
			$this->access->curators = array();
		} elseif ( 'lock' === $reason ) {
			$this->locker->lock( $zone_id, self::OTHER );
		}

		try {
			$this->curator->$method( self::EDITOR, $zone_id, 'post' === $reason ? array( 6, 99 ) : array( 6 ) );
			$this->fail( 'Expected the change to be refused.' );
		} catch ( AccessDeniedException | PostNotAllowedException | ZoneLockedException $exception ) {
			$this->assertInstanceOf( $expected, $exception );
		}

		$this->assertSame( array( 5 ), $this->zone_posts->post_ids[ $zone_id ] );
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public function data_refused_curation(): array {
		$cases = array();
		foreach ( array( 'add_posts', 'set_posts', 'remove_posts' ) as $method ) {
			$cases[ "$method without permission" ]     = array( $method, AccessDeniedException::class, 'permission' );
			$cases[ "$method with a post not allowed" ] = array( $method, PostNotAllowedException::class, 'post' );
			$cases[ "$method on a locked zone" ]        = array( $method, ZoneLockedException::class, 'lock' );
		}

		return $cases;
	}
}
