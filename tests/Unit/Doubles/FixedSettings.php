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

	public function get_lock_period(): int {
		return 30;
	}

	public function get_detail_defaults(): array {
		return array(
			'description' => '',
			'kicker'      => 'News',
		);
	}
}
