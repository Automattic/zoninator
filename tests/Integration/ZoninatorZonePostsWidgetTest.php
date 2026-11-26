<?php
declare( strict_types = 1 );

/**
 * Integration tests for the Zone Posts widget.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\ZonePostsWidget;
use WP_Query;

final class ZoninatorZonePostsWidgetTest extends TestCase {

	/**
	 * @var int
	 */
	private $zone_id;

	/**
	 * @var ZonePostsWidget
	 */
	private $widget;

	public function setUp(): void {
		parent::setUp();

		$zone          = self::insert_zone( 'widget-zone', 'Widget zone', array( 'description' => 'About the zone' ) );
		$this->zone_id = (int) $zone['term_id'];
		$this->widget  = new ZonePostsWidget();
	}

	public function test_widget_lists_zone_posts_in_order_under_the_zone_name(): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First post' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second post' ) );
		self::add_zone_posts( $this->zone_id, array( $second, $first ) );

		$html = $this->render( array( 'show_description' => 0 ) );

		$this->assertStringContainsString( '<h2>Widget zone</h2>', $html );
		$this->assertStringNotContainsString( 'About the zone', $html );
		$this->assertLessThan( strpos( $html, 'First post' ), strpos( $html, 'Second post' ) );
		$this->assertStringContainsString( 'href="' . get_permalink( $first ) . '"', $html );
	}

	public function test_widget_html_filter_replaces_the_post_list(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Filtered post' ) );
		self::add_zone_posts( $this->zone_id, $post_id );
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
		self::add_zone_posts( $this->zone_id, array( self::factory()->post->create() ) );

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
		self::add_zone_posts( $this->zone_id, array( self::factory()->post->create(), $draft ) );

		$this->assertStringNotContainsString( 'Draft post', $this->render( array( 'show_description' => 0 ) ) );
	}

	/**
	 * The widget keeps no cache of its own, so it can't show a zone as it was.
	 *
	 * @dataProvider data_zone_changes
	 *
	 * @param string $change Which change to make.
	 */
	public function test_widget_shows_a_zone_change_straight_away( string $change ): void {
		$first  = self::factory()->post->create( array( 'post_title' => 'First post' ) );
		$second = self::factory()->post->create( array( 'post_title' => 'Second post' ) );
		self::add_zone_posts( $this->zone_id, array( $first ) );
		$this->render( array( 'show_description' => 0 ) );

		switch ( $change ) {
			case 'add':
				self::add_zone_posts( $this->zone_id, array( $second ), true );
				$this->assertStringContainsString( 'Second post', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'remove':
				self::remove_zone_posts( $this->zone_id, array( $first ) );
				$this->assertStringNotContainsString( 'First post', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'rename':
				self::update_zone( $this->zone_id, array( 'name' => 'Renamed zone' ) );
				$this->assertStringContainsString( '<h2>Renamed zone</h2>', $this->render( array( 'show_description' => 0 ) ) );
				break;
			case 'delete':
				self::delete_zone( $this->zone_id );
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
	 * The widget has no cache of its output because WordPress caches the
	 * zone's query. If a later render ran queries, that reason would be gone.
	 */
	public function test_a_second_render_runs_no_queries(): void {
		global $wpdb;
		self::add_zone_posts( $this->zone_id, self::factory()->post->create_many( 3 ) );

		$first   = $this->render( array( 'show_description' => 1 ) );
		$queries = $wpdb->num_queries;
		$second  = $this->render( array( 'show_description' => 1 ) );

		$this->assertSame( $first, $second );
		$this->assertSame( $queries, $wpdb->num_queries );
	}

	/**
	 * Sites remove the widget, and themes print it, by its class name, so
	 * WordPress must have it filed under that name.
	 */
	public function test_wordpress_knows_the_widget_by_its_class_name(): void {
		global $wp_widget_factory;

		$this->assertInstanceOf( ZonePostsWidget::class, $wp_widget_factory->widgets[ ZonePostsWidget::class ] );
		$this->assertSame( $wp_widget_factory->widgets[ ZonePostsWidget::class ], $wp_widget_factory->get_widget_object( 'zoninator_zoneposts_widget' ) );
	}

	/**
	 * Saved widgets are stored under the name WordPress derived from the old
	 * class name, Zoninator_ZonePosts_Widget, so the renamed class must keep it.
	 */
	public function test_the_widget_keeps_the_id_base_saved_widgets_use(): void {
		$this->assertSame( 'zoninator_zoneposts_widget', $this->widget->id_base );
		$this->assertSame( 'widget_zoninator_zoneposts_widget', $this->widget->option_name );
	}

	public function test_the_widget_prints_the_widget_by_its_class_name(): void {
		self::add_zone_posts( $this->zone_id, self::factory()->post->create() );

		ob_start();
		the_widget( ZonePostsWidget::class, array( 'zone_id' => $this->zone_id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Widget zone', $html );
	}

	/**
	 * the_widget() gives the widget no ID, so two zones printed that way must
	 * each show their own posts.
	 */
	public function test_the_widget_prints_each_zone_it_is_asked_for(): void {
		$other = self::insert_zone( 'other-widget-zone', 'Other zone' );
		self::add_zone_posts( $this->zone_id, self::factory()->post->create() );
		self::add_zone_posts( (int) $other['term_id'], self::factory()->post->create() );

		ob_start();
		the_widget( ZonePostsWidget::class, array( 'zone_id' => $this->zone_id ) );
		the_widget( ZonePostsWidget::class, array( 'zone_id' => (int) $other['term_id'] ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'Widget zone', $html );
		$this->assertStringContainsString( 'Other zone', $html );
	}

	public function test_unregister_widget_removes_the_widget_by_its_class_name(): void {
		global $wp_widget_factory;
		$widget = $wp_widget_factory->widgets[ ZonePostsWidget::class ];

		unregister_widget( ZonePostsWidget::class );
		$removed = ! isset( $wp_widget_factory->widgets[ ZonePostsWidget::class ] );
		$wp_widget_factory->widgets[ ZonePostsWidget::class ] = $widget;

		$this->assertTrue( $removed );
	}

	public function test_widget_for_an_empty_zone_outputs_nothing(): void {
		$this->assertSame( '', $this->render( array( 'show_description' => 0 ) ) );
	}

	public function test_widget_saved_without_a_description_setting_still_renders(): void {
		self::add_zone_posts( $this->zone_id, array( self::factory()->post->create( array( 'post_title' => 'Only post' ) ) ) );

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
		self::insert_zone( 'other-zone', 'Other zone' );

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
