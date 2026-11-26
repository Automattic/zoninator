<?php
/**
 * What the zoninator/v1 REST controllers share.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use WP_Error;
use WP_REST_Response;

/**
 * Hooks the routes and builds responses the same way in every zoninator/v1
 * controller. Errors builds the errors. It keeps no state of its own.
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

	/**
	 * Hooks the controller's routes. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * The zone ID arg. With a type and no callbacks, core checks it's an integer and makes it one.
	 *
	 * @return array
	 */
	private function get_zone_id_param() {
		return array(
			'zone_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
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
