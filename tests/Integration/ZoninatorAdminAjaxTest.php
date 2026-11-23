<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how the zone screen's AJAX handlers and admin hooks behave.
 *
 * js/zoninator.js depends on these response shapes, and sites unhook the
 * handlers with array( Zoninator(), 'method' ), so both must survive any
 * change to where the code lives.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Ajax_UnitTestCase;
use WPAjaxDieContinueException;

/**
 * @group ajax
 */
class ZoninatorAdminAjaxTest extends WP_Ajax_UnitTestCase {

	/**
	 * @var int
	 */
	private $editor_id;

	/**
	 * @var int
	 */
	private $zone_id;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$zone            = Zoninator()->insert_zone( 'pinned-zone', 'Pinned zone' );
		$this->zone_id   = (int) $zone['term_id'];

		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
	}

	public function tearDown(): void {
		$_REQUEST = array();
		$_POST    = array();
		$_GET     = array();
		parent::tearDown();
	}

	public function test_add_post_appends_the_post_and_returns_its_row(): void {
		$first_id = self::factory()->post->create();
		$post_id  = self::factory()->post->create();
		Zoninator()->add_zone_posts( $this->zone_id, $first_id );

		$response = $this->ajax( 'zoninator_add_post', array( 'post_id' => $post_id ) );

		$this->assertSame( array( 'status', 'content', 'nonce' ), array_keys( $response ) );
		$this->assertSame( 1, $response['status'] );
		$this->assertSame( $this->render_zone_post( $post_id ), $response['content'] );
		$this->assertNotFalse( wp_verify_nonce( $response['nonce'], $this->ajax_nonce_action() ) );
		$this->assertSame( '2', $this->zone_order( $post_id ) );
	}

	public function test_remove_post_removes_only_that_post(): void {
		$kept_id    = self::factory()->post->create();
		$removed_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $this->zone_id, array( $kept_id, $removed_id ) );

		$response = $this->ajax( 'zoninator_remove_post', array( 'post_id' => $removed_id ) );

		$this->assertSame( 1, $response['status'] );
		$this->assertSame( '', $response['content'] );
		$this->assertSame( '', $this->zone_order( $removed_id ) );
		$this->assertSame( '1', $this->zone_order( $kept_id ) );
	}

	public function test_reorder_posts_saves_the_new_order(): void {
		$first_id  = self::factory()->post->create();
		$second_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $this->zone_id, array( $first_id, $second_id ) );

		$response = $this->ajax( 'zoninator_reorder_posts', array( 'posts' => array( $second_id, $first_id ) ) );

		$this->assertSame( 1, $response['status'] );
		$this->assertSame( '', $response['content'] );
		$this->assertSame( '1', $this->zone_order( $second_id ) );
		$this->assertSame( '2', $this->zone_order( $first_id ) );
	}

	public function test_search_posts_returns_each_match_with_its_labels(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Pinned search canary' ) );
		self::factory()->post->create( array( 'post_title' => 'Something else' ) );

		$response = $this->ajax( 'zoninator_search_posts', array( 'term' => 'canary' ) );

		$this->assertSame(
			array(
				array(
					'title'             => 'Pinned search canary',
					'post_id'           => $post_id,
					'date'              => get_the_time( get_option( 'date_format' ), $post_id ),
					'post_type'         => 'post',
					'post_type_label'   => 'Post',
					'post_status'       => 'publish',
					'post_status_label' => 'Published',
				),
			),
			$response
		);
	}

	public function test_search_posts_without_a_term_lists_the_latest_content(): void {
		$older_id = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ) ) );
		$newer_id = self::factory()->post->create( array( 'post_date' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );

		$response = $this->ajax( 'zoninator_search_posts', array( 'term' => '' ) );

		$this->assertSame( array( $newer_id, $older_id ), array_column( $response, 'post_id' ) );
	}

	public function test_update_lock_locks_an_unlocked_zone(): void {
		$response = $this->ajax( 'zoninator_update_lock', array() );

		$this->assertSame( 1, $response['status'] );
		$this->assertSame( '', $response['content'] );
		$this->assertSame( $this->editor_id, (int) get_transient( Zoninator()->get_zone_meta_key( $this->zone_id ) ) );
	}

	public function test_update_lock_leaves_another_users_lock_alone(): void {
		$other_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		Zoninator()->lock_zone( $this->zone_id, $other_id );

		$response = $this->ajax( 'zoninator_update_lock', array() );

		$this->assertNull( $response );
		$this->assertSame( $other_id, Zoninator()->is_zone_locked( $this->zone_id ) );
	}

	/**
	 * Sites unhook a handler with the facade callback, so it must stay the registered one.
	 */
	public function test_remove_action_with_the_facade_callback_stops_the_handler(): void {
		$post_id = self::factory()->post->create();
		$remover = static function () {
			remove_action( 'wp_ajax_zoninator_add_post', array( Zoninator(), 'ajax_add_post' ) );
		};
		add_action( 'admin_init', $remover, 11 );

		$response = $this->ajax( 'zoninator_add_post', array( 'post_id' => $post_id ) );

		remove_action( 'admin_init', $remover, 11 );
		// The handler didn't run, so it didn't call _deprecated_function() either.
		$this->expected_deprecated = array();
		$this->assertNull( $response );
		$this->assertSame( '', $this->zone_order( $post_id ) );
	}

	/**
	 * @dataProvider data_facade_hooks
	 *
	 * @param string $hook     Hook name.
	 * @param string $method   Zoninator method.
	 * @param int    $priority Expected priority.
	 */
	public function test_hooks_are_registered_on_the_facade( string $hook, string $method, int $priority ): void {
		do_action( 'admin_init' );

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
			'reorder posts'         => array( 'wp_ajax_zoninator_reorder_posts', 'ajax_reorder_posts', 10 ),
			'add post'              => array( 'wp_ajax_zoninator_add_post', 'ajax_add_post', 10 ),
			'remove post'           => array( 'wp_ajax_zoninator_remove_post', 'ajax_remove_post', 10 ),
			'search posts'          => array( 'wp_ajax_zoninator_search_posts', 'ajax_search_posts', 10 ),
			'update lock'           => array( 'wp_ajax_zoninator_update_lock', 'ajax_update_lock', 10 ),
			'category search field' => array( 'zoninator_advanced_search_fields', 'zone_advanced_search_cat_filter', 10 ),
			'date search field'     => array( 'zoninator_advanced_search_fields', 'zone_advanced_search_date_filter', 20 ),
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

		$this->render_zone_post( self::factory()->post->create() );

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

		$data = (string) wp_scripts()->get_data( 'zoninator-js', 'data' );
		$this->assertSame( 1, preg_match( '/var zoninatorOptions = (\{.*\});/s', $data, $matches ) );
		$this->assertSame(
			array( 'baseUrl', 'adminUrl', 'ajaxNonceAction', 'errorGeneral', 'errorZoneLock', 'errorSave', 'retry', 'savedAt', 'confirmZoneLock', 'zoneLockPeriod', 'zoneLockPeriodMax', 'postAdded', 'postRemoved', 'postMoved' ),
			array_keys( json_decode( $matches[1], true ) )
		);
	}

	/**
	 * Run a Zoninator AJAX action as the editor, with a valid nonce, and decode the response.
	 *
	 * @param string $action AJAX action.
	 * @param array  $data   Request data.
	 * @return mixed Decoded JSON, or null if the handler sent nothing.
	 */
	private function ajax( string $action, array $data ) {
		wp_set_current_user( $this->editor_id );

		$_POST = array_merge(
			array(
				'action'   => $action,
				'zone_id'  => $this->zone_id,
				'_wpnonce' => wp_create_nonce( $this->ajax_nonce_action() ),
			),
			$data
		);

		// The action is deprecated, but still has to work until 1.0.0.
		$this->setExpectedDeprecated( 'Zoninator::ajax_' . substr( $action, strlen( 'zoninator_' ) ) );

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected. wp_send_json() dies after echoing JSON.
		}

		return json_decode( (string) $this->_last_response, true );
	}

	private function ajax_nonce_action(): string {
		return Zoninator()->_get_nonce_key( \Automattic\Zoninator\Admin\Ajax::NONCE_ACTION );
	}

	private function render_zone_post( int $post_id ): string {
		ob_start();
		Zoninator()->admin_page_zone_post( get_post( $post_id ), Zoninator()->get_zone( $this->zone_id ) );
		return (string) ob_get_clean();
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string The post's order in the zone, or '' if it isn't in it.
	 */
	private function zone_order( int $post_id ): string {
		return (string) get_post_meta( $post_id, Zoninator()->get_zone_meta_key( $this->zone_id ), true );
	}
}
