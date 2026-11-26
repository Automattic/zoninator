<?php
declare( strict_types = 1 );

/**
 * Integration tests for the zone details on the Zones screen: the form, the
 * read-only view a user gets when they can't change the zone, and the
 * actions each one fires.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Generator;

final class ZoninatorZoneDetailsViewTest extends TestCase {

	private const FIELD_HOOKS    = array( 'zoninator_pre_zone_fields', 'zoninator_post_zone_fields' );
	private const READONLY_HOOKS = array( 'zoninator_pre_zone_readonly', 'zoninator_post_zone_readonly' );

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * What each zone details action was given, by action.
	 *
	 * @var array<string, mixed>
	 */
	private $fired = array();

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->zone_id = self::insert_zone( 'details-zone', 'Details & zone', array( 'description' => 'About <the> "zone"' ) );

		foreach ( array_merge( self::FIELD_HOOKS, self::READONLY_HOOKS ) as $hook ) {
			add_action(
				$hook,
				function ( $zone ) use ( $hook ) {
					$this->fired[ $hook ] = $zone;
					echo '<!-- ' . esc_html( $hook ) . ' -->';
				}
			);
		}
	}

	public function test_a_user_who_may_edit_the_zone_gets_the_form(): void {
		$output = self::render_zone_editor( $this->zone_id );

		$this->assertStringContainsString( '<form id="zone-info"', $output );
		$this->assertStringNotContainsString( 'zone-info-readonly', $output );
		$this->assertStringContainsString( 'value="Details &amp; zone"', $output );
		$this->assertSame( self::FIELD_HOOKS, array_keys( $this->fired ) );
		$this->assertSame( $this->zone_id, $this->fired['zoninator_pre_zone_fields']->term_id );
		$this->assert_in_order( $output, '<!-- zoninator_pre_zone_fields -->', 'id="zone-name"', 'id="zone-description"', '<!-- zoninator_post_zone_fields -->' );
	}

	public function test_the_new_zone_form_gives_its_actions_an_empty_array(): void {
		self::render_zone_editor();

		$this->assertSame(
			array(
				'zoninator_pre_zone_fields'  => array(),
				'zoninator_post_zone_fields' => array(),
			),
			$this->fired
		);
	}

	/**
	 * @dataProvider data_users_who_cannot_change_the_zone
	 *
	 * @param string $reason Why the user can't change the zone.
	 */
	public function test_a_user_who_cannot_change_the_zone_gets_it_read_only( string $reason ): void {
		if ( 'locked' === $reason ) {
			self::locker()->lock( $this->zone_id, self::factory()->user->create( array( 'role' => 'editor' ) ) );
		} else {
			add_filter( 'zoninator_current_user_can_edit_zone', '__return_false' );
		}

		$output = self::render_zone_editor( $this->zone_id );

		$this->assertStringNotContainsString( '<form id="zone-info"', $output );
		$this->assertStringNotContainsString( 'id="zone-name"', $output );
		$this->assertStringContainsString( '<div id="zone-info-readonly" class="readonly">', $output );
		$this->assertStringContainsString( '<span>Details &amp; zone</span>', $output );
		$this->assertStringContainsString( '<span>About &lt;the&gt; &quot;zone&quot;</span>', $output );
		$this->assertSame( self::READONLY_HOOKS, array_keys( $this->fired ) );
		$this->assertSame( $this->zone_id, $this->fired['zoninator_post_zone_readonly']->term_id );
		$this->assert_in_order( $output, '<!-- zoninator_pre_zone_readonly -->', '<span>Details &amp; zone</span>', '<!-- zoninator_post_zone_readonly -->' );
	}

	public static function data_users_who_cannot_change_the_zone(): Generator {
		yield 'someone else holds the lock' => array( 'locked' );
		yield 'the user may not edit the zone' => array( 'not allowed' );
	}

	/**
	 * Asserts each string appears in the output, after the one before it.
	 *
	 * @param string $output  Rendered HTML.
	 * @param string ...$parts Strings, in the order they should appear.
	 */
	private function assert_in_order( string $output, string ...$parts ): void {
		$offset = 0;
		foreach ( $parts as $part ) {
			$position = strpos( $output, $part, $offset );
			$this->assertIsInt( $position, "Expected to find $part after offset $offset." );
			$offset = $position + strlen( $part );
		}
	}
}
