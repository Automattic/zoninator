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
		$settings     = new \Automattic\Zoninator\Infrastructure\WordPress\Settings();
		$taxonomy     = new \Automattic\Zoninator\Infrastructure\WordPress\Taxonomy();
		$capabilities = new \Automattic\Zoninator\Infrastructure\WordPress\Capabilities( $taxonomy );
		$zones        = new \Automattic\Zoninator\Infrastructure\WordPress\Zones( $settings, $taxonomy, $capabilities );
		$zoninator    = new \Zoninator(
			$settings,
			$taxonomy,
			$capabilities,
			new \Automattic\Zoninator\Infrastructure\WordPress\Request( $capabilities ),
			$zones,
			new \Automattic\Zoninator\Infrastructure\WordPress\Locks( $zones, $settings ),
			new \Automattic\Zoninator\Infrastructure\WordPress\Feed( $zones )
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
			\Automattic\Zoninator\Infrastructure\WordPress\Settings::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Taxonomy::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Capabilities::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Request::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Zones::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Locks::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Feed::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Search::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Block::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController::class,
			\Automattic\Zoninator\Infrastructure\WordPress\Abilities::class,
			\Zoninator::class,
			\Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper::class,
		);

		return array_combine( $classes, array_map( static fn( $class_name ) => array( $class_name ), $classes ) );
	}
}
