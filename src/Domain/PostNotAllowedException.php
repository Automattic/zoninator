<?php
/**
 * Thrown when a post can't be put in a zone.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Domain;

use RuntimeException;

/**
 * A post can't be put in a zone, or taken out, by this user: it doesn't
 * exist, its post type doesn't support zones, or the user may not read it.
 *
 * @since 1.0.0
 */
final class PostNotAllowedException extends RuntimeException implements ZoninatorExceptionInterface {

	/**
	 * Constructor.
	 *
	 * @param int $post_id The post's ID.
	 */
	public function __construct( int $post_id ) {
		parent::__construct( sprintf( 'Post %d can not be used in a zone by this user.', $post_id ) );
	}
}
