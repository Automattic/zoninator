<?php
declare( strict_types = 1 );

/**
 * Integration tests of what zone locks do in WordPress: they're kept as
 * transients, and a deleted zone's lock goes with them. ZoneLockerTest covers
 * the rules, and TransientLockStoreTest holds the store to them.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class ZoninatorZoneLocksTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var int
	 */
	private $other_id;

	public function setUp(): void {
		parent::setUp();

		$this->zone_id  = self::insert_zone( 'locked-zone', 'Locked zone' );
		$this->other_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	public function test_your_own_lock_does_not_lock_you_out(): void {
		self::locker()->lock( $this->zone_id, get_current_user_id() );

		$this->assertNull( self::locker()->get_blocker_id( $this->zone_id, get_current_user_id() ) );
		$this->assertSame( get_current_user_id(), (int) get_transient( self::zones()->get_meta_key( $this->zone_id ) ) );
	}

	public function test_a_lock_lasts_one_second_longer_than_the_lock_period(): void {
		$expiration = null;
		add_filter(
			'expiration_of_transient_' . self::zones()->get_meta_key( $this->zone_id ),
			static function ( $value ) use ( &$expiration ) {
				$expiration = $value;
				return $value;
			}
		);

		self::locker()->lock( $this->zone_id, $this->other_id );

		$this->assertSame( self::settings()->get_lock_period() + 1, $expiration );
	}

	public function test_deleting_a_zone_releases_its_lock(): void {
		self::locker()->lock( $this->zone_id, $this->other_id );

		self::delete_zone( $this->zone_id );

		$this->assertFalse( get_transient( self::zones()->get_meta_key( $this->zone_id ) ) );
	}
}
