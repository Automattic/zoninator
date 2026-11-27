<?php
/**
 * Thrown when a zone can't hold more posts.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * A change would leave a zone with more posts than it may hold.
 *
 * @since 1.0.0
 */
final class ZoneFullException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * Constructor.
	 *
	 * @param int $limit The most posts the zone may hold.
	 */
	public function __construct( int $limit ) {
		parent::__construct( sprintf( 'The zone may hold at most %d posts.', $limit ) );
	}
}
