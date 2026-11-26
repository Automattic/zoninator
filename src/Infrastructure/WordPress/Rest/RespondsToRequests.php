<?php
/**
 * What the zoninator/v1 REST controllers share.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\InvalidZoneException;
use Automattic\Zoninator\Domain\PostNotAllowedException;
use Automattic\Zoninator\Domain\ZoneFullException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoneNotSavedException;
use Throwable;
use WP_Error;
use WP_REST_Response;

/**
 * Reads request params and builds responses the same way in every
 * zoninator/v1 controller. It keeps no state of its own.
 *
 * @since 1.0.0
 */
trait RespondsToRequests {

	/**
	 * The namespace every route is registered under.
	 *
	 * @var string
	 */
	private static $rest_namespace = 'zoninator/v1';

	public function is_numeric( $item ) {
		// see https://github.com/WP-API/WP-API/issues/1520 on why we do not use is_numeric directly.
		return is_numeric( $item );
	}

	private function get_zone_id_param() {
		return array(
			'zone_id' => array(
				'type'              => 'integer',
				'validate_callback' => array( $this, 'is_numeric' ),
				'sanitize_callback' => 'absint',
				'required'          => true,
			),
		);
	}

	/**
	 * @param WP_REST_Request $rest_request
	 * @param string          $key               Parameter name.
	 * @param string          $default_value
	 * @param string          $sanitize_callback
	 *
	 * @return array|mixed|null|string
	 */
	private function get_param( $rest_request, $key, $default_value = '', $sanitize_callback = '' ) {
		$value = $rest_request->get_param( $key );
		$value = empty( $value ) ? $default_value : $value;

		if ( is_callable( $sanitize_callback ) ) {
			$value = ( is_array( $value ) ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	/**
	 * Respond with a 200.
	 *
	 * @param mixed $data Body.
	 * @return WP_REST_Response
	 */
	private function ok( $data ) {
		return $this->respond( $data, 200 );
	}

	/**
	 * Respond with a 201.
	 *
	 * @param mixed $data Body.
	 * @return WP_REST_Response
	 */
	private function created( $data ) {
		return $this->respond( $data, 201 );
	}

	/**
	 * An error with a code and a 400 status.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function bad_request( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * Turns what the Application layer threw into this API's response.
	 *
	 * @param Throwable $exception What was thrown.
	 * @param int       $zone_id   The zone being changed, for the locked error's data.
	 * @return WP_Error
	 *
	 * @throws Throwable If it isn't one of Zoninator's own exceptions.
	 */
	private function error_response( Throwable $exception, $zone_id ) {
		if ( $exception instanceof ZoneLockedException ) {
			$locking_user = get_userdata( $exception->get_holder_id() );

			return new WP_Error(
				ErrorCodes::ZONE_LOCKED,
				sprintf(
					/* translators: %s: the locking user's display name, or "another user". */
					__( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
					$locking_user ? $locking_user->display_name : __( 'another user', 'zoninator' )
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
			return $this->bad_request( ErrorCodes::ZONE_FULL, __( 'Sorry, another item cannot be added to this zone.', 'zoninator' ) );
		}

		if ( $exception instanceof PostNotAllowedException ) {
			return $this->bad_request( ErrorCodes::INVALID_POST_ID, __( 'Invalid post ID.', 'zoninator' ) );
		}

		if ( $exception instanceof AccessDeniedException ) {
			return new WP_Error( ErrorCodes::PERMISSION_DENIED, __( "Sorry, you're not supposed to do that...", 'zoninator' ), array( 'status' => rest_authorization_required_code() ) );
		}

		if ( $exception instanceof InvalidZoneException ) {
			return $this->bad_request( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
		}

		if ( $exception instanceof ZoneNotSavedException ) {
			return $this->bad_request( $exception->get_error_code(), $exception->getMessage() );
		}

		// Not one of ours, so not something to turn into a response.
		throw $exception;
	}

	/**
	 * Create a 404 response
	 *
	 * @return WP_Error
	 */
	private function zone_not_found() {
		return new WP_Error( ErrorCodes::ZONE_NOT_FOUND, __( 'Zone not found.', 'zoninator' ), array( 'status' => 404 ) );
	}

	/**
	 * Wrap data in a response, unless it's already one.
	 *
	 * @param mixed $data   Body.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	private function respond( $data, $status ) {
		if ( $data instanceof WP_REST_Response ) {
			return $data;
		}

		return new WP_REST_Response( $data, $status );
	}
}
