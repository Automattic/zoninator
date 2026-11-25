<?php
/**
 * Thrown when a zone can't hold more posts.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * A change would leave a zone with more posts than it may hold.
 *
 * @since 1.0.0
 */
final class ZoneFullException extends RuntimeException {

	/**
	 * The most posts the zone may hold.
	 *
	 * @var int
	 */
	private $limit;

	/**
	 * Constructor.
	 *
	 * @param int $limit The most posts the zone may hold.
	 */
	public function __construct( int $limit ) {
		parent::__construct( sprintf( 'The zone may hold at most %d posts.', $limit ) );

		$this->limit = $limit;
	}

	/**
	 * Gets the most posts the zone may hold.
	 *
	 * @return int
	 */
	public function get_limit(): int {
		return $this->limit;
	}
}
