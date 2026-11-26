<?php
/**
 * Thrown when there's no such zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * There's no zone with the ID that was asked for.
 *
 * @since 1.0.0
 */
final class ZoneNotFoundException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * Constructor.
	 *
	 * @param int $zone_id The ID that was asked for.
	 */
	public function __construct( int $zone_id ) {
		parent::__construct( sprintf( 'There is no zone with the ID %d.', $zone_id ) );
	}
}
