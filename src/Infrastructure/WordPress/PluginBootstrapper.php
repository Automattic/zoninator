<?php
/**
 * Boots Zoninator.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Application\GuardedZoneCurator;
use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Application\ZoneGuard;
use Automattic\Zoninator\Application\ZoneLocker;
use Automattic\Zoninator\Application\ZoneManager;
use Automattic\Zoninator\Application\ZoneRemover;
use Automattic\Zoninator\Infrastructure\DI\Container;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneForm;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\LockController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\Permissions;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\SearchController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonePostsController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;
use Zoninator_ZonePosts_Widget;

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
	 * Where the classes it builds are kept.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container An empty container to fill.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Builds the classes and registers the hooks.
	 */
	public function boot() {
		$this->build();
		$this->register_hooks();
	}

	/**
	 * Builds each class with the others it needs, and adds it to the container. Adds no hooks.
	 */
	public function build() {
		$container = $this->container;

		// So sites can unhook the callbacks below.
		$container->add( $this );

		$settings      = $container->add( new Settings() );
		$taxonomy      = $container->add( new Taxonomy() );
		$capabilities  = $container->add( new Capabilities( $taxonomy ) );
		$request       = $container->add( new Request( $capabilities ) );
		$terms         = $container->add( new TermZoneRepository( $settings ) );
		$locker        = $container->add( new ZoneLocker( $container->add( new TransientLockStore() ), $settings, $terms ) );
		$zone_posts    = $container->add( new PostMetaZonePostsRepository( $terms, $taxonomy ) );
		$manager       = $container->add( new ZoneManager( $terms, $settings ) );
		$remover       = $container->add( new ZoneRemover( $terms, $zone_posts, $locker ) );
		$curator       = $container->add( new ZoneCurator( $terms, $zone_posts, $settings ) );
		$guard         = $container->add( new ZoneGuard( $capabilities, $locker, $terms ) );
		$guarded_zones = $container->add( new GuardedZoneManager( $manager, $remover, $guard ) );
		$guarded_posts = $container->add( new GuardedZoneCurator( $curator, $guard ) );
		$zones         = $container->add( new Zones( $taxonomy, $capabilities, $terms ) );
		$container->add( new TermSplitHandler( $curator ) );
		$feed   = $container->add( new Feed( $zones ) );
		$screen = $container->add( new Screen( $zones, $locker, $settings, $capabilities, $request ) );
		$container->add( new ZoneForm( $screen, $request, $guarded_zones ) );
		$search = $container->add( new Search( $taxonomy, $settings, $capabilities, $zones ) );
		$container->add( new FeedEndpoint( $feed ) );
		$container->add( new Block( $zones ) );

		// Only REST and Abilities API requests need these, so they're built, and their files loaded, then.
		$permissions = static function () use ( $container ) {
			return $container->get( Permissions::class );
		};
		$container->factory(
			Permissions::class,
			static function () use ( $capabilities ) {
				return new Permissions( $capabilities );
			}
		);
		$container->factory(
			ZonesController::class,
			static function () use ( $zones, $guarded_zones, $permissions ) {
				return new ZonesController( $zones, $guarded_zones, $permissions() );
			}
		);
		$container->factory(
			ZonePostsController::class,
			static function () use ( $feed, $guarded_posts, $screen, $permissions ) {
				return new ZonePostsController( $feed, $guarded_posts, $screen, $permissions() );
			}
		);
		$container->factory(
			SearchController::class,
			static function () use ( $search, $permissions ) {
				return new SearchController( $search, $permissions() );
			}
		);
		$container->factory(
			LockController::class,
			static function () use ( $locker, $settings, $permissions ) {
				return new LockController( $locker, $settings, $permissions() );
			}
		);
		$container->factory(
			CurationAbilities::class,
			static function () use ( $guarded_posts, $capabilities ) {
				return new CurationAbilities( $guarded_posts, $capabilities );
			}
		);
		$container->factory(
			Abilities::class,
			static function () use ( $zones, $capabilities, $search ) {
				return new Abilities( $zones, $capabilities, $search );
			}
		);
	}

	/**
	 * Registers every hook Zoninator uses: each class registers the ones it
	 * handles, and the methods below load the REST and Abilities API classes
	 * only when they're needed.
	 */
	public function register_hooks() {
		Zoninator_ZonePosts_Widget::register_hooks();
		$this->container->get( Taxonomy::class )->register_hooks();
		$this->container->get( TermSplitHandler::class )->register_hooks();
		$this->container->get( FeedEndpoint::class )->register_hooks();
		$this->container->get( Block::class )->register_hooks();
		$this->container->get( Screen::class )->register_hooks();
		$this->container->get( ZoneForm::class )->register_hooks();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the zoninator/v1 REST routes. Hooked on rest_api_init.
	 */
	public function register_rest_routes() {
		foreach ( array( ZonesController::class, ZonePostsController::class, SearchController::class, LockController::class ) as $controller ) {
			$this->container->get( $controller )->register_routes();
		}
	}

	/**
	 * Registers the zone ability category. Hooked on wp_abilities_api_categories_init.
	 */
	public function register_ability_categories() {
		$this->container->get( Abilities::class )->register_category();
	}

	/**
	 * Registers the zone abilities. Hooked on wp_abilities_api_init.
	 */
	public function register_abilities() {
		$this->container->get( Abilities::class )->register();
		$this->container->get( CurationAbilities::class )->register();
	}
}
