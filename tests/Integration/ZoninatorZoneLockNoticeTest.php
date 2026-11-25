<?php
declare( strict_types = 1 );

/**
 * Integration tests for the notice shown when a zone is locked.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;


class ZoninatorZoneLockNoticeTest extends TestCase {

	public function test_lock_notice_names_the_user_without_their_email(): void {
		$locker_id = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'display_name' => 'Zone Locker',
				'user_email'   => 'zoninator-locker@example.com',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$zone = self::insert_zone( 'locked-zone', 'Locked zone' );
		self::locker()->lock( (int) $zone['term_id'], $locker_id );

		$output = self::render_zone_editor( (int) $zone['term_id'] );

		$this->assertStringContainsString( 'Zone Locker', $output );
		$this->assertStringNotContainsString( 'zoninator-locker@example.com', $output );
	}
}
