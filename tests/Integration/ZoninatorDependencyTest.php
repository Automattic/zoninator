<?php
declare( strict_types = 1 );

/**
 * Checks that dependencies run one way: the bootstrapper builds every class,
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
		// These two load only when needed.
		require_once dirname( __DIR__, 2 ) . '/src/rest/class-zones-controller.php';
		require_once dirname( __DIR__, 2 ) . '/src/class-abilities.php';

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
	 * Zoninator builds nothing itself, so tests can hand it parts of their own.
	 */
	public function test_zoninator_works_with_parts_it_is_given(): void {
		$settings     = new \Automattic\Zoninator\Settings();
		$taxonomy     = new \Automattic\Zoninator\Taxonomy();
		$capabilities = new \Automattic\Zoninator\Capabilities( $taxonomy );
		$zones        = new \Automattic\Zoninator\Zones( $settings, $taxonomy, $capabilities );
		$zoninator    = new \Zoninator(
			$settings,
			$taxonomy,
			$capabilities,
			new \Automattic\Zoninator\Request( $capabilities ),
			$zones,
			new \Automattic\Zoninator\Locks( $zones, $settings ),
			new \Automattic\Zoninator\Feed( $zones )
		);
		$zone      = $zoninator->insert_zone( 'parts-zone', 'Parts zone' );

		$this->assertSame( 'Parts zone', $zoninator->get_zone( (int) $zone['term_id'] )->name );
		$this->assertSame( 30, $zoninator->get_zone_lock_period() );
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
			\Automattic\Zoninator\Block::class,
			\Automattic\Zoninator\Admin\Screen::class,
			\Automattic\Zoninator\REST\Zones_Controller::class,
			\Automattic\Zoninator\Abilities::class,
			\Zoninator::class,
			\Automattic\Zoninator\Bootstrapper::class,
		);

		return array_combine( $classes, array_map( static fn( $class_name ) => array( $class_name ), $classes ) );
	}
}
