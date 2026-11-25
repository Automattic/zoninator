<?php
/**
 * Integration tests for the layout and wording of the zone screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use WP_Term;

class ZoninatorZoneScreenTest extends TestCase {

	private function render_zone_edit( $zone = null ): string {
		ob_start();
		zoninator_container()->get( Screen::class )->render_zone_edit( $zone );
		return (string) ob_get_clean();
	}

	private function create_zone(): WP_Term {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone = Zoninator()->insert_zone( 'screen-zone', 'Screen zone' );
		return Zoninator()->get_zone( (int) $zone['term_id'] );
	}

	public function test_zone_content_comes_before_the_zone_details(): void {
		$output = $this->render_zone_edit( $this->create_zone() );

		$content = strpos( $output, 'Zone content</h2>' );
		$details = strpos( $output, 'Zone details</h2>' );

		$this->assertIsInt( $content );
		$this->assertIsInt( $details );
		$this->assertLessThan( $details, $content );
	}

	public function test_content_saves_without_a_save_button(): void {
		$output = $this->render_zone_edit( $this->create_zone() );

		$this->assertStringNotContainsString( 'id="zone-posts-save"', $output );
		$this->assertStringContainsString( '<div id="zone-posts-notices"></div>', $output );
		$this->assertStringContainsString( '<span class="spinner"></span>', $output );
	}

	public function test_one_field_adds_content(): void {
		$output = $this->render_zone_edit( $this->create_zone() );

		$this->assertStringNotContainsString( 'zone-post-latest', $output );
		$this->assertStringContainsString( '<label for="zone-post-search">Add content</label>', $output );
	}

	public function test_new_zone_screen_has_only_the_form(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$output = $this->render_zone_edit();

		$this->assertStringNotContainsString( 'postbox', $output );
		$this->assertStringContainsString( 'value="Add New Zone"', $output );
	}

	public function test_existing_zone_is_updated_with_update(): void {
		$this->assertStringContainsString( 'value="Update"', $this->render_zone_edit( $this->create_zone() ) );
	}

	public function test_items_show_the_status_label(): void {
		$zone    = $this->create_zone();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Labelled story' ) );

		ob_start();
		zoninator_container()->get( Screen::class )->render_info_column( get_post( $post_id ), $zone );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<span class="zone-post-status">(Published)</span>', $output );
	}

	public function test_items_leave_out_the_edit_link_for_a_post_the_user_cannot_edit(): void {
		$zone    = $this->create_zone();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Read-only story' ) );
		// Zone managers need not be able to edit every post they curate.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		zoninator_container()->get( Screen::class )->render_info_column( get_post( $post_id ), $zone );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'class="edit"', $output );
		$this->assertStringContainsString( 'class="view"', $output );
	}

	public function test_date_filter_shows_labels(): void {
		ob_start();
		zoninator_container()->get( Screen::class )->render_date_filter();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '>All dates</option>', $output );
		$this->assertStringContainsString( '>Today</option>', $output );
		$this->assertStringContainsString( '>Yesterday</option>', $output );
	}
}
