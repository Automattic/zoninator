<?php
/**
 * Zoninator's container.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\DI;

/**
 * Holds objects by class name. It knows nothing about the classes it holds:
 * PluginBootstrapper decides what goes in it.
 *
 * Its get() and has() are shaped like PSR-11's. zoninator_container() returns
 * the plugin's one container, which sites use to reach the object that
 * handles one of Zoninator's hooks, so they can unhook it.
 *
 * @since 1.0.0
 */
final class Container {

	/**
	 * The objects, keyed by class name.
	 *
	 * @var array<class-string, object>
	 */
	private array $objects = array();

	/**
	 * Adds an object, under its class name.
	 *
	 * @template T of object
	 * @param T $instance The object.
	 * @return T The same object, so it can be passed straight to the next constructor.
	 */
	public function add( $instance ): object {
		$this->objects[ get_class( $instance ) ] = $instance;

		return $instance;
	}

	/**
	 * Gets every object, in the order they were added.
	 *
	 * @internal For PluginBootstrapper, which finds the classes that have hooks. Sites use get().
	 *
	 * @return array<class-string, object> The objects, keyed by class name.
	 */
	public function all(): array {
		return $this->objects;
	}

	/**
	 * Gets the object of a class.
	 *
	 * Shaped like PSR-11's get(), but an unknown ID gives a notice and null
	 * rather than an exception, so a mistake in a site's unhooking code can't
	 * stop the site.
	 *
	 * @template T of object
	 * @param class-string<T> $id Class name.
	 * @return T|null The object, or null if the container has no such class.
	 */
	public function get( $id ): ?object {
		if ( ! isset( $this->objects[ $id ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( sprintf( 'The Zoninator container has no %s.', $id ) ), '1.0.0' );
			return null;
		}

		return $this->objects[ $id ];
	}

	/**
	 * Whether the container has an object of this class. Shaped like PSR-11's has().
	 *
	 * @param string $id Class name.
	 * @return bool
	 */
	public function has( $id ): bool {
		return isset( $this->objects[ $id ] );
	}
}
