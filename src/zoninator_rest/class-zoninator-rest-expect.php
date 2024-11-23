<?php
/**
 * Mixtape_Expect
 *
 * Asserts about invariants
 *
 * @package Mixtape
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Expect
 */
class Zoninator_REST_Expect {
	/**
	 * Expect a certain class
	 *
	 * @param mixed  $thing The thing to test.
	 * @param string $class_name The class.
	 *
	 * @throws Zoninator_REST_Exception Fail if we got an unexpected class.
	 */
	public static function is_a( $thing, $class_name ) {
		self::is_object( $thing );
		self::that( is_a( $thing, $class_name ), 'Expected ' . $class_name . ', got ' . get_class( $thing ) );
	}

	/**
	 * Expect that thing is an object
	 *
	 * @param mixed $thing The thing.
	 * @throws Zoninator_REST_Exception Throw if not an object.
	 */
	public static function is_object( $thing ) {
		self::that( is_object( $thing ), 'Variable is is not an Object' );
	}

	/**
	 * Express an invariant.
	 *
	 * @param bool   $cond The boolean condition that needs to hold.
	 * @param string $fail_message In case of failure, the reason this failed.
	 *
	 * @throws Zoninator_REST_Exception Fail if condition doesn't hold.
	 */
	public static function that( $cond, $fail_message ) {
		if ( ! $cond ) {
			throw new Zoninator_REST_Exception( esc_html( $fail_message ) );
		}
	}

	/**
	 * This method should be Overridden
	 *
	 * @param string $method The method name.
	 *
	 * @throws Zoninator_REST_Exception To Override this.
	 */
	public static function should_override( $method ) {
		throw new Zoninator_REST_Exception( esc_html( $method . ' should be overridden' ) );
	}
}
