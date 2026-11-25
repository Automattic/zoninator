<?php
/**
 * Boots Zoninator.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

use Automattic\Zoninator\Admin\Screen;
use Automattic\Zoninator\REST\Zones_Controller;
use Zoninator;
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
final class Bootstrapper {

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
	 * Builds the classes, sets the global older code reads, and registers the hooks.
	 */
	public function boot() {
		$this->build();

		$GLOBALS['zoninator'] = $this->container->get( Zoninator::class ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->register_hooks();
	}

	/**
	 * Builds each class with the others it needs, and adds it to the container. Adds no hooks.
	 */
	public function build() {
		$container = $this->container;

		// So sites can unhook the callbacks below.
		$container->add( $this );

		$settings     = $container->add( new Settings() );
		$taxonomy     = $container->add( new Taxonomy() );
		$capabilities = $container->add( new Capabilities( $taxonomy ) );
		$request      = $container->add( new Request( $capabilities ) );
		$zones        = $container->add( new Zones( $settings, $taxonomy, $capabilities ) );
		$locks        = $container->add( new Locks( $zones, $settings ) );
		$feed         = $container->add( new Feed( $zones ) );
		$screen       = $container->add( new Screen( $zones, $locks, $settings, $capabilities, $request ) );
		$container->add( new Block( $zones ) );
		$container->add( new Zoninator( $settings, $taxonomy, $capabilities, $request, $zones, $locks, $feed ) );

		// Only REST and Abilities API requests need these, so their files load then.
		$container->factory(
			Zones_Controller::class,
			static function () use ( $zones, $taxonomy, $locks, $feed, $settings, $capabilities, $screen ) {
				require_once __DIR__ . '/rest/class-zones-controller.php';

				return new Zones_Controller( $zones, $taxonomy, $locks, $feed, $settings, $capabilities, $screen );
			}
		);
		$container->factory(
			Abilities::class,
			static function () use ( $container, $zones, $capabilities ) {
				require_once __DIR__ . '/class-abilities.php';

				return new Abilities(
					$zones,
					$capabilities,
					static function () use ( $container ) {
						return $container->get( Zones_Controller::class );
					}
				);
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
		$this->container->get( Zones::class )->register_hooks();
		$this->container->get( Feed::class )->register_hooks();
		$this->container->get( Block::class )->register_hooks();
		$this->container->get( Screen::class )->register_hooks();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the zoninator/v1 REST routes. Hooked on rest_api_init.
	 */
	public function register_rest_routes() {
		$controller = $this->container->get( Zones_Controller::class );

		$this->container->get( Zoninator::class )->rest_api = $controller;
		$controller->register_routes();
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
	}
}
