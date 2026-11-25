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

class ZoninatorHooksTest extends TestCase {

	/**
	 * Every hook the plugin registers, as hook => list of "Class::method priority accepted_args".
	 */
	private const HOOKS = array(
		'init'                             => array( 'Automattic\\Zoninator\\Feed::add_rewrite_rule 10 1', 'Zoninator::init 99 1' ),
		'widgets_init'                     => array( 'Zoninator_ZonePosts_Widget::register 10 1' ),
		'template_redirect'                => array( 'Automattic\\Zoninator\\Feed::serve 10 1' ),
		'split_shared_term'                => array( 'Automattic\\Zoninator\\Zones::split_shared_term 10 4' ),
		'query_loop_block_query_vars'      => array( 'Automattic\\Zoninator\\Block::filter_query_vars 10 2' ),
		'rest_post_query'                  => array( 'Automattic\\Zoninator\\Block::filter_rest_query 10 2' ),
		'enqueue_block_editor_assets'      => array( 'Automattic\\Zoninator\\Block::enqueue_editor_assets 10 1' ),
		'rest_api_init'                    => array( 'Zoninator::register_rest_routes 10 1' ),
		'wp_abilities_api_categories_init' => array( 'Zoninator::register_ability_categories 10 1' ),
		'wp_abilities_api_init'            => array( 'Zoninator::register_abilities 10 1' ),
		'admin_init'                       => array( 'Automattic\\Zoninator\\Admin\\Screen::handle_request 10 1' ),
		'admin_enqueue_scripts'            => array( 'Automattic\\Zoninator\\Admin\\Screen::enqueue_scripts 10 1', 'Automattic\\Zoninator\\Admin\\Screen::enqueue_styles 10 1' ),
		'admin_menu'                       => array( 'Automattic\\Zoninator\\Admin\\Screen::register_menu_page 10 1' ),
		'zoninator_advanced_search_fields' => array( 'Automattic\\Zoninator\\Admin\\Screen::render_category_filter 10 1', 'Automattic\\Zoninator\\Admin\\Screen::render_date_filter 20 1' ),
		// The widget registers these on itself when it's built.
		'save_post'                        => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
		'deleted_post'                     => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
		'switch_theme'                     => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
		'zoninator_add_zone_posts'         => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
		'zoninator_remove_zone_posts'      => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
		'edited_zoninator_zones'           => array( 'Zoninator_ZonePosts_Widget::flush_widget_cache 10 1' ),
	);

	public function test_plugin_registers_exactly_these_hooks(): void {
		global $wp_filter;

		$found = array();
		foreach ( $wp_filter as $hook => $wp_hook ) {
			foreach ( $wp_hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( ! is_array( $function ) ) {
						continue;
					}
					$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
					if ( 'Zoninator' === $class || 'Zoninator_ZonePosts_Widget' === $class || ( str_starts_with( $class, 'Automattic\\Zoninator\\' ) && ! str_starts_with( $class, 'Automattic\\Zoninator\\Tests\\' ) ) ) {
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
		$screen = Zoninator()->get_admin_screen();
		$feed   = Zoninator()->get_feed();
		$block  = Zoninator()->get_block();

		return array(
			'plugin init'      => array( 'init', array( Zoninator(), 'init' ), 99 ),
			'widget'           => array( 'widgets_init', array( 'Zoninator_ZonePosts_Widget', 'register' ), 10 ),
			'feed rewrite'     => array( 'init', array( $feed, 'add_rewrite_rule' ), 10 ),
			'feed'             => array( 'template_redirect', array( $feed, 'serve' ), 10 ),
			'term split'       => array( 'split_shared_term', array( Zoninator()->get_zone_store(), 'split_shared_term' ), 10 ),
			'block query'      => array( 'query_loop_block_query_vars', array( $block, 'filter_query_vars' ), 10 ),
			'block preview'    => array( 'rest_post_query', array( $block, 'filter_rest_query' ), 10 ),
			'block editor'     => array( 'enqueue_block_editor_assets', array( $block, 'enqueue_editor_assets' ), 10 ),
			'REST routes'      => array( 'rest_api_init', array( Zoninator(), 'register_rest_routes' ), 10 ),
			'ability category' => array( 'wp_abilities_api_categories_init', array( Zoninator(), 'register_ability_categories' ), 10 ),
			'abilities'        => array( 'wp_abilities_api_init', array( Zoninator(), 'register_abilities' ), 10 ),
			'form handling'    => array( 'admin_init', array( $screen, 'handle_request' ), 10 ),
			'scripts'          => array( 'admin_enqueue_scripts', array( $screen, 'enqueue_scripts' ), 10 ),
			'styles'           => array( 'admin_enqueue_scripts', array( $screen, 'enqueue_styles' ), 10 ),
			'menu'             => array( 'admin_menu', array( $screen, 'register_menu_page' ), 10 ),
			'category field'   => array( 'zoninator_advanced_search_fields', array( $screen, 'render_category_filter' ), 10 ),
			'date field'       => array( 'zoninator_advanced_search_fields', array( $screen, 'render_date_filter' ), 20 ),
		);
	}

	/**
	 * Unhooking Zoninator's init turns off the whole plugin, so the screen's
	 * hooks must wait for it rather than be added when the plugin loads.
	 */
	public function test_screen_hooks_are_added_on_init(): void {
		$zoninator = new \Zoninator();
		$callback  = array( $zoninator->get_admin_screen(), 'register_menu_page' );

		$this->assertFalse( has_action( 'admin_menu', $callback ) );

		$zoninator->init();

		$this->assertSame( 10, has_action( 'admin_menu', $callback ) );
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

		Zoninator()->get_admin_screen()->register_menu_page();

		$this->assertSame( 10, has_action( 'toplevel_page_zoninator', array( Zoninator()->get_admin_screen(), 'render' ) ) );
	}

	public function test_zone_post_columns_default_to_the_screen(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone = Zoninator()->insert_zone( 'pinned-zone', 'Pinned zone' );

		$columns = null;
		$spy     = static function ( $value ) use ( &$columns ) {
			$columns = $value;
			return $value;
		};
		add_filter( 'zoninator_zone_post_columns', $spy );

		ob_start();
		Zoninator()->get_admin_screen()->render_zone_post( get_post( self::factory()->post->create() ), Zoninator()->get_zone( (int) $zone['term_id'] ) );
		ob_end_clean();

		remove_filter( 'zoninator_zone_post_columns', $spy );
		$this->assertSame(
			array(
				'position' => array( Zoninator()->get_admin_screen(), 'render_position_column' ),
				'info'     => array( Zoninator()->get_admin_screen(), 'render_info_column' ),
			),
			$columns
		);
	}

	public function test_zone_screen_scripts_get_their_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$screen = get_current_screen();
		set_current_screen( 'toplevel_page_zoninator' );

		Zoninator()->get_admin_screen()->enqueue_scripts();

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
