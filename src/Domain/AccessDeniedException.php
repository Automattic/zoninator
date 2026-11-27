<?php
/**
 * Thrown when a user may not do what was asked.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * The user may not make this change to zones.
 *
 * @since 1.0.0
 */
final class AccessDeniedException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'The user may not make this change.' );
	}
}
