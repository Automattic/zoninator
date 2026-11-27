<?php
/**
 * The settings zone locks use.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

/**
 * Says how long a zone lock is valid for.
 *
 * @since 1.0.0
 */
interface LockSettingsInterface {

	/**
	 * Number of seconds a lock is valid for before it must be renewed.
	 *
	 * @return int
	 */
	public function get_lock_period(): int;
}
