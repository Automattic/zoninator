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
		$this->create_zone( 'public-zone' );
		add_filter( 'zoninator_rest_get_zones_permissions_check', '__return_true' );

		$response = $this->dispatch( 'GET', '/zones' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'public-zone' ), wp_list_pluck( $response->get_data(), 'slug' ) );
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
		$zone_id = $this->create_zone( 'denied', 'Denied' );
		$params  = array(
			'slug' => 'new-zone',
			'name' => 'Renamed',
		);
		if ( $post ) {
			$params['post_ids'] = array( self::factory()->post->create() );
		}
		wp_set_current_user( $this->subscriber_id );

		$response = $this->dispatch( $method, sprintf( $path, $zone_id ), $params );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'permission-denied', $response->get_data()['code'] );
		$this->assertSame( "Sorry, you're not supposed to do that...", $response->get_data()['message'] );
		// Refused before the callback runs, so nothing changed.
		$this->assertSame( 'Denied', Zoninator()->get_zone( $zone_id )->name );
		$this->assertFalse( Zoninator()->get_zone( 'new-zone' ) );
		$this->assertSame( array(), Zoninator()->get_zone_posts( $zone_id ) );
		$this->assertFalse( Zoninator()->is_zone_locked( $zone_id ) );
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
			'add zone post'     => array( 'POST', '/zones/%d/posts/1', false ),
			'remove zone post'  => array( 'DELETE', '/zones/%d/posts/1', false ),
			'search posts'      => array( 'GET', '/search', false ),
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
	public function test_zone_routes_return_an_error_code_for_a_missing_zone( $method, $path, $post ): void {
		$params = $post ? array( 'post_ids' => array( self::factory()->post->create() ) ) : array();

		$response = $this->dispatch( $method, sprintf( $path, 999999 ), $params );

		$this->assert_rest_error( $response, 404, 'zone-not-found', 'Zone not found.' );
	}

	public static function data_zone_routes(): array {
		return array(
			'update zone'       => array( 'PUT', '/zones/%d', false ),
			'delete zone'       => array( 'DELETE', '/zones/%d', false ),
			'get zone posts'    => array( 'GET', '/zones/%d/posts', false ),
			'update zone posts' => array( 'PUT', '/zones/%d/posts', true ),
			'add zone post'     => array( 'POST', '/zones/%d/posts/1', false ),
			'remove zone post'  => array( 'DELETE', '/zones/%d/posts/1', false ),
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

	public function test_create_zone_without_slug_or_name_returns_an_error_code(): void {
		$response = $this->dispatch( 'POST', '/zones' );

		$this->assert_rest_error( $response, 400, 'zone-empty-slug', 'Slug is a required field.' );
	}

	public function test_create_zone_with_a_duplicate_returns_an_error_code(): void {
		$this->create_zone( 'taken', 'Taken' );

		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name' => 'Taken',
				'slug' => 'taken',
			)
		);

		$this->assert_rest_error( $response, 400, 'term_exists', 'A term with the name provided already exists in this taxonomy.' );
	}

	public function test_create_zone_escapes_html_in_the_name_and_description(): void {
		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name'        => '&<>!@#(',
				'slug'        => 'special',
				'description' => '&<>!@#(',
			)
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( '&amp;&lt;&gt;!@#(', $response->get_data()['name'] );
		$this->assertSame( '&amp;&lt;&gt;!@#(', $response->get_data()['description'] );
	}

	public function test_create_zone_keeps_backslashes(): void {
		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name'        => 'Back\\slash',
				'slug'        => 'backslash',
				'description' => 'C:\\path',
			)
		);
		$zone     = Zoninator()->get_zone( 'backslash' );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'Back\\slash', $response->get_data()['name'] );
		$this->assertSame( 'C:\\path', $response->get_data()['description'] );
		$this->assertSame( 'Back\\slash', $zone->name );
		$this->assertSame( 'C:\\path', $zone->description );
	}

	public function test_create_zone_stores_a_valid_description_with_accented_letters(): void {
		$response = $this->dispatch(
			'POST',
			'/zones',
			array(
				'name'        => 'Crème',
				'slug'        => 'creme',
				'description' => 'Crème brûlée',
			)
		);
		$zone     = Zoninator()->get_zone( 'creme' );

		$this->assertSame( 201, $response->get_status() );
		// The zone reader repairs damaged descriptions, so check what was stored is valid as it is.
		$this->assertSame(
			array( 'description' => 'Crème brûlée' ),
			unserialize( get_term_field( 'description', $zone->term_id, \Zoninator::ZONE_TAXONOMY, 'raw' ), array( 'allowed_classes' => false ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		);
	}

	public function test_update_zone_keeps_backslashes(): void {
		$zone_id = $this->create_zone( 'update-backslash' );

		$response = $this->dispatch(
			'PUT',
			"/zones/$zone_id",
			array(
				'name'        => 'Back\\slash',
				'description' => 'C:\\path',
			)
		);
		$zone     = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Back\\slash', $zone->name );
		$this->assertSame( 'C:\\path', $zone->description );
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

	public function test_update_zone_with_no_fields_changes_nothing(): void {
		$zone_id = $this->create_zone( 'same', 'Same', 'Kept' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id" );
		$zone     = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( 'Same', $zone->name );
		$this->assertSame( 'same', $zone->slug );
		$this->assertSame( 'Kept', $zone->description );
	}

	public function test_update_zone_name_keeps_the_description(): void {
		$zone_id = $this->create_zone( 'renamed', 'Old name', 'Kept' );

		$this->dispatch( 'PUT', "/zones/$zone_id", array( 'name' => 'New name' ) );
		$zone = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 'New name', $zone->name );
		$this->assertSame( 'Kept', $zone->description );
	}

	public function test_update_zone_over_rest_with_an_empty_description_clears_it(): void {
		$zone_id = $this->create_zone( 'rest-cleared', 'REST cleared', 'Gone' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id", array( 'description' => '' ) );
		$zone     = Zoninator()->get_zone( $zone_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', $zone->description );
		$this->assertSame( 'REST cleared', $zone->name );
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

	public function test_get_zone_posts_for_an_empty_zone_returns_an_empty_list(): void {
		$zone_id = $this->create_zone( 'empty' );

		$response = $this->dispatch( 'GET', "/zones/$zone_id/posts" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
	}

	public function test_get_zone_posts_hides_the_content_of_a_password_protected_post(): void {
		$zone_id = $this->create_zone( 'protected' );
		$post_id = self::factory()->post->create(
			array(
				'post_title'    => 'Protected post',
				'post_content'  => 'Secret content',
				'post_excerpt'  => 'Secret excerpt',
				'post_password' => 'secret123',
			)
		);
		Zoninator()->add_zone_posts( $zone_id, array( $post_id ) );
		wp_set_current_user( 0 );

		$response = $this->dispatch( 'GET', "/zones/$zone_id/posts" );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
		$this->assertSame( 'Protected post', $data[0]->post_title );
		$this->assertSame( '', $data[0]->post_content );
		$this->assertSame( '', $data[0]->post_excerpt );
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

	public function test_add_zone_post_appends_the_post_and_returns_its_row(): void {
		$zone_id  = $this->create_zone( 'appended' );
		$first_id = self::factory()->post->create();
		$post_id  = self::factory()->post->create();
		Zoninator()->add_zone_posts( $zone_id, array( $first_id ) );

		$response = $this->dispatch( 'POST', "/zones/$zone_id/posts/$post_id" );

		ob_start();
		self::admin_screen()->render_zone_post( get_post( $post_id ), Zoninator()->get_zone( $zone_id ) );
		$row = ob_get_clean();

		$this->assertSame( 201, $response->get_status() );
		$this->assertStringContainsString( 'data-post-id="' . $post_id . '"', $row );
		$this->assertSame( array( 'html' => $row ), $response->get_data() );
		$this->assertSame( array( $first_id, $post_id ), wp_list_pluck( Zoninator()->get_zone_posts( $zone_id ), 'ID' ) );
	}

	public function test_add_zone_post_renders_columns_sites_add(): void {
		$zone_id = $this->create_zone( 'columns' );
		$post_id = self::factory()->post->create();
		add_filter(
			'zoninator_zone_post_columns',
			static function ( $columns ) {
				$columns['custom'] = static function () {
					echo 'zoninator-custom-column';
				};
				return $columns;
			}
		);

		$response = $this->dispatch( 'POST', "/zones/$zone_id/posts/$post_id" );

		$this->assertStringContainsString( 'zoninator-custom-column', $response->get_data()['html'] );
	}

	/**
	 * In a REST request the zone query sees only published posts, so the new
	 * post must still go after a scheduled one at the end of the zone.
	 */
	public function test_add_zone_post_goes_after_a_scheduled_post(): void {
		$zone_id      = $this->create_zone( 'after-scheduled' );
		$scheduled_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		$post_id      = self::factory()->post->create();
		Zoninator()->add_zone_posts( $zone_id, array( self::factory()->post->create(), $scheduled_id ) );

		$this->dispatch( 'POST', "/zones/$zone_id/posts/$post_id" );

		$meta_key = Zoninator()->get_zone_meta_key( $zone_id );
		$this->assertSame( '2', get_post_meta( $scheduled_id, $meta_key, true ) );
		$this->assertSame( '3', get_post_meta( $post_id, $meta_key, true ) );
	}

	public function test_add_zone_post_with_a_missing_post_returns_an_error_code(): void {
		$zone_id = $this->create_zone( 'add-missing' );

		$response = $this->dispatch( 'POST', "/zones/$zone_id/posts/999999" );

		$this->assert_rest_error( $response, 400, 'invalid-post-id', 'Invalid post ID.' );
	}

	public function test_remove_zone_post_removes_only_that_post(): void {
		$zone_id    = $this->create_zone( 'removed' );
		$kept_id    = self::factory()->post->create();
		$removed_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $zone_id, array( $kept_id, $removed_id ) );

		$response = $this->dispatch( 'DELETE', "/zones/$zone_id/posts/$removed_id" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );
		$this->assertSame( array( $kept_id ), wp_list_pluck( Zoninator()->get_zone_posts( $zone_id ), 'ID' ) );
	}

	public function test_remove_zone_post_with_a_missing_post_returns_an_error_code(): void {
		$zone_id = $this->create_zone( 'remove-missing' );

		$response = $this->dispatch( 'DELETE', "/zones/$zone_id/posts/999999" );

		$this->assert_rest_error( $response, 400, 'invalid-post-id', 'Invalid post ID.' );
	}

	/**
	 * In a REST request the zone query sees only published posts, so
	 * replacing the list must still take out a scheduled post left off it.
	 */
	public function test_update_zone_posts_removes_a_scheduled_post_left_off_the_list(): void {
		$zone_id      = $this->create_zone( 'replace-scheduled' );
		$post_id      = self::factory()->post->create();
		$scheduled_id = self::factory()->post->create(
			array(
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);
		Zoninator()->add_zone_posts( $zone_id, array( $scheduled_id, $post_id ) );

		$this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => array( $post_id ) ) );

		$this->assertSame( '', get_post_meta( $scheduled_id, Zoninator()->get_zone_meta_key( $zone_id ), true ) );
	}

	/**
	 * Curating a zone, and taking its lock, needs the zone management
	 * capability, as on the Zones screen, not the one for editing the zone's
	 * details. Otherwise the screen would reload for ever when the lock fails.
	 */
	/**
	 * Each route needs exactly one of the three zone capabilities. They all
	 * default to edit_others_posts, so give each its own capability and a
	 * user only that one, which fails if a route checks the wrong one.
	 *
	 * @dataProvider data_routes_and_capabilities
	 *
	 * @param string $method     HTTP method.
	 * @param string $path       Route, with %1$d for the zone ID, %2$d for a post in it and %3$d for one that isn't.
	 * @param array  $params     Request params; post_ids of null means the post.
	 * @param string $needed     Capability the route needs: add, edit or manage.
	 * @param int    $status     Status when allowed.
	 * @param string $capability Capability the user has.
	 */
	public function test_each_route_needs_its_own_zone_capability( $method, $path, $params, $needed, $status, $capability ): void {
		foreach ( array( 'add', 'edit', 'manage' ) as $kind ) {
			add_filter(
				"zoninator_{$kind}_zone_cap",
				static function () use ( $kind ) {
					return "zoninator_test_{$kind}";
				}
			);
		}
		$zone_id = $this->create_zone( 'matrix' );
		$post_id = self::factory()->post->create();
		Zoninator()->add_zone_posts( $zone_id, array( $post_id ) );
		if ( array_key_exists( 'post_ids', $params ) ) {
			$params['post_ids'] = array( $post_id );
		}
		$user = self::factory()->user->create_and_get( array( 'role' => 'subscriber' ) );
		$user->add_cap( "zoninator_test_{$capability}" );
		wp_set_current_user( $user->ID );

		$response = $this->dispatch( $method, sprintf( $path, $zone_id, $post_id, self::factory()->post->create() ), $params );

		$this->assertSame( $needed === $capability ? $status : 403, $response->get_status() );
	}

	public static function data_routes_and_capabilities(): array {
		$routes = array(
			'create zone'       => array( 'POST', '/zones', array( 'slug' => 'matrix-new' ), 'add', 201 ),
			'update zone'       => array( 'PUT', '/zones/%1$d', array( 'name' => 'Renamed' ), 'edit', 200 ),
			'delete zone'       => array( 'DELETE', '/zones/%1$d', array(), 'edit', 200 ),
			'update zone posts' => array( 'PUT', '/zones/%1$d/posts', array( 'post_ids' => null ), 'manage', 200 ),
			'add zone post'     => array( 'POST', '/zones/%1$d/posts/%3$d', array(), 'manage', 201 ),
			'remove zone post'  => array( 'DELETE', '/zones/%1$d/posts/%2$d', array(), 'manage', 200 ),
			'search posts'      => array( 'GET', '/search', array(), 'manage', 200 ),
			'lock zone'         => array( 'PUT', '/zones/%1$d/lock', array(), 'manage', 200 ),
		);

		$cases = array();
		foreach ( $routes as $name => $route ) {
			foreach ( array( 'add', 'edit', 'manage' ) as $capability ) {
				$cases[ "$name, $capability capability" ] = array_merge( $route, array( $capability ) );
			}
		}

		return $cases;
	}

	/**
	 * Sites can limit editing to some zones, so the edit routes must ask about the zone being changed.
	 *
	 * @dataProvider data_zone_edit_routes
	 *
	 * @param string $method HTTP method.
	 * @param array  $params Request params.
	 * @param int    $status Status when allowed.
	 */
	public function test_edit_routes_ask_about_the_zone_being_changed( $method, $params, $status ): void {
		$open_id   = $this->create_zone( 'open', 'Open' );
		$closed_id = $this->create_zone( 'closed', 'Closed' );
		$asked     = array();
		add_filter(
			'zoninator_current_user_can_edit_zone',
			static function ( $can, $zone_id ) use ( $closed_id, &$asked ) {
				$asked[] = (int) $zone_id;
				return $can && (int) $zone_id !== $closed_id;
			},
			10,
			2
		);

		$refused = $this->dispatch( $method, "/zones/$closed_id", $params );
		$allowed = $this->dispatch( $method, "/zones/$open_id", $params );

		$this->assertSame( 403, $refused->get_status() );
		$this->assertSame( 'Closed', Zoninator()->get_zone( $closed_id )->name );
		$this->assertSame( $status, $allowed->get_status() );
		$this->assertSame( array( $closed_id, $open_id ), $asked );
	}

	public static function data_zone_edit_routes(): array {
		return array(
			'update zone' => array( 'PUT', array( 'name' => 'Renamed' ), 200 ),
			'delete zone' => array( 'DELETE', array(), 200 ),
		);
	}

	public function test_search_returns_each_match_with_its_labels(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'zoninator_rest_search_canary' ) );

		$response = $this->dispatch( 'GET', '/search', array( 'term' => 'zoninator_rest_search_canary' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array(
					'title'             => 'zoninator_rest_search_canary',
					'post_id'           => $post_id,
					'date'              => get_the_time( get_option( 'date_format' ), $post_id ),
					'post_type'         => 'post',
					'post_type_label'   => 'Post',
					'post_status'       => 'publish',
					'post_status_label' => 'Published',
				),
			),
			$response->get_data()
		);
	}

	public function test_search_without_a_term_lists_the_latest_content_without_excluded_posts(): void {
		$post_ids = self::factory()->post->create_many( 3 );

		$response = $this->dispatch( 'GET', '/search', array( 'exclude' => array( $post_ids[1] ) ) );
		$ids      = wp_list_pluck( $response->get_data(), 'post_id' );

		$this->assertContains( $post_ids[0], $ids );
		$this->assertNotContains( $post_ids[1], $ids );
	}

	public function test_search_finds_a_scheduled_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'zoninator_scheduled_canary',
				'post_status' => 'future',
				'post_date'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			)
		);

		$response = $this->dispatch( 'GET', '/search', array( 'term' => 'zoninator_scheduled_canary' ) );

		$this->assertSame( array( $post_id ), wp_list_pluck( $response->get_data(), 'post_id' ) );
	}

	/**
	 * Excluded posts must not come back, and the page must still be full when
	 * they're the newest matches. Issue #75's slow NOT IN clause must not be used.
	 *
	 * @dataProvider data_search_terms
	 *
	 * @param string $term Search term. Empty for the latest content.
	 */
	public function test_search_leaves_out_excluded_posts_and_fills_the_page( string $term ): void {
		$limit    = '' === $term ? Zoninator()->get_posts_per_page() : 2;
		$post_ids = array();
		for ( $i = 1; $i <= $limit + 2; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => 'zoninator_exclude_canary ' . $i,
					'post_date'  => gmdate( 'Y-m-d H:i:s', time() - ( 100 - $i ) * HOUR_IN_SECONDS ),
				)
			);
		}
		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		// The newest two posts are already in the zone.
		$response = $this->dispatch(
			'GET',
			'/search',
			array(
				'term'    => $term,
				'limit'   => $limit,
				'exclude' => array_slice( $post_ids, -2 ),
			)
		);

		remove_filter( 'query', $logger );
		$this->assertSame( array_reverse( array_slice( $post_ids, 0, $limit ) ), wp_list_pluck( $response->get_data(), 'post_id' ) );
		$this->assertNotEmpty( $queries );
		foreach ( $queries as $query ) {
			$this->assertDoesNotMatchRegularExpression( '/\.ID NOT IN/', $query );
		}
	}

	public static function data_search_terms(): array {
		return array(
			'text search'    => array( 'zoninator_exclude_canary' ),
			'latest content' => array( '' ),
		);
	}

	/**
	 * The IDs to exclude come from the request, so a long list must not make
	 * the query fetch a matching number of extra rows.
	 */
	public function test_search_with_a_long_exclude_list_keeps_the_query_limit(): void {
		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		$this->dispatch(
			'GET',
			'/search',
			array(
				'term'    => 'zoninator_exclude_canary',
				'limit'   => 2,
				'exclude' => range( 1000000, 1004999 ),
			)
		);

		remove_filter( 'query', $logger );
		$limits = array();
		foreach ( $queries as $query ) {
			if ( preg_match( '/LIMIT \d+, (\d+)/', $query, $matches ) ) {
				$limits[] = (int) $matches[1];
			}
		}
		$this->assertNotEmpty( $limits );
		$this->assertLessThanOrEqual( 102, max( $limits ) );
	}

	/**
	 * Sites change the search with these filters, so they must get the same
	 * arguments the old admin-ajax action gave them.
	 */
	public function test_search_applies_the_search_filters(): void {
		$args = null;
		add_filter(
			'zoninator_search_args',
			static function ( $query_args ) use ( &$args ) {
				$args = $query_args;
				return $query_args;
			}
		);
		add_filter(
			'zoninator_search_results_post',
			static function ( $result ) {
				$result['extra'] = 'added';
				return $result;
			}
		);
		self::factory()->post->create( array( 'post_title' => 'zoninator_rest_filter_canary' ) );

		$response = $this->dispatch( 'GET', '/search', array( 'term' => 'zoninator_rest_filter_canary' ) );

		$this->assertSame(
			array(
				's'                => 'zoninator_rest_filter_canary',
				'posts_per_page'   => Zoninator()->get_posts_per_page(),
				'post_type'        => Zoninator()->get_supported_post_types(),
				'post_status'      => array( 'publish', 'future' ),
				'order'            => 'DESC',
				'orderby'          => 'post_date',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			),
			$args
		);
		$this->assertSame( 'added', $response->get_data()[0]['extra'] );
	}

	public function test_search_by_post_id_returns_that_post_without_a_text_search(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Found by ID' ) );
		$queries = array();
		$logger  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $logger );

		$response = $this->dispatch( 'GET', '/search', array( 'term' => (string) $post_id ) );

		remove_filter( 'query', $logger );
		$this->assertSame( array( $post_id ), wp_list_pluck( $response->get_data(), 'post_id' ) );
		$this->assertSame( 'Found by ID', $response->get_data()[0]['title'] );
		foreach ( $queries as $query ) {
			$this->assertStringNotContainsString( 'LIKE', $query );
		}
	}

	/**
	 * A site that narrows the search with zoninator_search_args must not be
	 * bypassed by typing a post ID.
	 */
	public function test_search_by_post_id_respects_the_search_args_filter(): void {
		$sport_cat = self::factory()->category->create( array( 'slug' => 'sport' ) );
		$sport_id  = self::factory()->post->create( array( 'post_category' => array( $sport_cat ) ) );
		$other_id  = self::factory()->post->create();
		$filter    = static function ( $args ) {
			$args['category_name'] = 'sport';
			return $args;
		};
		add_filter( 'zoninator_search_args', $filter );

		$sport_ids = wp_list_pluck( $this->dispatch( 'GET', '/search', array( 'term' => (string) $sport_id ) )->get_data(), 'post_id' );
		$other_ids = wp_list_pluck( $this->dispatch( 'GET', '/search', array( 'term' => (string) $other_id ) )->get_data(), 'post_id' );

		remove_filter( 'zoninator_search_args', $filter );
		$this->assertSame( array( $sport_id ), $sport_ids );
		$this->assertNotContains( $other_id, $other_ids );
	}

	/**
	 * Factory post titles hold sequence numbers that can contain an ID, so
	 * these check which posts come back rather than the whole list.
	 */
	public function test_search_by_post_id_falls_back_to_text_for_a_post_it_cannot_offer(): void {
		$draft_id    = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		$excluded_id = self::factory()->post->create();
		$page_id     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$titled_id   = self::factory()->post->create( array( 'post_title' => "Number $draft_id" ) );

		$draft_ids = wp_list_pluck( $this->dispatch( 'GET', '/search', array( 'term' => (string) $draft_id ) )->get_data(), 'post_id' );
		$this->assertContains( $titled_id, $draft_ids );
		$this->assertNotContains( $draft_id, $draft_ids );

		$excluded = $this->dispatch(
			'GET',
			'/search',
			array(
				'term'    => (string) $excluded_id,
				'exclude' => array( $excluded_id ),
			)
		);
		$this->assertNotContains( $excluded_id, wp_list_pluck( $excluded->get_data(), 'post_id' ) );

		$this->assertNotContains( $page_id, wp_list_pluck( $this->dispatch( 'GET', '/search', array( 'term' => (string) $page_id ) )->get_data(), 'post_id' ) );
	}

	/**
	 * Sites focus the search on the zone being edited, so both query args
	 * filters get its ID.
	 */
	public function test_search_passes_the_zone_id_to_the_query_args_filters(): void {
		$zone_ids = array();
		$spy      = static function ( $args, $zone_id = null ) use ( &$zone_ids ) {
			$zone_ids[] = $zone_id;
			return $args;
		};
		add_filter( 'zoninator_search_args', $spy, 10, 2 );
		add_filter( 'zoninator_recent_posts_args', $spy, 10, 2 );

		$this->dispatch(
			'GET',
			'/search',
			array(
				'term'    => 'anything',
				'zone_id' => 42,
			)
		);
		$this->dispatch( 'GET', '/search', array( 'zone_id' => 42 ) );
		$this->dispatch( 'GET', '/search' );

		$this->assertSame( array( 42, 42, 0 ), $zone_ids );
	}

	public function test_search_rejects_a_limit_over_100(): void {
		$response = $this->dispatch(
			'GET',
			'/search',
			array(
				'term'  => 'anything',
				'limit' => 101,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
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

	/**
	 * @dataProvider data_logged_in
	 *
	 * @param bool $logged_in Whether the caller is logged in.
	 */
	public function test_update_zone_posts_rejects_post_ids_that_are_not_an_array( $logged_in ): void {
		$zone_id = $this->create_zone( 'not-an-array' );
		if ( ! $logged_in ) {
			wp_set_current_user( 0 );
		}

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => '5' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	public static function data_logged_in(): array {
		return array(
			'logged in'  => array( true ),
			'logged out' => array( false ),
		);
	}

	public function test_get_zones_returns_an_error_code_when_zones_cannot_be_read(): void {
		$taxonomy = $GLOBALS['wp_taxonomies'][ \Zoninator::ZONE_TAXONOMY ];
		unset( $GLOBALS['wp_taxonomies'][ \Zoninator::ZONE_TAXONOMY ] );

		try {
			$response = $this->dispatch( 'GET', '/zones' );
		} finally {
			$GLOBALS['wp_taxonomies'][ \Zoninator::ZONE_TAXONOMY ] = $taxonomy;
		}

		$this->assert_rest_error( $response, 400, 'zones-read-error', 'Zones could not be read.' );
	}

	public function test_update_zone_posts_with_a_missing_post_returns_an_error_code(): void {
		$zone_id = $this->create_zone( 'missing-post' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/posts", array( 'post_ids' => array( 999999 ) ) );

		$this->assert_rest_error( $response, 400, 'invalid-post-id', 'Invalid post ID.' );
	}

	public function test_lock_zone_locks_an_unlocked_zone(): void {
		$zone_id = $this->create_zone( 'lockable' );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/lock" );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'zone_id'         => $zone_id,
				'timeout'         => Zoninator()->get_zone_lock_period(),
				'max_lock_period' => Zoninator()->get_zone_max_lock_period(),
			),
			$response->get_data()
		);
		$this->assertSame( $this->admin_id, (int) get_transient( Zoninator()->get_zone_meta_key( $zone_id ) ) );
	}

	public function test_lock_zone_reports_a_zone_locked_by_someone_else(): void {
		$zone_id   = $this->create_zone( 'locked' );
		$holder_id = self::factory()->user->create(
			array(
				'role'         => 'editor',
				'display_name' => 'Lock Holder',
			)
		);
		Zoninator()->lock_zone( $zone_id, $holder_id );

		$response = $this->dispatch( 'PUT', "/zones/$zone_id/lock" );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			array(
				'code'    => 'zone-locked',
				'message' => 'Sorry, this zone is in use by Lock Holder and is currently locked. Please try again later.',
				'data'    => array( 'status' => 400 ),
				'zone_id' => $zone_id,
				'blocked' => true,
			),
			$response->get_data()
		);
		$this->assertSame( $holder_id, Zoninator()->is_zone_locked( $zone_id ) );
	}

	public function test_lock_zone_with_zone_id_zero_is_a_bad_request(): void {
		$response = $this->dispatch( 'PUT', '/zones/0/lock' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'zone-id-required', $response->get_data()['code'] );
		$this->assertSame( 'Zone ID is required.', $response->get_data()['message'] );
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
		$zone_post   = array(
			'post_id' => $zone_id,
			'zone_id' => $zone_id,
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
			'/zoninator/v1/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)' => array(
				array(
					'methods' => array( 'POST' ),
					'args'    => $zone_post,
				),
				array(
					'methods' => array( 'DELETE' ),
					'args'    => $zone_post,
				),
			),
			'/zoninator/v1/search'                         => array(
				array(
					'methods' => array( 'GET' ),
					'args'    => array(
						'cat'     => array( 'default' => 0 ),
						'date'    => array(
							'default' => '',
							'type'    => 'string',
						),
						'exclude' => array(
							'default' => array(),
							'items'   => array( 'type' => 'integer' ),
							'type'    => 'array',
						),
						'limit'   => array( 'type' => 'integer' ),
						'term'    => array(
							'default' => '',
							'type'    => 'string',
						),
						'zone_id' => array(
							'default' => 0,
							'type'    => 'integer',
						),
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

	/**
	 * Asserts a response is a REST error with this status, code and, if given, message.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int              $status   Expected HTTP status.
	 * @param string           $code     Expected error code.
	 * @param string|null      $message  Expected message, or null to require any message.
	 */
	private function assert_rest_error( $response, $status, $code, $message = null ): void {
		$data = $response->get_data();

		$this->assertSame( $status, $response->get_status() );
		$this->assertSame( $code, $data['code'] ?? null );
		$this->assertSame( $status, $data['data']['status'] ?? null );

		if ( null === $message ) {
			$this->assertNotEmpty( $data['message'] ?? '' );
		} else {
			$this->assertSame( $message, $data['message'] ?? null );
		}
	}
}
