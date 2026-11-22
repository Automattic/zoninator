<?php
/**
 * Integration tests for the layout and wording of the zone screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class Zoninator_Zone_Screen_Test extends TestCase {

	private function render_zone_edit( $zone = null ): string {
		ob_start();
		Zoninator()->admin_page_zone_edit( $zone );
		return (string) ob_get_clean();
	}

	private function create_zone(): \WP_Term {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$zone = Zoninator()->insert_zone( 'screen-zone', 'Screen zone' );
		return Zoninator()->get_zone( (int) $zone['term_id'] );
	}
	public function test_items_show_the_status_label(): void {
		$zone    = $this->create_zone();
		$post_id = self::factory()->post->create( array( 'post_title' => 'Labelled story' ) );

		ob_start();
		Zoninator()->admin_page_zone_post_col_info( get_post( $post_id ), $zone );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<span class="zone-post-status">(Published)</span>', $output );
	}

	public function test_date_filter_shows_labels(): void {
		ob_start();
		Zoninator()->zone_advanced_search_date_filter();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '>All dates</option>', $output );
		$this->assertStringContainsString( '>Today</option>', $output );
		$this->assertStringContainsString( '>Yesterday</option>', $output );
	}
}
