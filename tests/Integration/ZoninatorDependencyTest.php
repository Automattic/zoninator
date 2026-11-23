<?php
declare( strict_types = 1 );

/**
 * Checks that dependencies run one way: Zoninator builds the other classes,
 * and none of them depends on Zoninator.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use ReflectionClass;
use ReflectionNamedType;

final class ZoninatorDependencyTest extends TestCase {

	/**
	 * @dataProvider data_classes
	 *
	 * @param string $class_name Class to check.
	 */
	public function test_class_does_not_take_zoninator( string $class_name ): void {
		if ( ! class_exists( $class_name ) ) {
			require_once dirname( __DIR__, 2 ) . '/src/rest/class-zones-controller.php';
		}

		$constructor = ( new ReflectionClass( $class_name ) )->getConstructor();
		$parameters  = $constructor ? $constructor->getParameters() : array();

		// A class with no constructor arguments has nothing to check.
		$this->addToAssertionCount( 1 );

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();
			$this->assertNotNull( $type, "{$class_name}::__construct() has an untyped \${$parameter->getName()}." );
			$this->assertInstanceOf( ReflectionNamedType::class, $type );
			$this->assertNotSame( 'Zoninator', $type->getName(), "{$class_name} takes Zoninator." );
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_classes(): array {
		$classes = array(
			\Automattic\Zoninator\Settings::class,
			\Automattic\Zoninator\Taxonomy::class,
			\Automattic\Zoninator\Capabilities::class,
			\Automattic\Zoninator\Request::class,
			\Automattic\Zoninator\Zones::class,
			\Automattic\Zoninator\Locks::class,
			\Automattic\Zoninator\Feed::class,
			\Automattic\Zoninator\Admin\Screen::class,
			\Automattic\Zoninator\Admin\Ajax::class,
			\Automattic\Zoninator\REST\Zones_Controller::class,
		);

		return array_combine( $classes, array_map( static fn( $class_name ) => array( $class_name ), $classes ) );
	}
}
