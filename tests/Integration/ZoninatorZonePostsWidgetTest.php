<?php
declare( strict_types = 1 );

/**
 * Integration tests for the Zone Posts widget.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Zoninator_ZonePosts_Widget;

final class ZoninatorZonePostsWidgetTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var Zoninator_ZonePosts_Widget
	 */
	private $widget;

	public function setUp(): void {
		parent::setUp();

		$zone          = \Zoninator()->insert_zone( 'widget-zone', 'Widget zone', array( 'description' => 'About the zone' ) );
		$this->zone_id = (int) $zone['term_id'];
		$this->widget  = new Zoninator_ZonePosts_Widget();

		wp_cache_delete( 'widget-zone-posts', 'widget' );
		wp_cache_delete( 'widget-zone-posts-save_blocked', 'widget' );
	}

	public function test_widget_lists_zone_posts_in_order_under_the_zone_name(): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First post' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second post' ) );
		\Zoninator()->add_zone_posts( $this->zone_id, array( $second, $first ) );

		$html = $this->render( array( 'show_description' => 0 ) );

		$this->assertStringContainsString( '<h2>Widget zone</h2>', $html );
		$this->assertStringNotContainsString( 'About the zone', $html );
		$this->assertLessThan( strpos( $html, 'First post' ), strpos( $html, 'Second post' ) );
		$this->assertStringContainsString( 'href="' . get_permalink( $first ) . '"', $html );
	}

	public function test_widget_shows_the_description_when_asked(): void {
		\Zoninator()->add_zone_posts( $this->zone_id, array( self::factory()->post->create() ) );

		$html = $this->render( array( 'show_description' => 1 ) );

		$this->assertStringContainsString( '<p class="description">About the zone</p>', $html );
	}

	public function test_widget_leaves_out_unpublished_posts(): void {
		$draft = self::factory()->post->create(
			array(
				'post_title'  => 'Draft post',
				'post_status' => 'draft',
			)
		);
		\Zoninator()->add_zone_posts( $this->zone_id, array( self::factory()->post->create(), $draft ) );

		$this->assertStringNotContainsString( 'Draft post', $this->render( array( 'show_description' => 0 ) ) );
	}

	public function test_update_keeps_only_a_zone_id_and_a_description_flag(): void {
		$instance = $this->widget->update(
			array(
				'zone_id'          => '12abc',
				'show_description' => 'yes',
				'extra'            => 'dropped',
			),
			array()
		);

		$this->assertSame(
			array(
				'zone_id'          => 12,
				'show_description' => 1,
			),
			$instance
		);
	}

	public function test_form_lists_the_zones_with_the_saved_one_selected(): void {
		\Zoninator()->insert_zone( 'other-zone', 'Other zone' );

		ob_start();
		$this->widget->form( array( 'zone_id' => $this->zone_id ) );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option value="' . $this->zone_id . '"\s+selected=\'selected\'>\s*Widget zone/', $html );
		$this->assertStringContainsString( 'Other zone', $html );
	}

	/**
	 * Render the widget for the test zone.
	 *
	 * @param array $instance Extra instance settings.
	 * @return string
	 */
	private function render( array $instance ): string {
		ob_start();
		$this->widget->widget(
			array(
				'widget_id'     => 'zone-posts-1',
				'before_widget' => '<section>',
				'after_widget'  => '</section>',
				'before_title'  => '<h2>',
				'after_title'   => '</h2>',
			),
			array_merge( array( 'zone_id' => $this->zone_id ), $instance )
		);

		return ob_get_clean();
	}
}
