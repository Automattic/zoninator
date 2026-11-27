<?php
/**
 * Integration tests for the layout and wording of the zone screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Admin\Screen;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenDataLoader;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ScreenView;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\SearchForm;
use Automattic\Zoninator\Infrastructure\WordPress\Admin\ZonePostRow;
use WP_Term;

class ZoninatorZoneScreenTest extends TestCase {

	private function render_zone_edit( $zone = null ): string {
		return self::render_zone_editor( $zone ? (int) $zone->term_id : 0 );
	}

	private function create_zone(): WP_Term {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		return self::zones()->get( self::insert_zone( 'screen-zone', 'Screen zone' ) );
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
		zoninator_container()->get( ZonePostRow::class )->render_info_column( get_post( $post_id ), $zone );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<span class="zone-post-status">(Published)</span>', $output );
	}

	public function test_items_leave_out_the_edit_link_for_a_post_the_user_cannot_edit(): void {
		$zone    = $this->create_zone();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Read-only story' ) );
		// Zone managers need not be able to edit every post they curate.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		zoninator_container()->get( ZonePostRow::class )->render_info_column( get_post( $post_id ), $zone );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'class="edit"', $output );
		$this->assertStringContainsString( 'class="view"', $output );
	}

	/**
	 * A message is good news and an error isn't, and the screen has a box for each.
	 */
	public function test_an_error_is_shown_as_an_error_and_a_message_as_a_message(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$view = zoninator_container()->get( ScreenView::class );
		$data = zoninator_container()->get( ScreenDataLoader::class )->load( 0 );

		ob_start();
		$view->render( $data, '', 'general' );
		$failed = (string) ob_get_clean();
		ob_start();
		$view->render( $data, 'insert-success', '' );
		$saved = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<div id="zone-message" class="error[^"]*">\s*<p>Sorry, something went wrong! Please try again\?<\/p>/', $failed );
		$this->assertStringNotContainsString( 'class="updated', $failed );
		$this->assertMatchesRegularExpression( '/<div id="zone-message" class="updated[^"]*">\s*<p>The zone was successfully created\.<\/p>/', $saved );
		$this->assertStringNotContainsString( 'class="error', $saved );
	}

	public function test_date_filter_shows_labels(): void {
		ob_start();
		zoninator_container()->get( SearchForm::class )->render_date_filter();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '>All dates</option>', $output );
		$this->assertStringContainsString( '>Today</option>', $output );
		$this->assertStringContainsString( '>Yesterday</option>', $output );
	}

	public function test_date_filter_starts_on_the_date_a_site_chooses(): void {
		add_filter(
			'zoninator_advanced_filter_date',
			static fn() => array(
				'all',
				array(
					'value' => '2001-02-03',
					'label' => 'Launch day',
				),
			)
		);
		add_filter( 'zoninator_advanced_filter_date_default', static fn() => '2001-02-03' );

		ob_start();
		zoninator_container()->get( SearchForm::class )->render_date_filter();
		$output = (string) ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option value="2001-02-03"\s+selected=\'selected\'>Launch day<\/option>/', $output );
		$this->assertStringNotContainsString( "selected='selected'>All dates", $output );
	}
}
