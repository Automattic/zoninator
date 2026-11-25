<?php
declare( strict_types = 1 );

/**
 * Checks that dependencies run one way: the bootstrapper builds every class,
 * and none of them depends on Zoninator.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Infrastructure\WordPress\Abilities;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Block;
use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint;
use Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper;
use Automattic\Zoninator\Infrastructure\WordPress\Request;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;
use Automattic\Zoninator\Infrastructure\WordPress\Search;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\TransientLockStore;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use ReflectionClass;
use ReflectionNamedType;
use Zoninator;

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
		$settings     = new Settings();
		$taxonomy     = new Taxonomy();
		$capabilities = new Capabilities( $taxonomy );
		$locker       = new ZoneLocker( new TransientLockStore(), $settings );
		$zones        = new Zones( $settings, $taxonomy, $capabilities, $locker );
		$zoninator    = new Zoninator(
			$settings,
			$taxonomy,
			$capabilities,
			new Request( $capabilities ),
			$zones,
			$locker,
			new Feed( $zones )
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
			Settings::class,
			Taxonomy::class,
			Capabilities::class,
			Request::class,
			Zones::class,
			ZoneLocker::class,
			TransientLockStore::class,
			Feed::class,
			FeedEndpoint::class,
			Search::class,
			Block::class,
			Screen::class,
			ZonesController::class,
			Abilities::class,
			Zoninator::class,
			PluginBootstrapper::class,
		);

		return array_combine( $classes, array_map( static fn( $class_name ) => array( $class_name ), $classes ) );
	}
}
