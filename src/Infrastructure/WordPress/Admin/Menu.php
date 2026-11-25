<?php
/**
 * The Zones item in the admin menu.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;

/**
 * Adds Zones to the admin menu, for users who may curate zones.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( Menu::class ) to unhook it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Menu {

	/**
	 * The capability checks, for who sees the item.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Where the Zones screen is.
	 *
	 * @var ScreenLocation
	 */
	private $location;

	/**
	 * The Zones screen, which the item opens.
	 *
	 * @var Screen
	 */
	private $screen;

	/**
	 * Constructor.
	 *
	 * @param Capabilities   $capabilities The capability checks.
	 * @param ScreenLocation $location     Where the Zones screen is.
	 * @param Screen         $screen       The Zones screen.
	 */
	public function __construct( Capabilities $capabilities, ScreenLocation $location, Screen $screen ) {
		$this->capabilities = $capabilities;
		$this->location     = $location;
		$this->screen       = $screen;
	}

	/**
	 * Hooks the menu item. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register' ) );
	}

	/**
	 * Adds the menu item.
	 */
	public function register() {
		add_menu_page( __( 'Zones', 'zoninator' ), __( 'Zones', 'zoninator' ), $this->capabilities->get_manage_zones_cap(), ScreenLocation::SLUG, array( $this->screen, 'render' ), 'dashicons-layout', 11 );
	}
}
