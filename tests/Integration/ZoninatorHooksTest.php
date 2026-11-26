<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin every hook the plugin registers.
 *
 * A site's remove_action() stops matching, silently, when a callback moves,
 * so these tests fail on any change to a hook's callback or priority, and
 * check that each unhooking call in the upgrade guide works.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use ArrayObject;
use Automattic\Zoninator\Infrastructure\DI\Container;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Assets;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\SearchForm;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneForm;
use Automattic\Zoninator\Infrastructure\WordPress\Abilities;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRow;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRowController;
use Automattic\Zoninator\Infrastructure\WordPress\Block;
use Automattic\Zoninator\Infrastructure\WordPress\CurationAbilities;
use Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint;
use Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\LockController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\SearchController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonePostsController;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\ZonesController;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use Automattic\Zoninator\Infrastructure\WordPress\Widgets;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;

class ZoninatorHooksTest extends TestCase {

	/**
	 * Every hook the plugin registers, as hook => list of "Class::method priority accepted_args".
	 */
	private const HOOKS = array(
		'init'                             => array( Taxonomy::class . '::register 10 1', FeedEndpoint::class . '::add_rewrite_rule 10 1' ),
		'widgets_init'                     => array( Widgets::class . '::register 10 1' ),
		'template_redirect'                => array( FeedEndpoint::class . '::serve 10 1' ),
		'query_loop_block_query_vars'      => array( Block::class . '::filter_query_vars 10 2' ),
		'rest_post_query'                  => array( Block::class . '::filter_rest_query 10 2' ),
		'enqueue_block_editor_assets'      => array( Block::class . '::enqueue_editor_assets 10 1' ),
		'plugins_loaded'                   => array( 'zoninator_container 10 1' ),
		'rest_api_init'                    => array(
			ZonesController::class . '::register_routes 10 1',
			ZonePostsController::class . '::register_routes 10 1',
			SearchController::class . '::register_routes 10 1',
			LockController::class . '::register_routes 10 1',
			ZonePostRowController::class . '::register_routes 10 1',
		),
		'wp_abilities_api_categories_init' => array( Abilities::class . '::register_category 10 1' ),
		'wp_abilities_api_init'            => array( Abilities::class . '::register 10 1', CurationAbilities::class . '::register 10 1' ),
		'admin_init'                       => array( ZoneForm::class . '::handle_request 10 1' ),
		'admin_enqueue_scripts'            => array( Assets::class . '::enqueue_scripts 10 1', Assets::class . '::enqueue_styles 10 1' ),
		'admin_menu'                       => array( Screen::class . '::register 10 1' ),
		'zoninator_advanced_search_fields' => array( SearchForm::class . '::render_category_filter 10 1', SearchForm::class . '::render_date_filter 20 1' ),
		// Everything that changes what the Zone Posts widget shows clears its cache.
		'save_post'                        => array( Widgets::class . '::flush_cache 10 1' ),
		'deleted_post'                     => array( Widgets::class . '::flush_cache 10 1' ),
		'switch_theme'                     => array( Widgets::class . '::flush_cache 10 1' ),
		'zoninator_add_zone_posts'         => array( Widgets::class . '::flush_cache 10 1' ),
		'zoninator_remove_zone_posts'      => array( Widgets::class . '::flush_cache 10 1' ),
		'edited_zoninator_zones'           => array( Widgets::class . '::flush_cache 10 1' ),
	);

	public function test_plugin_registers_exactly_these_hooks(): void {
		global $wp_filter;

		$found = array();
		foreach ( $wp_filter as $hook => $wp_hook ) {
			foreach ( $wp_hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_string( $function ) && str_starts_with( $function, 'zoninator_' ) ) {
						$found[ $hook ][] = $function . ' ' . $priority . ' ' . $callback['accepted_args'];
					}
					if ( ! is_array( $function ) ) {
						continue;
					}
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( str_starts_with( $class, 'Automattic\\Zoninator\\' ) && ! str_starts_with( $class, 'Automattic\\Zoninator\\Tests\\' ) ) {
						$found[ $hook ][] = $class . '::' . $function[1] . ' ' . $priority . ' ' . $callback['accepted_args'];
					}
				}
			}
		}
		$found = array_map( static fn( $entries ) => array_values( array_unique( $entries ) ), $found );
		ksort( $found );
		$expected = self::HOOKS;
		ksort( $expected );

		$this->assertSame( $expected, $found );
	}

	/**
	 * Each call the upgrade guide gives for unhooking must match the registered callback.
	 *
	 * @dataProvider data_unhook_calls
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback, built from the getters sites use.
	 * @param int      $priority Priority.
	 */
	public function test_upgrade_guide_unhooks_each_hook( string $hook, callable $callback, int $priority ): void {
		$this->assertTrue( remove_action( $hook, $callback, $priority ) );
		$this->assertFalse( has_action( $hook, $callback ) );
	}

	/**
	 * @return array<string, array{string, callable, int}>
	 */
	public function data_unhook_calls(): array {
		$assets    = zoninator_container()->get( Assets::class );
		$abilities = zoninator_container()->get( Abilities::class );
		$search    = zoninator_container()->get( SearchForm::class );
		$feed      = zoninator_container()->get( FeedEndpoint::class );
		$block     = zoninator_container()->get( Block::class );

		return array(
			'taxonomy'           => array( 'init', array( zoninator_container()->get( Taxonomy::class ), 'register' ), 10 ),
			'widget'             => array( 'widgets_init', array( zoninator_container()->get( Widgets::class ), 'register' ), 10 ),
			'feed rewrite'       => array( 'init', array( $feed, 'add_rewrite_rule' ), 10 ),
			'feed'               => array( 'template_redirect', array( $feed, 'serve' ), 10 ),
			'block query'        => array( 'query_loop_block_query_vars', array( $block, 'filter_query_vars' ), 10 ),
			'block preview'      => array( 'rest_post_query', array( $block, 'filter_rest_query' ), 10 ),
			'block editor'       => array( 'enqueue_block_editor_assets', array( $block, 'enqueue_editor_assets' ), 10 ),
			'zones routes'       => array( 'rest_api_init', array( zoninator_container()->get( ZonesController::class ), 'register_routes' ), 10 ),
			'zone posts routes'  => array( 'rest_api_init', array( zoninator_container()->get( ZonePostsController::class ), 'register_routes' ), 10 ),
			'search route'       => array( 'rest_api_init', array( zoninator_container()->get( SearchController::class ), 'register_routes' ), 10 ),
			'lock route'         => array( 'rest_api_init', array( zoninator_container()->get( LockController::class ), 'register_routes' ), 10 ),
			'row route'          => array( 'rest_api_init', array( zoninator_container()->get( ZonePostRowController::class ), 'register_routes' ), 10 ),
			'ability category'   => array( 'wp_abilities_api_categories_init', array( $abilities, 'register_category' ), 10 ),
			'reading abilities'  => array( 'wp_abilities_api_init', array( $abilities, 'register' ), 10 ),
			'curation abilities' => array( 'wp_abilities_api_init', array( zoninator_container()->get( CurationAbilities::class ), 'register' ), 10 ),
			'form handling'      => array( 'admin_init', array( zoninator_container()->get( ZoneForm::class ), 'handle_request' ), 10 ),
			'scripts'            => array( 'admin_enqueue_scripts', array( $assets, 'enqueue_scripts' ), 10 ),
			'styles'             => array( 'admin_enqueue_scripts', array( $assets, 'enqueue_styles' ), 10 ),
			'menu'               => array( 'admin_menu', array( zoninator_container()->get( Screen::class ), 'register' ), 10 ),
			'category field'     => array( 'zoninator_advanced_search_fields', array( $search, 'render_category_filter' ), 10 ),
			'date field'         => array( 'zoninator_advanced_search_fields', array( $search, 'render_date_filter' ), 20 ),
		);
	}

	/**
	 * Building the classes must not hook anything, so tests and the
	 * bootstrapper control when hooks are added.
	 */
	public function test_building_the_classes_adds_no_hooks(): void {
		$container    = new Container();
		$bootstrapper = new PluginBootstrapper( $container );
		$bootstrapper->build();
		$callback = array( $container->get( Screen::class ), 'register' );

		$this->assertFalse( has_action( 'admin_menu', $callback ) );

		$bootstrapper->register_hooks();

		$this->assertSame( 10, has_action( 'admin_menu', $callback ) );
	}

	/**
	 * A class gets its hooks by having a register_hooks() method, with no list to add it to.
	 */
	public function test_the_bootstrapper_hooks_any_class_with_a_register_hooks_method(): void {
		$container = new Container();
		$with      = new class() {
			public $calls = 0;

			public function register_hooks(): void {
				++$this->calls;
			}
		};
		$container->add( $with );
		$container->add( new ArrayObject() );

		( new PluginBootstrapper( $container ) )->register_hooks();

		$this->assertSame( 1, $with->calls );
	}

	/**
	 * The container knows nothing about Zoninator's classes, so it can hold a stand-in.
	 */
	public function test_container_holds_whatever_it_is_given(): void {
		$container = new Container();
		$stand_in  = new ArrayObject();

		$this->assertSame( $stand_in, $container->add( $stand_in ) );
		$this->assertSame( $stand_in, $container->get( ArrayObject::class ) );
		$this->assertSame( array( ArrayObject::class => $stand_in ), $container->all() );
		$this->assertFalse( $container->has( Zones::class ) );
	}

	public function test_get_returns_null_with_a_notice_for_a_class_it_did_not_build(): void {
		$this->setExpectedIncorrectUsage( 'Automattic\Zoninator\Infrastructure\DI\Container::get' );

		$this->assertFalse( zoninator_container()->has( 'WP_Query' ) );
		$this->assertNull( zoninator_container()->get( 'WP_Query' ) );
	}

	/**
	 * The Zones screen uses the REST API, and 1.0.0 removed these actions.
	 *
	 * @dataProvider data_removed_ajax_actions
	 *
	 * @param string $action admin-ajax action.
	 */
	public function test_removed_ajax_action_is_not_registered( string $action ): void {
		$this->assertFalse( has_action( 'wp_ajax_' . $action ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_removed_ajax_actions(): array {
		return array(
			'add post'      => array( 'zoninator_add_post' ),
			'remove post'   => array( 'zoninator_remove_post' ),
			'reorder posts' => array( 'zoninator_reorder_posts' ),
			'search posts'  => array( 'zoninator_search_posts' ),
			'update lock'   => array( 'zoninator_update_lock' ),
			'recent posts'  => array( 'zoninator_update_recent' ),
		);
	}

	public function test_menu_page_renders_with_the_screen(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		zoninator_container()->get( Screen::class )->register();

		$this->assertSame( 10, has_action( 'toplevel_page_zoninator', array( zoninator_container()->get( Screen::class ), 'render' ) ) );
	}

	public function test_zone_post_columns_default_to_the_row(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone = self::insert_zone( 'pinned-zone', 'Pinned zone' );

		$columns = null;
		$spy     = static function ( $value ) use ( &$columns ) {
			$columns = $value;
			return $value;
		};
		add_filter( 'zoninator_zone_post_columns', $spy );

		ob_start();
		zoninator_container()->get( ZonePostRow::class )->render( get_post( self::factory()->post->create() ), self::zones()->get( (int) $zone['term_id'] ) );
		ob_end_clean();

		remove_filter( 'zoninator_zone_post_columns', $spy );
		$this->assertSame(
			array(
				'position' => array( zoninator_container()->get( ZonePostRow::class ), 'render_position_column' ),
				'info'     => array( zoninator_container()->get( ZonePostRow::class ), 'render_info_column' ),
			),
			$columns
		);
	}

	public function test_zone_screen_scripts_get_their_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$screen = get_current_screen();
		set_current_screen( 'toplevel_page_zoninator' );

		zoninator_container()->get( Assets::class )->enqueue_scripts();

		$GLOBALS['current_screen'] = $screen; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$this->assertTrue( wp_script_is( 'zoninator-js', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'jquery-ui-touch-punch', 'enqueued' ) );
		// js/zoninator.js calls wp.a11y.speak() and wp.apiFetch().
		$this->assertContains( 'wp-a11y', wp_scripts()->registered['zoninator-js']->deps );
		$this->assertContains( 'wp-api-fetch', wp_scripts()->registered['zoninator-js']->deps );

		$data = (string) wp_scripts()->get_data( 'zoninator-js', 'data' );
		$this->assertSame( 1, preg_match( '/var zoninatorOptions = (\{.*\});/s', $data, $matches ) );
		$this->assertSame(
			array( 'baseUrl', 'adminUrl', 'errorGeneral', 'errorZoneLock', 'errorSave', 'retry', 'savedAt', 'confirmZoneLock', 'zoneLockPeriod', 'zoneLockPeriodMax', 'postAdded', 'postRemoved', 'postMoved' ),
			array_keys( json_decode( $matches[1], true ) )
		);
	}
}
