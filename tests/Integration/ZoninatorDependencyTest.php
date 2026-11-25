<?php
declare( strict_types = 1 );

/**
 * Checks that dependencies run one way: the bootstrapper builds every class,
 * and none of them depends on Zoninator.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Application\ZoneRemover;
use Automattic\Zoninator\Infrastructure\WordPress\Abilities;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Block;
use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Feed;
use Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint;
use Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper;
use Automattic\Zoninator\Infrastructure\WordPress\PostMetaZonePostsRepository;
use Automattic\Zoninator\Infrastructure\WordPress\Request;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;
use Automattic\Zoninator\Infrastructure\WordPress\Search;
use Automattic\Zoninator\Infrastructure\WordPress\Settings;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\TermZoneRepository;
use Automattic\Zoninator\Infrastructure\WordPress\TransientLockStore;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
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
			ZoneManager::class,
			ZoneRemover::class,
			ZoneCurator::class,
			TermZoneRepository::class,
			PostMetaZonePostsRepository::class,
			Feed::class,
			FeedEndpoint::class,
			Search::class,
			Block::class,
			Screen::class,
			ZonesController::class,
			Abilities::class,
			PluginBootstrapper::class,
		);

		return array_combine( $classes, array_map( static fn( $class_name ) => array( $class_name ), $classes ) );
	}
}
