<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how zone locks are taken, reported and released.
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

	public function set_up(): void {
		parent::set_up();

		$zone           = Zoninator()->insert_zone( 'locked-zone', 'Locked zone' );
		$this->zone_id  = (int) $zone['term_id'];
		$this->other_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	public function test_a_lock_held_by_someone_else_reports_their_id(): void {
		$this->assertNull( Zoninator()->lock_zone( $this->zone_id, $this->other_id ) );

		$this->assertSame( $this->other_id, Zoninator()->is_zone_locked( $this->zone_id ) );
		$this->assertSame( $this->other_id, Zoninator()->is_zone_locked( 'locked-zone' ) );
	}

	public function test_your_own_lock_does_not_lock_you_out(): void {
		Zoninator()->lock_zone( $this->zone_id );

		$this->assertFalse( Zoninator()->is_zone_locked( $this->zone_id ) );
		$this->assertSame( get_current_user_id(), (int) get_transient( Zoninator()->get_zone_meta_key( $this->zone_id ) ) );
	}

	public function test_a_lock_lasts_one_second_longer_than_the_lock_period(): void {
		$expiration = null;
		add_filter(
			'expiration_of_transient_' . Zoninator()->get_zone_meta_key( $this->zone_id ),
			static function ( $value ) use ( &$expiration ) {
				$expiration = $value;
				return $value;
			}
		);

		Zoninator()->lock_zone( $this->zone_id, $this->other_id );

		$this->assertSame( Zoninator()->get_zone_lock_period() + 1, $expiration );
	}

	public function test_unlock_zone_releases_the_lock(): void {
		Zoninator()->lock_zone( $this->zone_id, $this->other_id );

		$this->assertNull( Zoninator()->unlock_zone( $this->zone_id ) );
		$this->assertFalse( Zoninator()->is_zone_locked( $this->zone_id ) );
	}

	public function test_a_missing_zone_cannot_be_locked(): void {
		$this->assertFalse( Zoninator()->lock_zone( 'no-such-zone', $this->other_id ) );
		$this->assertFalse( Zoninator()->unlock_zone( 'no-such-zone' ) );
		$this->assertFalse( Zoninator()->is_zone_locked( 'no-such-zone' ) );
	}
}
