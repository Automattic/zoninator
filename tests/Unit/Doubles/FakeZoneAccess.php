<?php
declare( strict_types = 1 );

/**
 * Zone access rules for unit tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\ZoneAccessInterface;

/**
 * Lets the users and posts a test names through, and no others.
 */
final class FakeZoneAccess implements ZoneAccessInterface {

	/**
	 * IDs of users who may create zones.
	 *
	 * @var int[]
	 */
	public $creators = array();

	/**
	 * IDs of users who may edit any zone.
	 *
	 * @var int[]
	 */
	public $editors = array();

	/**
	 * IDs of users who may curate zones.
	 *
	 * @var int[]
	 */
	public $curators = array();

	/**
	 * IDs of posts anyone may use.
	 *
	 * @var int[]
	 */
	public $usable_posts = array();

	public function can_create_zones( int $user_id ): bool {
		return in_array( $user_id, $this->creators, true );
	}

	public function can_edit_zone( int $user_id, int $zone_id ): bool {
		return in_array( $user_id, $this->editors, true );
	}

	public function can_curate_zones( int $user_id ): bool {
		return in_array( $user_id, $this->curators, true );
	}

	public function can_use_post( int $user_id, int $post_id ): bool {
		return in_array( $post_id, $this->usable_posts, true );
	}
}
