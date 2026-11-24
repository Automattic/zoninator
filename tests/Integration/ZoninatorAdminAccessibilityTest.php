<?php
/**
 * Integration tests for the accessibility of the zone admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class ZoninatorAdminAccessibilityTest extends TestCase {

	public function tearDown(): void {
		$_GET     = array();
		$_REQUEST = array();
		parent::tearDown();
	}

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

		foreach ( array( 'move-up' => 'Move up', 'move-down' => 'Move down', 'button-link-delete delete' => 'Remove' ) as $class => $text ) {
			$this->assertMatchesRegularExpression( '#<button (?=[^>]*type="button")(?=[^>]*aria-label="' . $text . ' “Zoned story”")[^>]*class="button-link ' . $class . '"[^>]*>' . $text . '</button>#u', $output );
		}
		$this->assertStringNotContainsString( 'href="#"', $output );
	}

	/**
	 * Ordering is this screen's main job, so the move buttons come first, in
	 * the same place on every row, whether or not the row has an Edit link.
	 */
	public function test_zone_item_actions_start_with_the_move_buttons(): void {
		preg_match_all( '#class="(?:button-link )?(?:button-link-delete )?([a-z-]+)"#', $this->render_zone_post(), $matches );

		$this->assertSame( array( 'move-up', 'move-down', 'edit', 'delete', 'view' ), array_values( array_intersect( $matches[1], array( 'move-up', 'move-down', 'edit', 'delete', 'view' ) ) ) );
	}

	public function test_save_status_is_a_live_region(): void {
		$this->assertStringContainsString( '<p id="zone-posts-save-info" class="zone-posts-save-info" role="status">', $this->render_zone_edit() );
	}

	public function test_zone_content_controls_are_labelled(): void {
		add_action( 'zoninator_advanced_search_fields', array( Zoninator(), 'zone_advanced_search_cat_filter' ) );
		add_action( 'zoninator_advanced_search_fields', array( Zoninator(), 'zone_advanced_search_date_filter' ) );

		$output = $this->render_zone_edit();

		$this->assertStringContainsString( '<label for="zone-post-search">', $output );
		$this->assertStringContainsString( '<label for="zone_advanced_filter_taxonomy" class="screen-reader-text">', $output );
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

	public function test_links_that_open_a_new_tab_say_so(): void {
		$output = $this->render_zone_post();

		$this->assertSame( 2, substr_count( $output, 'target="_blank"' ) );
		$this->assertStringContainsString( 'aria-label="Edit “Zoned story” (opens in a new tab)"', $output );
		$this->assertStringContainsString( 'aria-label="View “Zoned story” (opens in a new tab)"', $output );
	}

	public function test_zone_item_action_names_are_whole_translatable_strings(): void {
		$translate = static function ( $translation, $text, $domain ) {
			return 'zoninator' === $domain && 'Move up “%s”' === $text ? '„%s“ nach oben verschieben' : $translation;
		};
		add_filter( 'gettext', $translate, 10, 3 );
		$output = $this->render_zone_post();
		remove_filter( 'gettext', $translate, 10 );

		$this->assertStringContainsString( 'aria-label="„Zoned story“ nach oben verschieben"', $output );
	}

	public function test_zone_item_layout_table_is_presentational(): void {
		$this->assertStringContainsString( '<table role="presentation">', $this->render_zone_post() );
	}

	public function test_admin_page_has_a_single_h1(): void {
		list( $zone )        = $this->create_zone_with_post();
		$_GET['zone_id']     = $zone->term_id;
		$_REQUEST['zone_id'] = $zone->term_id;

		$output = $this->capture(
			static function () {
				Zoninator()->admin_page();
			}
		);

		$this->assertSame( 1, substr_count( $output, '<h1' ) );
		$this->assertStringContainsString( '<h2 class="hndle">Zone content</h2>', $output );
		$this->assertStringNotContainsString( '<h3>', $output );
	}
}
