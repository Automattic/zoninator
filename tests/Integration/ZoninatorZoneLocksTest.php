<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how zone locks are taken, reported and released.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Domain\ZoneLockedException;

class ZoninatorZoneLocksTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var int
	 */
	private $other_id;

	public function set_up(): void {
		parent::set_up();

		$zone           = self::zones()->insert( 'locked-zone', 'Locked zone' );
		$this->zone_id  = (int) $zone['term_id'];
		$this->other_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	public function test_a_lock_held_by_someone_else_reports_their_id(): void {
		self::locker()->lock( $this->zone_id, $this->other_id );

		$this->assertSame( $this->other_id, self::locker()->get_blocker_id( $this->zone_id, get_current_user_id() ) );
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

	public function test_a_lock_held_by_someone_else_cannot_be_taken(): void {
		self::locker()->lock( $this->zone_id, $this->other_id );

		try {
			self::locker()->lock( $this->zone_id, get_current_user_id() );
			$this->fail( 'Expected the zone to stay locked.' );
		} catch ( ZoneLockedException $exception ) {
			$this->assertSame( $this->other_id, $exception->get_holder_id() );
		}

		$this->assertSame( $this->other_id, self::locker()->get_blocker_id( $this->zone_id, get_current_user_id() ) );
	}

	public function test_unlocking_a_zone_releases_the_lock(): void {
		self::locker()->lock( $this->zone_id, $this->other_id );

		self::locker()->unlock( $this->zone_id );

		$this->assertNull( self::locker()->get_blocker_id( $this->zone_id, get_current_user_id() ) );
	}

	public function test_deleting_a_zone_releases_its_lock(): void {
		self::locker()->lock( $this->zone_id, $this->other_id );

		$this->assertTrue( self::zones()->delete( $this->zone_id ) );
		$this->assertFalse( get_transient( self::zones()->get_meta_key( $this->zone_id ) ) );
	}
}
