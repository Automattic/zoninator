<?php
/**
 * Zone Posts widget class
 */
class Zoninator_ZonePosts_Widget extends WP_Widget {

	function Zoninator_ZonePosts_Widget() {
		$widget_ops = array( 'classname' => 'widget-zone-posts', 'description' => __( 'Use this widget to display a list of posts from any zone.', 'zoninator' ) );
		parent::__construct( false, __( 'Zone Posts', 'zoninator' ), $widget_ops );
		$this->alt_option_name = 'widget_zone_posts';
		
		add_action( 'save_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'deleted_post', array( &$this, 'flush_widget_cache' ) );
		add_action( 'switch_theme', array( &$this, 'flush_widget_cache' ) );
	}

	function widget( $args, $instance ) {
		$cache = wp_cache_get( 'widget-zone-posts', 'widget' );
		
		if ( !is_array( $cache ) )
			$cache = array();
		
		if ( isset( $cache[$args['widget_id']] ) ) {
			echo $cache[$args['widget_id']];
			return;
		}
		
		ob_start();
		extract( $args );
		
		$zone_id = $instance['zone_id'] ? $instance['zone_id'] : 0;
		$show_description = $instance['show_description'] ? 1 : 0;
		
		if( ! $zone_id )
			return;
		
		$zone = z_get_zone( $zone_id );
		
		if( ! $zone )
			return;
			
		$posts = z_get_posts_in_zone( $zone_id );
		
		if( empty( $posts ) )
			return;
		
		?>
		<?php echo $before_widget; ?>
		
		<?php echo $before_title . esc_html( $zone->name ) . $after_title; ?>
		
		<?php if( ! empty( $zone->description ) && $show_description ) : ?>
			<p class="description"><?php echo esc_html( $zone->description ); ?></p>
		<?php endif; ?>
		
		<ul>
			<?php foreach( $posts as $post ) : ?>
				<li>
					<a href="<?php echo get_permalink( $post->ID ); ?>">
						<?php echo esc_html( get_the_title( $post->ID ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		
		<?php echo $after_widget; ?>
		<?php				
		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set( 'widget-zone-posts', $cache, 'widget' );
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array( 'zone_id' => 0, 'show_description' => 0 ) );
		
		$instance['zone_id'] = absint( $new_instance['zone_id'] );
		$instance['show_description'] = $new_instance['show_description'] ? 1 : 0;
		
		$this->flush_widget_cache();
		
		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget-zone-posts']) )
			delete_option( 'widget-zone-posts' );

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete( 'widget-zone-posts', 'widget' );
	}

	function form( $instance ) {
		// select - zone 
		// checkbox - show description
		
		$zones = z_get_zones();
		
		if( empty( $zones ) ) {
			_e( 'You need to create at least one zone before you use this widget!', 'zoninator' );
			return;
		}	
		
		$zone_id = isset( $instance['zone_id'] ) ? absint( $instance['zone_id'] ) : 0;
		$show_description = isset( $instance['show_description'] ) ? (bool)$instance['show_description'] : true;
		?>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'zone_id' ); ?>"><?php _e('Zone:'); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'zone_id' ); ?>" name="<?php echo $this->get_field_name( 'zone_id' ); ?>">
				<option value="0" <?php selected( $zone_id, 0 ); ?>>
					<?php _e( '-- Select a zone --', 'zoninator' ); ?>
				</option>
				
				<?php foreach( $zones as $zone ) : ?>
					<option value="<?php echo $zone->term_id; ?>" <?php selected( $zone_id, $zone->term_id ); ?>>
					<?php echo esc_html( $zone->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'show_description' ); ?>">
				<input id="<?php echo $this->get_field_id( 'show_description' ); ?>" name="<?php echo $this->get_field_name( 'show_description' ); ?>" <?php checked( true, $show_description ); ?> type="checkbox" value="1" />
				<?php _e( 'Show zone description in widget', 'zoninator' ); ?>
			</label>
		</p>
		<?php
	}
}
