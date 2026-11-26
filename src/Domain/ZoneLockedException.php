<?php
/**
 * Thrown when a zone is locked by someone else.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * A zone can't be locked for a user, because someone else holds its lock.
 *
 * @since 1.0.0
 */
final class ZoneLockedException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * The lock that's in the way.
	 *
	 * @var ZoneLock
	 */
	private $lock;

	/**
	 * Constructor.
	 *
	 * @param ZoneLock $lock The lock that's in the way.
	 */
	public function __construct( ZoneLock $lock ) {
		parent::__construct( 'The zone is locked by another user.' );

		$this->lock = $lock;
	}

	/**
	 * Gets the ID of the user holding the lock.
	 *
	 * @return int
	 */
	public function get_holder_id(): int {
		return $this->lock->get_holder_id();
	}
}
