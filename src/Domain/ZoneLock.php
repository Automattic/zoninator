<?php
/**
 * A lock on a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * A lock on a zone: who holds it, and who it keeps out.
 *
 * Only one person may edit a zone at a time. Whoever is editing holds the
 * zone's lock, which keeps everyone else out but never its holder.
 *
 * @since 1.0.0
 */
final class ZoneLock {

	/**
	 * Seconds a lock outlasts the lock period, so a lock that's renewed every
	 * period doesn't lapse in the moment before the renewal arrives.
	 */
	public const GRACE_SECONDS = 1;

	/**
	 * Constructor.
	 *
	 * @param int $holder_id ID of the user holding the lock.
	 */
	public function __construct( private readonly int $holder_id ) {
	}

	/**
	 * Gets the ID of the user holding the lock.
	 *
	 * @return int
	 */
	public function get_holder_id(): int {
		return $this->holder_id;
	}

	/**
	 * Whether the lock keeps a user out of the zone. It never keeps out its holder.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function blocks( int $user_id ): bool {
		return $this->holder_id !== $user_id;
	}

	/**
	 * How long a lock lasts when it's taken or renewed.
	 *
	 * @param int $lock_period Seconds between renewals.
	 * @return int Seconds.
	 */
	public static function duration( int $lock_period ): int {
		return $lock_period + self::GRACE_SECONDS;
	}
}
