<?php
/**
 * The Zone Posts widget's registration.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

/**
 * Registers the Zone Posts widget.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( Widgets::class ) to unhook it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Widgets {

	/**
	 * Hooks the widget's registration. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'widgets_init', array( $this, 'register' ) );
	}

	/**
	 * Registers the widget. Hooked on widgets_init.
	 */
	public function register(): void {
		register_widget( ZonePostsWidget::class );
	}
}
