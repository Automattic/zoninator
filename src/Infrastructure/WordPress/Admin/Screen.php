<?php
/**
 * The Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;

/**
 * The Zones admin screen: adds it to the admin menu, for users who may curate
 * zones, and shows it. To show it, it reads which zone and which notice the
 * request asks for, has ScreenDataLoader gather what to show, and hands that
 * to ScreenView to print.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( Screen::class ) to unhook it. The rest of the
 * screen is in the classes beside this one: Assets, SearchForm, ZoneForm and
 * the views.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Screen {

	/**
	 * Constructor.
	 *
	 * @param Capabilities     $capabilities The capability checks.
	 * @param ScreenDataLoader $loader  Gathers what the screen shows.
	 * @param ScreenView       $view    Prints the screen.
	 */
	public function __construct(
		private readonly Capabilities $capabilities,
		private readonly ScreenDataLoader $loader,
		private readonly ScreenView $view,
	) {
	}

	/**
	 * Hooks the menu item. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Adds Zones to the admin menu, with render() as its page.
	 */
	public function register(): void {
		add_menu_page( __( 'Zones', 'zoninator' ), __( 'Zones', 'zoninator' ), $this->capabilities->get_manage_zones_cap(), ScreenLocation::SLUG, array( $this, 'render' ), 'dashicons-layout', 11 );
	}

	/**
	 * Prints the screen for the current request.
	 */
	public function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Choosing which zone and notice to show changes nothing.
		$this->view->render(
			$this->loader->load( isset( $_GET['zone_id'] ) ? absint( $_GET['zone_id'] ) : 0 ),
			isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '',
			isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : ''
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
