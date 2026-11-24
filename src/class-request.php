<?php
/**
 * Reading and checking the Zones screen's requests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

/**
 * Reads request variables, sanitises and validates them, and checks nonces
 * and capabilities before a request handler does any work.
 *
 * @since 0.12.0
 */
final class Request {

	/**
	 * Prefix of every nonce action.
	 */
	public const NONCE_PREFIX = 'zone-nonce';

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param Capabilities $capabilities The capability checks.
	 */
	public function __construct( Capabilities $capabilities ) {
		$this->capabilities = $capabilities;
	}

	/**
	 * Gets a value from an object or array, or a default if it's empty.
	 *
	 * @param string          $key               Property or key.
	 * @param object|array    $source            Where to look.
	 * @param mixed           $default_value     Value to use if it's missing or empty.
	 * @param callable|string $sanitize_callback Callback to sanitise the value, or each item of an array.
	 * @return mixed
	 */
	public static function get_value_or_default( $key, $source, $default_value = '', $sanitize_callback = '' ) {
		if ( is_object( $source ) ) {
			$value = empty( $source->$key ) ? $default_value : $source->$key;
		} elseif ( is_array( $source ) ) {
			$value = empty( $source[ $key ] ) ? $default_value : $source[ $key ];
		} else {
			$value = $default_value;
		}

		if ( is_callable( $sanitize_callback ) ) {
			$value = is_array( $value ) ? array_map( $sanitize_callback, $value ) : call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	public function get_request_var( $key, $default_value = '', $sanitize_callback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::get_value_or_default( $key, $_REQUEST, $default_value, $sanitize_callback );
	}

	public function get_get_var( $key, $default_value = '', $sanitize_callback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::get_value_or_default( $key, $_GET, $default_value, $sanitize_callback );
	}

	public function get_post_var( $key, $default_value = '', $sanitize_callback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return self::get_value_or_default( $key, $_POST, $default_value, $sanitize_callback );
	}

	/**
	 * Sanitises a zone name or description from a form.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function sanitize_value( $value ) {
		// htmlspecialchars(), not htmlentities(): core decodes named entities such as
		// &eacute; when it saves a term, which broke the serialised zone details.
		return htmlspecialchars( stripslashes( $value ) );
	}

	public static function is_valid_date_filter( $date ) {
		return preg_match( '/(\d{4})-(\d{2})-(\d{2})/', $date );
	}

	public static function is_valid_category_filter( $cat ) {
		return $cat && get_term_by( 'id', $cat, 'category' );
	}

	/**
	 * Gets the nonce action for one of the Zones screen's actions.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	public static function get_nonce_key( $action ) {
		return sprintf( '%s-%s', self::NONCE_PREFIX, $action );
	}

	/**
	 * Stops the request unless it has a valid nonce for the action.
	 *
	 * @param string $action Action.
	 */
	public function verify_nonce( $action ) {
		$action = self::get_nonce_key( $action );
		$nonce  = $this->get_request_var( $action );

		if ( empty( $nonce ) ) {
			$nonce = $this->get_request_var( '_wpnonce' );
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			$this->unauthorized_access();
		}
	}

	/**
	 * Stops the request unless the current user may take the action.
	 *
	 * @param string   $action  'insert', 'update' or 'delete'. Anything else checks for managing zones.
	 * @param int|null $zone_id Zone ID, for 'update' and 'delete'.
	 */
	public function verify_access( $action = '', $zone_id = null ) {
		// Zone locks aren't checked on writes yet. See https://github.com/Automattic/zoninator/issues/215.
		if ( ! $this->capabilities->check( $action, $zone_id ) ) {
			$this->unauthorized_access();
		}
	}

	public function unauthorized_access() {
		wp_die( esc_html__( "Sorry, you're not supposed to do that...", 'zoninator' ) );
	}
}
