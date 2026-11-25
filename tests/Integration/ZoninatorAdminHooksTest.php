<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin the Zones screen's admin hooks and callbacks.
 *
 * Sites unhook these with array( Zoninator(), 'method' ), so they must stay
 * on the facade whatever class does the work.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class ZoninatorAdminHooksTest extends TestCase {

	/**
	 * @var int
	 */
	private $editor_id;

	/**
	 * @var int
	 */
	private $zone_id;

	public function set_up(): void {
		parent::set_up();

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$zone            = Zoninator()->insert_zone( 'pinned-zone', 'Pinned zone' );
		$this->zone_id   = (int) $zone['term_id'];
	}

	/**
	 * @dataProvider data_facade_hooks
	 *
	 * @param string $hook     Hook name.
	 * @param string $method   Zoninator method.
	 * @param int    $priority Expected priority.
	 */
	public function test_hooks_are_registered_on_the_facade( string $hook, string $method, int $priority ): void {
		Zoninator()->admin_init();

		$this->assertSame( $priority, has_action( $hook, array( Zoninator(), $method ) ) );
	}

	/**
	 * @return array<string, array{string, string, int}>
	 */
	public function data_facade_hooks(): array {
		return array(
			'admin controller'      => array( 'admin_init', 'admin_controller', 10 ),
			'admin init'            => array( 'admin_init', 'admin_init', 10 ),
			'admin menu'            => array( 'admin_menu', 'admin_page_init', 10 ),
			'scripts'               => array( 'admin_enqueue_scripts', 'admin_enqueue_scripts', 10 ),
			'styles'                => array( 'admin_enqueue_scripts', 'admin_enqueue_styles', 10 ),
			'category search field' => array( 'zoninator_advanced_search_fields', 'zone_advanced_search_cat_filter', 10 ),
			'date search field'     => array( 'zoninator_advanced_search_fields', 'zone_advanced_search_date_filter', 20 ),
		);
	}

	/**
	 * The Zones screen uses the REST API, and 1.0.0 removed these actions.
	 *
	 * @dataProvider data_removed_ajax_actions
	 *
	 * @param string $action admin-ajax action.
	 */
	public function test_removed_ajax_action_is_not_registered( string $action ): void {
		Zoninator()->admin_init();

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

	public function test_menu_page_renders_with_the_facade(): void {
		wp_set_current_user( $this->editor_id );

		Zoninator()->admin_page_init();

		$this->assertSame( 10, has_action( 'toplevel_page_zoninator', array( Zoninator(), 'admin_page' ) ) );
	}

	public function test_zone_post_columns_default_to_facade_callbacks(): void {
		wp_set_current_user( $this->editor_id );

		$columns = null;
		$spy     = static function ( $value ) use ( &$columns ) {
			$columns = $value;
			return $value;
		};
		add_filter( 'zoninator_zone_post_columns', $spy );

		ob_start();
		Zoninator()->admin_page_zone_post( get_post( self::factory()->post->create() ), Zoninator()->get_zone( $this->zone_id ) );
		ob_end_clean();

		remove_filter( 'zoninator_zone_post_columns', $spy );
		$this->assertSame(
			array(
				'position' => array( Zoninator(), 'admin_page_zone_post_col_position' ),
				'info'     => array( Zoninator(), 'admin_page_zone_post_col_info' ),
			),
			$columns
		);
	}

	public function test_zone_screen_scripts_get_their_options(): void {
		wp_set_current_user( $this->editor_id );
		$screen = get_current_screen();
		set_current_screen( 'toplevel_page_zoninator' );

		Zoninator()->admin_enqueue_scripts();

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
