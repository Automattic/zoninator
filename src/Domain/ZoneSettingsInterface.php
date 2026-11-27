<?php
/**
 * The settings zones use.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Says which details every zone has, and how many posts a zone may hold.
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

	/**
	 * The most posts a zone may hold.
	 *
	 * @param int $zone_id Zone ID.
	 * @return int The limit, or 0 for no limit.
	 */
	public function get_post_limit( int $zone_id ): int;
}
