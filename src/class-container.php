<?php
/**
 * Zoninator's container.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

use Closure;

/**
 * Holds objects by class name. It knows nothing about the classes it holds:
 * Bootstrapper decides what goes in it.
 *
 * Its get() and has() are shaped like PSR-11's. zoninator_container() returns
 * the plugin's one container, which sites use to reach the object that
 * handles one of Zoninator's hooks, so they can unhook it.
 *
 * @since 1.0.0
 */
final class Container {

	/**
	 * Objects added or built so far, keyed by class name.
	 *
	 * @var array<class-string, object>
	 */
	private $objects = array();

	/**
	 * Builders for objects made only when first asked for, keyed by class name.
	 *
	 * @var array<class-string, Closure>
	 */
	private $factories = array();

	/**
	 * Adds an object, under its class name.
	 *
	 * @template T of object
	 * @param T $instance The object.
	 * @return T The same object, so it can be passed straight to the next constructor.
	 */
	public function add( $instance ) {
		$this->objects[ get_class( $instance ) ] = $instance;

		return $instance;
	}

	/**
	 * Adds a builder for an object only some requests need. get() calls it once, the first time it's asked.
	 *
	 * @param class-string $id      Class name.
	 * @param Closure      $factory Returns the object.
	 */
	public function factory( $id, Closure $factory ) {
		$this->factories[ $id ] = $factory;
	}

	/**
	 * Gets the object of a class, building it first if it has a factory.
	 *
	 * Shaped like PSR-11's get(), but an unknown ID gives a notice and null
	 * rather than an exception, so a mistake in a site's unhooking code can't
	 * stop the site.
	 *
	 * @template T of object
	 * @param class-string<T> $id Class name.
	 * @return T|null The object, or null if the container has no such class.
	 */
	public function get( $id ) {
		if ( ! isset( $this->objects[ $id ] ) && isset( $this->factories[ $id ] ) ) {
			$this->objects[ $id ] = ( $this->factories[ $id ] )();
		}

		if ( ! isset( $this->objects[ $id ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( sprintf( 'The Zoninator container has no %s.', $id ) ), '1.0.0' );
			return null;
		}

		return $this->objects[ $id ];
	}

	/**
	 * Whether the container has, or can build, an object of this class. Shaped like PSR-11's has().
	 *
	 * @param string $id Class name.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->objects[ $id ] ) || isset( $this->factories[ $id ] );
	}
}
