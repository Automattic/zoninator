<?php
/**
 * The errors Zoninator gives back.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneFullException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ErrorCodes;
use WP_Error;

/**
 * Builds the WP_Error for each thing that can go wrong, with its code,
 * message and HTTP status, so the REST API, the abilities and the Zones
 * screen all say the same thing.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Errors {

	/**
	 * Turns what the Application layer threw into its error.
	 *
	 * @param ZoninatorExceptionInterface $exception What was thrown.
	 * @param int                         $zone_id   The zone being changed, for the locked error's data.
	 * @return WP_Error
	 *
	 * @throws ZoninatorExceptionInterface If it's an exception with no error of its own yet.
	 */
	public function from_exception( ZoninatorExceptionInterface $exception, int $zone_id = 0 ): WP_Error {
		if ( $exception instanceof ZoneLockedException ) {
			$holder = get_userdata( $exception->get_holder_id() );

			return new WP_Error(
				ErrorCodes::ZONE_LOCKED,
				sprintf(
					/* translators: %s: the locking user's display name, or "another user". */
					__( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
					$holder ? $holder->display_name : __( 'another user', 'zoninator' )
				),
				array(
					'status'  => 400,
					'zone_id' => $zone_id,
					'blocked' => true,
				)
			);
		}

		if ( $exception instanceof ZoneNotFoundException ) {
			return $this->zone_not_found();
		}

		if ( $exception instanceof ZoneFullException ) {
			return new WP_Error( ErrorCodes::ZONE_FULL, __( 'Sorry, another item cannot be added to this zone.', 'zoninator' ), array( 'status' => 400 ) );
		}

		if ( $exception instanceof PostNotAllowedException ) {
			return $this->invalid_post_id();
		}

		if ( $exception instanceof AccessDeniedException ) {
			return $this->permission_denied();
		}

		if ( $exception instanceof InvalidZoneException ) {
			return new WP_Error( ErrorCodes::ZONE_EMPTY_SLUG, __( 'Slug is a required field.', 'zoninator' ), array( 'status' => 400 ) );
		}

		if ( $exception instanceof ZoneNotSavedException ) {
			return new WP_Error( $exception->get_error_code(), $exception->getMessage(), array( 'status' => 400 ) );
		}

		// A new exception with no error of its own yet. Let it be seen, not guessed at.
		throw $exception;
	}

	/**
	 * The error for a user who may not do what they asked: 401 when logged out, 403 when logged in.
	 *
	 * @return WP_Error
	 */
	public function permission_denied(): WP_Error {
		return new WP_Error( ErrorCodes::PERMISSION_DENIED, __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * The error for a zone ID that isn't a zone.
	 *
	 * @return WP_Error
	 */
	public function zone_not_found(): WP_Error {
		return new WP_Error( ErrorCodes::ZONE_NOT_FOUND, __( 'Zone not found.', 'zoninator' ), array( 'status' => 404 ) );
	}

	/**
	 * The error for a post the user may not put in a zone, or one that doesn't exist.
	 *
	 * @return WP_Error
	 */
	public function invalid_post_id(): WP_Error {
		return new WP_Error( ErrorCodes::INVALID_POST_ID, __( 'Invalid post ID.', 'zoninator' ), array( 'status' => 400 ) );
	}

	/**
	 * The error for a zone ID of 0.
	 *
	 * @return WP_Error
	 */
	public function zone_id_required(): WP_Error {
		return new WP_Error( ErrorCodes::ZONE_ID_REQUIRED, __( 'Zone ID is required.', 'zoninator' ), array( 'status' => 400 ) );
	}

	/**
	 * The error for a zone's feed that a site's filter refused to give.
	 *
	 * @param string $message The filter's own message.
	 * @return WP_Error
	 */
	public function zone_feed_error( string $message ): WP_Error {
		return new WP_Error( ErrorCodes::ZONE_FEED_ERROR, $message, array( 'status' => 400 ) );
	}

	/**
	 * The error for when the zones can't be read at all.
	 *
	 * @return WP_Error
	 */
	public function zones_read_error(): WP_Error {
		return new WP_Error( ErrorCodes::ZONES_READ_ERROR, __( 'Zones could not be read.', 'zoninator' ), array( 'status' => 400 ) );
	}
}
