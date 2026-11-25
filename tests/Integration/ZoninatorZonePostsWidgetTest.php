<?php
declare( strict_types = 1 );

/**
 * Integration tests for the Zone Posts widget.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use WP_Query;
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

		$zone          = self::zones()->insert( 'widget-zone', 'Widget zone', array( 'description' => 'About the zone' ) );
		$this->zone_id = (int) $zone['term_id'];
		$this->widget  = new Zoninator_ZonePosts_Widget();

		wp_cache_delete( 'widget-zone-posts', 'widget' );
		wp_cache_delete( 'widget-zone-posts-save_blocked', 'widget' );
	}

	public function test_widget_lists_zone_posts_in_order_under_the_zone_name(): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First post' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second post' ) );
		self::zones()->add_posts( $this->zone_id, array( $second, $first ) );

		$html = $this->render( array( 'show_description' => 0 ) );

		$this->assertStringContainsString( '<h2>Widget zone</h2>', $html );
		$this->assertStringNotContainsString( 'About the zone', $html );
		$this->assertLessThan( strpos( $html, 'First post' ), strpos( $html, 'Second post' ) );
		$this->assertStringContainsString( 'href="' . get_permalink( $first ) . '"', $html );
	}

	public function test_widget_html_filter_replaces_the_post_list(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Filtered post' ) );
		self::zones()->add_posts( $this->zone_id, $post_id );
		$received = array();
		$filter   = static function ( $html, $posts, $query ) use ( &$received ) {
			$received = array( $html, wp_list_pluck( $posts, 'ID' ), $query instanceof WP_Query );
			return '<p class="custom-zone">Custom</p>';
		};
		add_filter( 'zoninator_zone_posts_widget_html', $filter, 10, 3 );

		$html = $this->render( array() );

		remove_filter( 'zoninator_zone_posts_widget_html', $filter, 10 );
		$this->assertSame( array( false, array( $post_id ), true ), $received );
		$this->assertStringContainsString( '<h2>Widget zone</h2>', $html );
		$this->assertStringContainsString( '<p class="custom-zone">Custom</p>', $html );
		$this->assertStringNotContainsString( '<ul>', $html );
	}

	public function test_widget_shows_the_description_when_asked(): void {
		self::zones()->add_posts( $this->zone_id, array( self::factory()->post->create() ) );

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
		self::zones()->add_posts( $this->zone_id, array( self::factory()->post->create(), $draft ) );

		$this->assertStringNotContainsString( 'Draft post', $this->render( array( 'show_description' => 0 ) ) );
	}

	/**
	 * The widget caches its output, so a change to the zone must clear it.
	 *
	 * @dataProvider data_zone_changes
	 *
	 * @param string $change Which change to make.
	 */
	public function test_widget_shows_a_zone_change_straight_away( string $change ): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First post' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second post' ) );
		self::zones()->add_posts( $this->zone_id, array( $first ) );

		// Creating posts briefly blocks the cache from being saved. Let it save.
		wp_cache_delete( 'widget-zone-posts-save_blocked', 'widget' );
		$this->render( array( 'show_description' => 0 ) );

		switch ( $change ) {
			case 'add':
				self::zones()->add_posts( $this->zone_id, array( $second ), true );
				$this->assertStringContainsString( 'Second post', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'remove':
				self::zones()->remove_posts( $this->zone_id, array( $first ) );
				$this->assertStringNotContainsString( 'First post', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'rename':
				self::zones()->update( $this->zone_id, array( 'name' => 'Renamed zone' ) );
				$this->assertStringContainsString( '<h2>Renamed zone</h2>', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'delete':
				self::zones()->delete( $this->zone_id );
				$this->assertSame( '', $this->render( array( 'show_description' => 0 ) ) );
				break;
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_zone_changes(): array {
		return array(
			'post added'   => array( 'add' ),
			'post removed' => array( 'remove' ),
			'zone renamed' => array( 'rename' ),
			'zone deleted' => array( 'delete' ),
		);
	}

	/**
	 * A value of 0 turns off the pause after the cache is cleared.
	 */
	public function test_save_pause_filter_can_turn_the_pause_off(): void {
		add_filter( 'zoninator_zone_posts_widget_block_save_cache_seconds', '__return_zero' );

		( new Zoninator_ZonePosts_Widget() )->flush_widget_cache();

		remove_filter( 'zoninator_zone_posts_widget_block_save_cache_seconds', '__return_zero' );
		$this->assertFalse( wp_cache_get( 'widget-zone-posts-save_blocked', 'widget' ) );
	}

	public function test_widget_for_an_empty_zone_outputs_nothing(): void {
		$this->assertSame( '', $this->render( array( 'show_description' => 0 ) ) );
	}

	public function test_widget_saved_without_a_description_setting_still_renders(): void {
		self::zones()->add_posts( $this->zone_id, array( self::factory()->post->create( array( 'post_title' => 'Only post' ) ) ) );

		$this->assertStringContainsString( 'Only post', $this->render( array() ) );
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
		self::zones()->insert( 'other-zone', 'Other zone' );

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
