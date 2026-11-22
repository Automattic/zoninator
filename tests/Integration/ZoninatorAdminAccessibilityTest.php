<?php
/**
 * Integration tests for the accessibility of the zone admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class Zoninator_Admin_Accessibility_Test extends TestCase {

	private function capture( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	private function create_zone_with_post(): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$zone    = Zoninator()->insert_zone( 'a11y-zone', 'Accessibility zone' );
		$zone    = Zoninator()->get_zone( (int) $zone['term_id'] );
		$post_id = self::factory()->post->create( array( 'post_title' => 'Zoned story' ) );
		Zoninator()->add_zone_posts( $zone, $post_id );

		return array( $zone, get_post( $post_id ) );
	}

	private function render_zone_post(): string {
		list( $zone, $post ) = $this->create_zone_with_post();

		return $this->capture(
			static function () use ( $post, $zone ) {
				Zoninator()->admin_page_zone_post( $post, $zone );
			}
		);
	}

	private function render_zone_edit(): string {
		list( $zone ) = $this->create_zone_with_post();

		return $this->capture(
			static function () use ( $zone ) {
				Zoninator()->admin_page_zone_edit( $zone );
			}
		);
	}

	public function test_zone_items_have_keyboard_move_and_remove_buttons(): void {
		$output = $this->render_zone_post();

		foreach ( array( 'move-up' => 'Move up', 'move-down' => 'Move down', 'delete' => 'Remove' ) as $class => $text ) {
			$this->assertMatchesRegularExpression( '#<button (?=[^>]*type="button")[^>]*class="button-link ' . $class . '"[^>]*>' . $text . '<span class="screen-reader-text"> “Zoned story”</span></button>#u', $output );
		}
		$this->assertStringNotContainsString( 'href="#"', $output );
	}

	public function test_save_status_is_a_live_region(): void {
		$this->assertStringContainsString( '<p id="zone-posts-save-info" class="zone-posts-save-info" role="status">', $this->render_zone_edit() );
	}

	public function test_zone_content_controls_are_labelled(): void {
		add_action( 'zoninator_advanced_search_fields', array( Zoninator(), 'zone_advanced_search_date_filter' ) );

		$output = $this->render_zone_edit();

		$this->assertStringContainsString( '<label for="zone-post-latest">', $output );
		$this->assertStringContainsString( 'id="zone-post-latest"', $output );
		$this->assertStringContainsString( '<label for="zone_advanced_filter_date" class="screen-reader-text">', $output );
		$this->assertStringContainsString( 'aria-describedby="zone-post-search-description"', $output );
		$this->assertStringContainsString( 'id="zone-post-search-description"', $output );
		$this->assertMatchesRegularExpression( '#<input type="text" id="zone-name" name="name" value="[^"]*" required />#', $output );
	}

	public function test_filter_toggle_is_a_button_that_reports_its_state(): void {
		$output = $this->capture(
			static function () {
				Zoninator()->zone_advanced_search_filters();
			}
		);

		$this->assertStringContainsString( '<button type="button" class="button-link zone-toggle-advanced-search" aria-expanded="false" aria-controls="zone-advanced-search-filters"', $output );
		$this->assertStringContainsString( 'id="zone-advanced-search-filters"', $output );
	}
}
