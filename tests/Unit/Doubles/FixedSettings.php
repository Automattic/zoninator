<?php
declare( strict_types = 1 );

/**
 * Settings for unit tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\LockSettingsInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;

/**
 * The plugin's default settings, with one extra zone detail, as a site might add.
 */
final class FixedSettings implements LockSettingsInterface, ZoneSettingsInterface {

	/**
	 * The most posts a zone may hold, or 0 for no limit.
	 *
	 * @var int
	 */
	public $post_limit = 0;

	public function get_lock_period(): int {
		return 30;
	}

	public function get_post_limit( int $zone_id ): int {
		return $this->post_limit;
	}

	public function get_detail_defaults(): array {
		return array(
			'description' => '',
			'kicker'      => 'News',
		);
	}
}
