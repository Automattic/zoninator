<?php
/**
 * Zoninator's settings.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\LockSettingsInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;

/**
 * The values sites tune with filters: lock periods, results per page, zone
 * details and the most posts a zone may hold.
 *
 * Each getter applies its filter when it's called, so a filter added at any
 * point before then counts.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Settings implements LockSettingsInterface, ZoneSettingsInterface {

	/**
	 * Default number of seconds a zone lock lasts without being renewed.
	 */
	private const LOCK_PERIOD = 30;

	/**
	 * Default number of seconds the Zones screen keeps a zone locked while the user makes no changes, before asking whether they're still working.
	 */
	private const MAX_LOCK_PERIOD = 600;

	/**
	 * Default number of posts in a zone search or the latest content.
	 */
	private const POSTS_PER_PAGE = 10;

	/**
	 * Number of seconds a lock is valid for.
	 *
	 * @return int
	 */
	public function get_lock_period(): int {
		return (int) apply_filters( 'zoninator_zone_lock_period', self::LOCK_PERIOD );
	}

	/**
	 * Max number of seconds for all locks in a session.
	 *
	 * @return int
	 */
	public function get_max_lock_period(): int {
		return (int) apply_filters( 'zoninator_zone_max_lock_period', self::MAX_LOCK_PERIOD );
	}

	/**
	 * Number of posts shown in zone searches and the latest content.
	 *
	 * @return int
	 */
	public function get_posts_per_page(): int {
		return (int) apply_filters( 'zoninator_posts_per_page', self::POSTS_PER_PAGE );
	}

	/**
	 * Zone details saved in the zone term, with their default values.
	 *
	 * @return array
	 */
	public function get_detail_defaults(): array {
		/**
		 * Filters the zone details saved in the zone term, and their default values.
		 *
		 * @since 0.12.0
		 *
		 * @param array $defaults Detail name => default value. Includes 'description'.
		 */
		return (array) apply_filters( 'zoninator_zone_detail_defaults', array( 'description' => '' ) );
	}

	/**
	 * The most posts a zone may hold.
	 *
	 * @param int $zone_id Zone ID.
	 * @return int The limit, or 0 for no limit.
	 */
	public function get_post_limit( int $zone_id ): int {
		/**
		 * Filters the most posts a zone may hold.
		 *
		 * @since 1.0.0
		 *
		 * @param int $limit   The limit. 0, the default, means no limit.
		 * @param int $zone_id Zone ID.
		 */
		return max( 0, (int) apply_filters( 'zoninator_post_count_limit', 0, $zone_id ) );
	}
}
