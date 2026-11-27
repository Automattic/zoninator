<?php
/**
 * The Zone Posts widget.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use WP_Widget;

/**
 * The classic Zone Posts widget, which lists a zone's posts in a sidebar.
 *
 * Unlike the classes around it, it's public: themes print it with
 * the_widget( ZonePostsWidget::class ), and sites extend it or remove it
 * with unregister_widget( ZonePostsWidget::class ). Widgets registers it.
 *
 * @since 1.0.0 Before, Zoninator_ZonePosts_Widget.
 */
class ZonePostsWidget extends WP_Widget {

	/**
	 * The widget's ID base. Saved widgets and their places in sidebars are
	 * keyed by it, so it can't change. It's the name WordPress derived from
	 * the class when the class was Zoninator_ZonePosts_Widget.
	 */
	public const ID_BASE = 'zoninator_zoneposts_widget';

	/**
	 * Constructor. It takes no arguments, as WordPress builds widgets from
	 * their class names, so the widget gets what it needs from the container.
	 */
	public function __construct() {
		$this->alt_option_name = 'widget_zone_posts';

		parent::__construct(
			self::ID_BASE,
			__( 'Zone Posts', 'zoninator' ),
			array(
				'classname'   => 'widget-zone-posts',
				'description' => __( 'Use this widget to display a list of posts from any zone.', 'zoninator' ),
			)
		);
	}

	/**
	 * Prints the widget: the zone's name, its description if asked for, and its posts.
	 *
	 * Nothing is cached here. WordPress caches the zone's query, which is the
	 * costly part, and clears that cache itself when a post or its place in a
	 * zone changes.
	 *
	 * @param array $args     The sidebar's markup: before_widget, after_widget, before_title and after_title.
	 * @param array $instance The widget's settings: zone_id and show_description.
	 */
	public function widget( $args, $instance ) {
		$zone_id          = $instance['zone_id'] ?? 0;
		$show_description = empty( $instance['show_description'] ) ? 0 : 1;
		if ( ! $zone_id ) {
			return;
		}

		$zones = zoninator_container()->get( Zones::class );
		$zone  = $zones->get( $zone_id );
		if ( ! $zone ) {
			return;
		}

		$query = $zones->get_query( $zone_id );
		$posts = $query->posts;
		if ( empty( $posts ) ) {
			return;
		}
		?>
		<?php echo wp_kses_post( $args['before_widget'] ); ?>

		<?php echo wp_kses_post( $args['before_title'] ) . esc_html( $zone->name ) . wp_kses_post( $args['after_title'] ); ?>

		<?php if ( ! empty( $zone->description ) && $show_description ) : ?>
			<p class="description"><?php echo esc_html( $zone->description ); ?></p>
			<?php
		endif;
		?>

		<?php
		/**
		 * Filters the HTML for the posts in a Zone Posts widget, so a theme can
		 * show them its own way, such as with thumbnails or excerpts.
		 *
		 * Return false to keep the default list of links. The HTML is passed
		 * through wp_kses_post(). It's built on every page view, so a callback
		 * that does costly work should cache its own result.
		 *
		 * @since 0.12.0
		 *
		 * @param string|false $html  HTML for the posts, or false for the default list.
		 * @param WP_Post[]    $posts The zone's posts, in order.
		 * @param WP_Query     $query The zone query that found them.
		 */
		$html = apply_filters( 'zoninator_zone_posts_widget_html', false, $posts, $query );

		if ( false !== $html ) {
			echo wp_kses_post( $html );
		} else {
			$this->render_posts( $posts );
		}
		?>

		<?php echo wp_kses_post( $args['after_widget'] ); ?>
		<?php
	}

	/**
	 * Prints the default list of links to the zone's posts.
	 *
	 * @param WP_Post[] $posts The zone's posts, in order.
	 */
	private function render_posts( $posts ): void {
		?>
		<ul>
			<?php foreach ( $posts as $post ) : ?>
				<li>
					<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
						<?php echo esc_html( get_the_title( $post->ID ) ); ?>
					</a>
				</li>
				<?php
			endforeach;
			?>
		</ul>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance                     = $old_instance;
		$new_instance                 = wp_parse_args(
			(array) $new_instance,
			array(
				'zone_id'          => 0,
				'show_description' => 0,
			)
		);
		$instance['zone_id']          = absint( $new_instance['zone_id'] );
		$instance['show_description'] = $new_instance['show_description'] ? 1 : 0;

		return $instance;
	}

	public function form( $instance ) {
		// select - zone
		// checkbox - show description
		$zones = zoninator_container()->get( Zones::class )->get_all();
		if ( empty( $zones ) ) {
			esc_html_e( 'You need to create at least one zone before you use this widget!', 'zoninator' );
			return;
		}

		$zone_id          = isset( $instance['zone_id'] ) ? absint( $instance['zone_id'] ) : 0;
		$show_description = isset( $instance['show_description'] ) ? (bool) $instance['show_description'] : true;
		?>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'zone_id' ) ); ?>"><?php esc_html_e( 'Zone:', 'zoninator' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'zone_id' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'zone_id' ) ); ?>">
				<option value="0" <?php selected( $zone_id, 0 ); ?>>
					<?php esc_html_e( '-- Select a zone --', 'zoninator' ); ?>
				</option>

				<?php foreach ( $zones as $zone ) : ?>
					<option value="<?php echo esc_attr( $zone->term_id ); ?>" <?php selected( $zone_id, $zone->term_id ); ?>>
						<?php echo esc_html( $zone->name ); ?>
					</option>
					<?php
				endforeach;
				?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_description' ) ); ?>">
				<input id="<?php echo esc_attr( $this->get_field_id( 'show_description' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_description' ) ); ?>" <?php checked( true, $show_description ); ?> type="checkbox" value="1" />
				<?php esc_html_e( 'Show zone description in widget', 'zoninator' ); ?>
			</label>
		</p>
		<?php
	}
}
