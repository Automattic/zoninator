<?php
declare( strict_types = 1 );

/**
 * Integration tests for the Zones screen form handler.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenLocation;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZoneForm;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use RuntimeException;
use WPDieException;

final class ZoninatorAdminControllerTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	public function setUp(): void {
		parent::setUp();

		$this->zone_id = self::insert_zone( 'form-zone', 'Form zone', array( 'description' => 'Before' ) );

		// The handler redirects and exits on success, so stop it at the redirect.
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		);
	}

	public function tearDown(): void {
		$_REQUEST = array();
		$_POST    = array();
		parent::tearDown();
	}

	public function test_insert_creates_the_zone_and_redirects_to_it(): void {
		$location = $this->submit(
			'insert',
			array(
				'name'        => 'New zone',
				'slug'        => 'new-zone',
				'description' => 'Fresh',
			)
		);

		$zone = self::zones()->get( 'new-zone' );
		$this->assertSame( 'New zone', $zone->name );
		$this->assertSame( 'Fresh', $zone->description );
		$this->assertStringContainsString( 'zone_id=' . $zone->term_id, $location );
		$this->assertStringContainsString( 'message=insert-success', $location );
	}

	public function test_update_changes_the_zone(): void {
		$location = $this->submit(
			'update',
			array(
				'zone_id'     => $this->zone_id,
				'name'        => 'Renamed zone',
				'description' => 'After',
			)
		);

		$zone = self::zones()->get( $this->zone_id );
		$this->assertSame( 'Renamed zone', $zone->name );
		$this->assertSame( 'After', $zone->description );
		$this->assertStringContainsString( 'message=update-success', $location );
	}

	/**
	 * A failure used to come back as message=error-general, which the screen
	 * prints in its green "updated" box. It belongs in the red one.
	 */
	public function test_a_failed_insert_comes_back_as_an_error(): void {
		$location = $this->submit( 'insert', array( 'name' => '' ) );

		$this->assertStringContainsString( 'error=general', $location );
		$this->assertStringNotContainsString( 'message=', $location );
	}

	public function test_a_failed_update_comes_back_to_the_zone_as_an_error(): void {
		self::insert_zone( 'taken', 'Taken' );

		$location = $this->submit(
			'update',
			array(
				'zone_id' => $this->zone_id,
				'name'    => 'Form zone',
				'slug'    => 'taken',
			)
		);

		$this->assertStringContainsString( 'zone_id=' . $this->zone_id, $location );
		$this->assertStringContainsString( 'error=general', $location );
		$this->assertStringNotContainsString( 'message=', $location );
		$this->assertSame( 'form-zone', self::zones()->get( $this->zone_id )->slug );
	}

	public function test_insert_and_update_keep_backslashes(): void {
		// WordPress slashes $_POST, so send the fields as a browser's form post would arrive.
		$this->submit(
			'insert',
			wp_slash(
				array(
					'name'        => 'Back\\slash',
					'slug'        => 'backslash',
					'description' => 'C:\\path',
				)
			)
		);

		$zone = self::zones()->get( 'backslash' );
		$this->assertSame( 'Back\\slash', $zone->name );
		$this->assertSame( 'C:\\path', $zone->description );

		$this->submit(
			'update',
			wp_slash(
				array(
					'zone_id'     => $zone->term_id,
					'name'        => 'Two\\back\\slashes',
					'description' => 'D:\\path',
				)
			)
		);

		$zone = self::zones()->get( (int) $zone->term_id );
		$this->assertSame( 'Two\\back\\slashes', $zone->name );
		$this->assertSame( 'D:\\path', $zone->description );
	}

	/**
	 * WordPress decodes an encoded quote when it saves a term, which used to
	 * leave the stored length wrong; with a semicolon after the quote, the
	 * description could not be repaired and read as empty.
	 */
	public function test_insert_keeps_a_quote_followed_by_a_semicolon(): void {
		$this->submit(
			'insert',
			wp_slash(
				array(
					'name'        => 'The "Daily"; late',
					'slug'        => 'quoted',
					'description' => 'He said "hi"; she didn\'t',
				)
			)
		);

		$zone = self::zones()->get( 'quoted' );
		$this->assertSame( 'The "Daily"; late', $zone->name );
		$this->assertSame( 'He said "hi"; she didn\'t', $zone->description );
		$this->assertSame(
			array( 'description' => 'He said "hi"; she didn\'t' ),
			unserialize( get_term_field( 'description', $zone->term_id, Taxonomy::NAME, 'raw' ), array( 'allowed_classes' => false ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		);
	}

	public function test_insert_keeps_non_ascii_text_in_the_description(): void {
		$this->submit(
			'insert',
			array(
				'name'        => 'Crème',
				'slug'        => 'creme',
				'description' => 'Crème <b>brûlée</b> — “à la carte”',
			)
		);

		$zone = self::zones()->get( 'creme' );
		$this->assertSame( 'Crème', $zone->name );
		$this->assertSame( 'Crème &lt;b&gt;brûlée&lt;/b&gt; — “à la carte”', $zone->description );
		// The zone reader repairs damaged descriptions, so check what was stored is valid as it is.
		$this->assertSame(
			array( 'description' => 'Crème &lt;b&gt;brûlée&lt;/b&gt; — “à la carte”' ),
			unserialize( get_term_field( 'description', $zone->term_id, Taxonomy::NAME, 'raw' ), array( 'allowed_classes' => false ) ) // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
		);
	}

	public function test_delete_removes_the_zone_and_its_posts(): void {
		$post_id = self::factory()->post->create();
		self::add_zone_posts( $this->zone_id, array( $post_id ) );
		$meta_key = self::zones()->get_meta_key( $this->zone_id );

		$location = $this->submit( 'delete', array( 'zone_id' => $this->zone_id ) );

		$this->assertFalse( self::zones()->get( $this->zone_id ) );
		$this->assertSame( '', get_post_meta( $post_id, $meta_key, true ) );
		$this->assertStringContainsString( 'message=delete-success', $location );
	}

	/**
	 * The user may add zones, so only the action keeps this from adding one.
	 */
	public function test_update_without_a_zone_does_not_add_one(): void {
		$this->attempt(
			'update',
			array(
				'name' => 'Added zone',
				'slug' => 'added-zone',
			)
		);

		$this->assertFalse( self::zones()->get( 'added-zone' ) );
	}

	/**
	 * The user may edit this zone, so only the action keeps this from changing it.
	 */
	public function test_insert_with_a_zone_does_not_change_it(): void {
		$this->attempt(
			'insert',
			array(
				'zone_id'     => $this->zone_id,
				'name'        => 'Changed zone',
				'description' => 'Changed',
			)
		);

		$zone = self::zones()->get( $this->zone_id );
		$this->assertSame( 'Form zone', $zone->name );
		$this->assertSame( 'Before', $zone->description );
		$this->assertSame( 'Changed', self::zones()->get( 'changed-zone' )->description );
	}

	/**
	 * @dataProvider data_actions
	 *
	 * @param string $action Form action.
	 */
	public function test_action_refuses_a_user_who_cannot_manage_zones( string $action ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$this->assert_refused( $action, true );
	}

	/**
	 * @dataProvider data_actions
	 *
	 * @param string $action Form action.
	 */
	public function test_action_refuses_a_request_without_a_valid_nonce( string $action ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assert_refused( $action, false );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_actions(): array {
		return array(
			'insert' => array( 'insert' ),
			'update' => array( 'update' ),
			'delete' => array( 'delete' ),
		);
	}

	/**
	 * Submit the form as an editor and return where the handler redirected.
	 *
	 * @param string $action Form action.
	 * @param array  $fields Form fields.
	 * @return string
	 */
	private function submit( string $action, array $fields ): string {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->set_request( $action, $fields, true );

		try {
			zoninator_container()->get( ZoneForm::class )->handle_request();
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}

		$this->fail( 'Expected the handler to redirect.' );
	}

	/**
	 * Submit the form as an editor, whether the handler redirects or stops the request.
	 *
	 * @param string $action Form action.
	 * @param array  $fields Form fields.
	 */
	private function attempt( string $action, array $fields ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->set_request( $action, $fields, true );

		try {
			zoninator_container()->get( ZoneForm::class )->handle_request();
		} catch ( RuntimeException | WPDieException $e ) {
			return;
		}
	}

	/**
	 * Check the current user's request is stopped and changes nothing.
	 *
	 * @param string $action     Form action.
	 * @param bool   $with_nonce Whether to send a valid nonce.
	 */
	private function assert_refused( string $action, bool $with_nonce ): void {
		$this->set_request(
			$action,
			array(
				'zone_id' => 'insert' === $action ? 0 : $this->zone_id,
				'name'    => 'Changed zone',
				'slug'    => 'changed-zone',
			),
			$with_nonce
		);

		try {
			zoninator_container()->get( ZoneForm::class )->handle_request();
			$this->fail( 'Expected the handler to stop the request.' );
		} catch ( WPDieException $e ) {
			// A request without a nonce gets WordPress's own refusal; a user who may not gets Zoninator's.
			$this->assertSame( $with_nonce ? "Sorry, you're not supposed to do that..." : 'The link you followed has expired.', $e->getMessage() );
		}

		$this->assertSame( 'Form zone', self::zones()->get( $this->zone_id )->name );
		$this->assertFalse( self::zones()->get( 'changed-zone' ) );
	}

	/**
	 * @param string $action     Form action.
	 * @param array  $fields     Form fields.
	 * @param bool   $with_nonce Whether to send a valid nonce.
	 */
	private function set_request( string $action, array $fields, bool $with_nonce ): void {
		$_POST = $fields;
		if ( $with_nonce ) {
			$_POST['_wpnonce'] = wp_create_nonce( zoninator_container()->get( ScreenLocation::class )->get_nonce_action( $action ) );
		}

		$_REQUEST = array_merge(
			$_POST,
			array(
				'page'   => ScreenLocation::SLUG,
				'action' => $action,
			)
		);
	}
}
