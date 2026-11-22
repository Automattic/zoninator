<?php
/**
 * Contract tests for the zoninator/v1 REST API.
 *
 * These pin the current behaviour (status codes, body shapes, error codes,
 * route registration) so the REST layer can be rebuilt on WP_REST_Controller
 * without changing what callers see. Some of the pinned behaviour is wrong
 * (for example, permission failures return HTTP 500); fix it in its own
 * change and update the test alongside.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_REST_Request;
use WP_REST_Response;

/**
 * @coversNothing
 */
final class ZoninatorRestContractTest extends TestCase {

	/**
	 * @var int
	 */
	private $admin_id;

	/**
	 * @var int
	 */
	private $subscriber_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $this->admin_id );
	}

	public function test_routes_are_registered_with_their_methods_and_args(): void {
		$this->assertSame( $this->expected_routes(), $this->registered_routes() );
	}

	/**
	 * @dataProvider data_editable_methods
	 *
	 * @param string $method HTTP method.
	 */
	public function test_update_routes_accept_every_editable_method( $method ): void {
		$zone_id = $this->create_zone( 'editable' );
		$post_id = self::factory()->post->create();

		$this->assertSame( 200, $this->dispatch( $method, "/zones/$zone_id", array( 'name' => 'Renamed' ) )->get_status() );
		$this->assertSame( 200, $this->dispatch( $method, "/zones/$zone_id/posts", array( 'post_ids' => array( $post_id ) ) )->get_status() );
		$this->assertSame( 200, $this->dispatch( $method, "/zones/$zone_id/lock" )->get_status() );
	}

	public static function data_editable_methods(): array {
		return array(
			'POST'  => array( 'POST' ),
			'PUT'   => array( 'PUT' ),
			'PATCH' => array( 'PATCH' ),
		);
	}

	public function test_get_zones_rejects_anonymous_callers(): void {
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', '/zones' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'rest_forbidden', $response->get_data()['code'] );
	}

	public function test_get_zones_allows_anonymous_callers_when_filtered(): void {
		wp_set_current_user( 0 );
		add_filter( 'zoninator_rest_get_zones_permissions_check', '__return_true' );

		$this->assertSame( 200, $this->dispatch( 'GET', '/zones' )->get_status() );
	}

	public function test_get_zones_allows_any_logged_in_user(): void {
		wp_set_current_user( $this->subscriber_id );

		$this->assertSame( 200, $this->dispatch( 'GET', '/zones' )->get_status() );
	}

	/**
	 * @dataProvider data_write_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Route, with %d for the zone ID.
	 * @param bool   $post   Whether to send a valid post_ids param.
	 */
	public function test_write_routes_deny_users_without_the_capability( $method, $path, $post ): void {
		$zone_id = $this->create_zone( 'denied' );
		$params  = $post ? array( 'post_ids' => array( self::factory()->post->create() ) ) : array();
		wp_set_current_user( $this->subscriber_id );

		$response = $this->dispatch( $method, sprintf( $path, $zone_id ), $params );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'permission-denied', $response->get_data()['code'] );
		$this->assertSame( "Sorry, you're not supposed to do that...", $response->get_data()['message'] );
	}

	/**
	 * @dataProvider data_write_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Route, with %d for the zone ID.
	 * @param bool   $post   Whether to send a valid post_ids param.
	 */
	public function test_write_routes_ask_logged_out_users_to_authenticate( $method, $path, $post ): void {
		$zone_id = $this->create_zone( 'anonymous' );
		$params  = $post ? array( 'post_ids' => array( self::factory()->post->create() ) ) : array();
		wp_set_current_user( 0 );

		$response = $this->dispatch( $method, sprintf( $path, $zone_id ), $params );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'permission-denied', $response->get_data()['code'] );
	}

	public static function data_write_routes(): array {
		return array(
			'create zone'       => array( 'POST', '/zones', false ),
			'update zone'       => array( 'PUT', '/zones/%d', false ),
			'delete zone'       => array( 'DELETE', '/zones/%d', false ),
			'update zone posts' => array( 'PUT', '/zones/%d/posts', true ),
			'lock zone'         => array( 'PUT', '/zones/%d/lock', false ),
		);
	}

	/**
	 * @dataProvider data_zone_routes
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Route, with %d for the zone ID.
	 * @param bool   $post   Whether to send a valid post_ids param.
	 */
	public function test_zone_routes_return_a_message_only_body_for_a_missing_zone( $method, $path, $post ): void {
		$params = $post ? array( 'post_ids' => array( self::factory()->post->create() ) ) : array();

		$response = $this->dispatch( $method, sprintf( $path, 999999 ), $params );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( array( 'message' => 'Zone not found' ), $response->get_data() );
	}

	public static function data_zone_routes(): array {
		return array(
			'update zone'       => array( 'PUT', '/zones/%d', false ),
			'delete zone'       => array( 'DELETE', '/zones/%d', false ),
			'get zone posts'    => array( 'GET', '/zones/%d/posts', false ),
			'update zone posts' => array( 'PUT', '/zones/%d/posts', true ),
			'lock zone'         => array( 'PUT', '/zones/%d/lock', false ),
		);
	}

	public function test_get_zones_lists_zones_by_id_with_four_fields(): void {
		$first  = $this->create_zone( 'first', 'First', 'One' );
		$second = $this->create_zone( 'second', 'Second', 'Two' );

		$response = $this->dispatch( 'GET', '/zones' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array(
					'term_id'     => $first,
					'slug'        => 'first',
					'name'        => 'First',
					'description' => 'One',
				),
				array(
					'term_id'     => $second,
					'slug'        => 'second',
					'name'        => 'Second',
					'description' => 'Two',
				),
			),
			$response->get_data()
		);
	}

	public function test_create_zone_returns_the_new_zone(): void {
		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name'        => 'Homepage',
				'slug'        => 'homepage',
				'description' => 'Top stories',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( 'term_id', 'slug', 'name', 'description' ), array_keys( $data ) );
		$this->assertSame( 'homepage', $data['slug'] );
		$this->assertSame( 'Homepage', $data['name'] );
		$this->assertSame( 'Top stories', $data['description'] );
		$this->assertSame( 'Homepage', Zoninator()->get_zone( (int) $data['term_id'] )->name );
	}

	public function test_create_zone_uses_the_name_as_the_slug_when_none_is_given(): void {
		$response = $this->dispatch( 'POST', '/zones', array( 'name' => 'sidebar' ) );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'sidebar', $response->get_data()['slug'] );
	}

	public function test_create_zone_without_slug_or_name_returns_a_message_only_body(): void {
		$response = $this->dispatch( 'POST', '/zones' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( array( 'message' => 'Slug is a required field.' ), $response->get_data() );
	}

	public function test_create_zone_with_a_duplicate_returns_a_message_only_body(): void {
		$this->create_zone( 'taken', 'Taken' );

		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name' => 'Taken',
				'slug' => 'taken',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( array( 'message' ), array_keys( $response->get_data() ) );
		$this->assertNotEmpty( $response->get_data()['message'] );
	}

	public function test_create_zone_strips_backslashes_from_input(): void {
		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name' => 'Back\\slash',
				'slug' => 'backslash',
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Backslash', $response->get_data()['name'] );
	}

	public function test_update_zone_changes_the_zone(): void {
		$zone_id = $this->create_zone( 'before', 'Before', 'Old' );

		$response = $this->dispatch(
			'PUT',
			"/zones/$zone_id",
			array(
				'name'        => 'After',
				'slug'        => 'after',
				'description' => 'New',
			)
		);
		$zone     = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( 'After', $zone->name );
		$this->assertSame( 'after', $zone->slug );
		$this->assertSame( 'New', $zone->description );
	}

	public function test_update_zone_with_no_fields_keeps_name_and_slug_but_clears_description(): void {
		$zone_id = $this->create_zone( 'same', 'Same', 'Will be cleared' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id" );
		$zone     = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( 'Same', $zone->name );
		$this->assertSame( 'same', $zone->slug );
		$this->assertSame( '', $zone->description );
	}

	public function test_delete_zone_removes_the_zone(): void {
		$zone_id = $this->create_zone( 'doomed' );

		$response = $this->dispatch( 'DELETE', "/zones/$zone_id" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertFalse( Zoninator()->get_zone( $zone_id ) );
	}

	public function test_get_zone_posts_returns_the_feed_in_zone_order(): void {
		$zone_id  = $this->create_zone( 'feed' );
		$post_ids = self::factory()->post->create_many( 3 );
		$ordered  = array( $post_ids[2], $post_ids[0], $post_ids[1] );
		Zoninator()->add_zone_posts( $zone_id, $ordered );

		$response = $this->dispatch( 'GET', "/zones/$zone_id/posts" );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $ordered, array_map( 'intval', wp_list_pluck( $data, 'ID' ) ) );
		$this->assertSame(
			array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' ),
			array_keys( (array) $data[0] )
		);
	}

	public function test_update_zone_posts_sets_the_order(): void {
		$zone_id  = $this->create_zone( 'ordered' );
		$post_ids = self::factory()->post->create_many( 3 );
		$ordered  = array( $post_ids[1], $post_ids[2], $post_ids[0] );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => $ordered ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( $ordered, wp_list_pluck( Zoninator()->get_zone_posts( $zone_id ), 'ID' ) );
	}

	public function test_update_zone_posts_with_an_empty_list_empties_the_zone(): void {
		$zone_id = $this->create_zone( 'emptied' );
		Zoninator()->add_zone_posts( $zone_id, array( self::factory()->post->create() ) );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => array() ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( array(), Zoninator()->get_zone_posts( $zone_id ) );
	}

	public function test_update_zone_posts_requires_post_ids(): void {
		$zone_id = $this->create_zone( 'required' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts" );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'] );
	}

	public function test_update_zone_posts_rejects_non_numeric_post_ids(): void {
		$zone_id = $this->create_zone( 'numeric' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => array( 'abc' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	public function test_update_zone_posts_with_a_missing_post_returns_a_message_only_body(): void {
		$zone_id = $this->create_zone( 'missing-post' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => array( 999999 ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( array( 'message' => 'Invalid post id' ), $response->get_data() );
	}

	public function test_lock_zone_locks_an_unlocked_zone(): void {
		$zone_id = $this->create_zone( 'lockable' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/lock" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'zone_id'         => $zone_id,
				'timeout'         => Zoninator()->zone_lock_period,
				'max_lock_period' => Zoninator()->zone_max_lock_period,
			),
			$response->get_data()
		);
		$this->assertSame( $this->admin_id, (int) get_transient( Zoninator()->get_zone_meta_key( $zone_id ) ) );
	}

	public function test_lock_zone_reports_a_zone_locked_by_someone_else(): void {
		$zone_id = $this->create_zone( 'locked' );
		Zoninator()->lock_zone( $zone_id, self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/lock" );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			array(
				'zone_id' => $zone_id,
				'blocked' => true,
			),
			$response->get_data()
		);
	}

	public function test_lock_zone_with_zone_id_zero_is_a_bad_request(): void {
		$response = $this->dispatch( 'PUT', '/zones/0/lock' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'zone-id-required', $response->get_data()['code'] );
		$this->assertSame( 'zone id required', $response->get_data()['message'] );
	}

	/**
	 * Dispatch a request against the zoninator/v1 namespace.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path after the namespace.
	 * @param array  $params Request params.
	 * @return WP_REST_Response
	 */
	private function dispatch( $method, $path, $params = array() ) {
		$request = new WP_REST_Request( $method, '/zoninator/v1' . $path );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * @param string $slug        Slug.
	 * @param string $name        Name.
	 * @param string $description Description.
	 * @return int Zone term ID.
	 */
	private function create_zone( $slug, $name = '', $description = '' ) {
		$result = Zoninator()->insert_zone( $slug, $name, array( 'description' => $description ) );
		$this->assertNotWPError( $result );

		return (int) $result['term_id'];
	}

	/**
	 * The zoninator/v1 routes, reduced to what callers depend on.
	 *
	 * @return array
	 */
	private function registered_routes() {
		$routes = array();
		foreach ( rest_get_server()->get_routes( 'zoninator/v1' ) as $route => $handlers ) {
			foreach ( $handlers as $handler ) {
				$args = array();
				foreach ( $handler['args'] as $name => $arg ) {
					$args[ $name ] = array_intersect_key( $arg, array_flip( array( 'type', 'required', 'default', 'items' ) ) );
					ksort( $args[ $name ] );
				}
				ksort( $args );

				$methods = array_keys( $handler['methods'] );
				sort( $methods );

				$routes[ $route ][] = array(
					'methods' => $methods,
					'args'    => $args,
				);
			}
		}

		return $routes;
	}

	private function expected_routes(): array {
		$zone_fields = function ( $with_default ) {
			$field = array( 'required' => false, 'type' => 'string' );
			if ( $with_default ) {
				$field = array( 'default' => '' ) + $field;
			}

			return array(
				'description' => $field,
				'name'        => $field,
				'slug'        => $field,
			);
		};
		$editable    = array( 'PATCH', 'POST', 'PUT' );
		$zone_id     = array(
			'required' => true,
			'type'     => 'integer',
		);

		return array(
			'/zoninator/v1'                                => array(
				array(
					'methods' => array( 'GET' ),
					'args'    => array(
						'context'   => array( 'default' => 'view' ),
						'namespace' => array( 'default' => 'zoninator/v1' ),
					),
				),
			),
			'/zoninator/v1/zones'                          => array(
				array(
					'methods' => array( 'GET' ),
					'args'    => array(),
				),
				array(
					'methods' => array( 'POST' ),
					'args'    => $zone_fields( true ),
				),
			),
			'/zoninator/v1/zones/(?P<zone_id>[\d]+)'       => array(
				array(
					'methods' => $editable,
					'args'    => $zone_fields( false ),
				),
				array(
					'methods' => array( 'DELETE' ),
					'args'    => array(),
				),
			),
			'/zoninator/v1/zones/(?P<zone_id>[\d]+)/posts' => array(
				array(
					'methods' => array( 'GET' ),
					'args'    => array( 'zone_id' => $zone_id ),
				),
				array(
					'methods' => $editable,
					'args'    => array(
						'post_ids' => array(
							'items'    => array( 'type' => 'integer' ),
							'required' => true,
							'type'     => 'array',
						),
						'zone_id'  => $zone_id,
					),
				),
			),
			'/zoninator/v1/zones/(?P<zone_id>[\d]+)/lock'  => array(
				array(
					'methods' => $editable,
					'args'    => array( 'zone_id' => $zone_id ),
				),
			),
		);
	}
}
