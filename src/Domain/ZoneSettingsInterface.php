<?php
/**
 * The settings zones use.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

/**
 * Says which details every zone has.
 *
 * @since 1.0.0
 */
interface ZoneSettingsInterface {

	/**
	 * The details every zone has, with their default values.
	 *
	 * @return array<string, mixed> Default values, by detail name.
	 */
	public function get_detail_defaults(): array;
}
