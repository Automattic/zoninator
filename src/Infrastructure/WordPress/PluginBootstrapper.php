<?php
/**
 * Boots Zoninator.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneGuard;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Infrastructure\DI\Container;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Assets;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Messages;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenDataLoader;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenLocation;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenView;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\SearchForm;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneDetailsView;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneForm;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRow;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRowController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\LockController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\Permissions;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\SearchController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonePostsController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;

/**
 * The composition root: the one place that knows how Zoninator's classes fit
 * together and which hooks they use.
 *
 * It builds each class with the others it needs, puts them in the container,
 * and calls register_hooks() on each class that handles hooks. zoninator.php
 * boots it once.
 *
 * @since 1.0.0
 * @internal Use zoninator_container() to reach the objects it builds.
 */
final class PluginBootstrapper {

	/**
	 * Constructor.
	 *
	 * @param Container $container An empty container to fill.
	 */
	public function __construct( private readonly Container $container ) {
	}

	/**
	 * Builds the classes and registers the hooks.
	 */
	public function boot(): void {
		$this->build();
		$this->register_hooks();
	}

	/**
	 * Builds each class with the others it needs, and adds it to the container. Adds no hooks.
	 */
	public function build(): void {
		$container = $this->container;

		$settings      = $container->add( new Settings() );
		$errors        = $container->add( new Errors() );
		$taxonomy      = $container->add( new Taxonomy() );
		$capabilities  = $container->add( new Capabilities( $taxonomy ) );
		$terms         = $container->add( new TermZoneRepository( $settings ) );
		$locker        = $container->add( new ZoneLocker( $container->add( new TransientLockStore() ), $settings, $terms ) );
		$zone_posts    = $container->add( new PostMetaZonePostsRepository( $terms, $taxonomy ) );
		$manager       = $container->add( new ZoneManager( $terms, $settings, $zone_posts, $locker ) );
		$curator       = $container->add( new ZoneCurator( $terms, $zone_posts, $settings ) );
		$guard         = $container->add( new ZoneGuard( $capabilities, $locker, $terms, $zone_posts ) );
		$guarded_zones = $container->add( new GuardedZoneManager( $manager, $guard ) );
		$guarded_posts = $container->add( new GuardedZoneCurator( $curator, $guard ) );
		$zones         = $container->add( new Zones( $taxonomy, $capabilities, $terms ) );
		$feed          = $container->add( new Feed( $zones ) );

		// The Zones admin screen.
		$location = $container->add( new ScreenLocation() );
		$messages = $container->add( new Messages() );
		$row      = $container->add( new ZonePostRow( $zones, $capabilities ) );
		$loader   = $container->add( new ScreenDataLoader( $zones, $capabilities, $locker ) );
		$view     = $container->add( new ScreenView( $messages, $location, $container->add( new SearchForm() ), $row, new ZoneDetailsView( $location ) ) );
		$container->add( new Screen( $capabilities, $loader, $view ) );
		$container->add( new Assets( $location, $settings, $messages ) );
		$container->add( new ZoneForm( $location, $guarded_zones, $errors ) );

		$search = $container->add( new Search( $taxonomy, $settings, $capabilities ) );
		$container->add( new FeedEndpoint( $feed ) );
		$container->add( new Block( $zones ) );

		$container->add( new Widgets() );
		$container->add( new Abilities( $zones, $capabilities, $search, $errors ) );
		$container->add( new CurationAbilities( $guarded_posts, $capabilities, $errors ) );

		// The zoninator/v1 REST API, and the Zones screen's own route.
		$permissions = new Permissions( $capabilities, $errors );
		$container->add( new ZonesController( $zones, $guarded_zones, $permissions, $errors ) );
		$container->add( new ZonePostsController( $feed, $guarded_posts, $permissions, $errors ) );
		$container->add( new SearchController( $search, $permissions ) );
		$container->add( new LockController( $locker, $settings, $permissions, $errors ) );
		$container->add( new ZonePostRowController( $row, $capabilities, $permissions, $errors ) );
	}

	/**
	 * Registers every hook Zoninator uses, by calling register_hooks() on each
	 * class in the container that has one. A class that handles hooks needs
	 * nothing more than that method, and its hooks are added in the order
	 * build() adds the classes.
	 */
	public function register_hooks(): void {
		foreach ( $this->container->all() as $instance ) {
			if ( method_exists( $instance, 'register_hooks' ) ) {
				$instance->register_hooks();
			}
		}
	}
}
