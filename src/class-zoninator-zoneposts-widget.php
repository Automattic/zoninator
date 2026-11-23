<?php
// phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * Zone Posts widget class
 */
class Zoninator_ZonePosts_Widget extends WP_Widget {

	public function __construct() {
		$widget_ops = array(
			'classname'   => 'widget-zone-posts',
			'description' => __( 'Use this widget to display a list of posts from any zone.', 'zoninator' ),
		);

		$this->alt_option_name = 'widget_zone_posts';
		add_action( 'save_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( &$this, 'flush_widget_cache' ) );
		add_action( 'zoninator_add_zone_posts', array( $this, 'flush_widget_cache' ) );
		add_action( 'zoninator_remove_zone_posts', array( $this, 'flush_widget_cache' ) );
		add_action( 'edited_' . Zoninator::ZONE_TAXONOMY, array( $this, 'flush_widget_cache' ) );

		parent::__construct(
			false,
			__( 'Zone Posts', 'zoninator' ),
			$widget_ops
		);
	}

	public function widget( $args, $instance ) {
		$cache_key = 'widget-zone-posts';
		$cache     = wp_cache_get( $cache_key, 'widget' );

		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo wp_kses_post( $cache[ $args['widget_id'] ] );
			return;
		}

		$zone_id          = $instance['zone_id'] ?? 0;
		$show_description = empty( $instance['show_description'] ) ? 0 : 1;
		if ( ! $zone_id ) {
			return;
		}

		$zone = z_get_zone( $zone_id );
		if ( ! $zone ) {
			return;
		}

		$query = z_get_zone_query( $zone_id );
		$posts = $query->posts;
		if ( empty( $posts ) ) {
			return;
		}

		ob_start();
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
		 * Return false to keep the default list of links. The HTML is cached with
		 * the rest of the widget, and passed through wp_kses_post().
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
		$cache[ $args['widget_id'] ] = ob_get_flush();

		$save_blocked = wp_cache_get( $cache_key . '-save_blocked', 'widget' );
		if ( $save_blocked ) {
			// Save is blocked while the cache flush is in progress.
			return;
		}

		wp_cache_set( 'widget-zone-posts', $cache, 'widget' );
	}

	/**
	 * Prints the default list of links to the zone's posts.
	 *
	 * @param WP_Post[] $posts The zone's posts, in order.
	 */
	private function render_posts( $posts ) {
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
		$this->flush_widget_cache();
		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset( $alloptions['widget-zone-posts'] ) ) {
			delete_option( 'widget-zone-posts' );
		}

		return $instance;
	}

	public function flush_widget_cache() {
		$cache_key = 'widget-zone-posts';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- breaking change to rename this filter with the correct prefix.
		$block_save_cache_seconds = absint( apply_filters( 'zone_posts_widget_block_save_cache_seconds', 5 ) );
		if ( $block_save_cache_seconds > 0 ) {
			// This key will block updating the cache for n seconds so the following cache delete can propagate
			// phpcs:ignore -- WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- not changing behavior now.
			wp_cache_set( $cache_key . '-save_blocked', 1, 'widget', $block_save_cache_seconds );
		}

		wp_cache_delete( $cache_key, 'widget' );
	}

	public function form( $instance ) {
		// select - zone
		// checkbox - show description
		$zones = z_get_zones();
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
