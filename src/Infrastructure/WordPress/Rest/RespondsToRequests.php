<?php
/**
 * What the zoninator/v1 REST controllers share.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

use WP_Error;
use WP_REST_Response;

/**
 * Hooks the routes, reads request params and builds responses the same way
 * in every zoninator/v1 controller. Errors builds the errors. It keeps no state of its own.
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
