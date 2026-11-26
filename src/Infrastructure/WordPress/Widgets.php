<?php
/**
 * The Zone Posts widget's registration and cache.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Zoninator_ZonePosts_Widget;

/**
 * Registers the Zone Posts widget, and clears the widget's cached output
 * when something it shows changes.
 *
 * It registers its own hooks in register_hooks(), and sites reach it with
 * zoninator_container()->get( Widgets::class ) to unhook them.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Widgets {

	/**
	 * Key, in the widget cache group, that holds every Zone Posts widget's output.
	 */
	public const CACHE_KEY = 'widget-zone-posts';

	/**
	 * Hooks the widget's registration, and its cache to everything that
	 * changes what it shows. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks() {
		add_action( 'widgets_init', array( $this, 'register' ) );

		add_action( 'save_post', array( $this, 'flush_cache' ) );
		add_action( 'deleted_post', array( $this, 'flush_cache' ) );
		add_action( 'switch_theme', array( $this, 'flush_cache' ) );
		add_action( 'zoninator_add_zone_posts', array( $this, 'flush_cache' ) );
		add_action( 'zoninator_remove_zone_posts', array( $this, 'flush_cache' ) );
		add_action( 'edited_' . Taxonomy::NAME, array( $this, 'flush_cache' ) );
	}

	/**
	 * Registers the widget. Hooked on widgets_init.
	 */
	public function register() {
		register_widget( Zoninator_ZonePosts_Widget::class );
	}

	/**
	 * Clears the cached output of every Zone Posts widget.
	 */
	public function flush_cache() {
		$block_save_cache_seconds = absint( apply_filters( 'zoninator_zone_posts_widget_block_save_cache_seconds', 5 ) );
		if ( $block_save_cache_seconds > 0 ) {
			// This key will block updating the cache for n seconds so the following cache delete can propagate
			// phpcs:ignore -- WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- not changing behavior now.
			wp_cache_set( self::CACHE_KEY . '-save_blocked', 1, 'widget', $block_save_cache_seconds );
		}

		wp_cache_delete( self::CACHE_KEY, 'widget' );
	}
}
