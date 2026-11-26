<?php
/**
 * The Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Shows the Zones admin screen. It reads which zone and which notice the
 * request asks for, has ScreenDataLoader gather what to show, and hands that
 * to ScreenView to print.
 *
 * Menu makes render() the menu page's callback. The rest of the screen is in
 * the classes beside this one: Assets, SearchForm, ZoneForm and the views.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Screen {

	/**
	 * Gathers what the screen shows.
	 *
	 * @var ScreenDataLoader
	 */
	private $loader;

	/**
	 * Prints the screen.
	 *
	 * @var ScreenView
	 */
	private $view;

	/**
	 * Constructor.
	 *
	 * @param ScreenDataLoader $loader  Gathers what the screen shows.
	 * @param ScreenView       $view    Prints the screen.
	 */
	public function __construct( ScreenDataLoader $loader, ScreenView $view ) {
		$this->loader = $loader;
		$this->view   = $view;
	}

	/**
	 * Prints the screen for the current request.
	 */
	public function render() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Choosing which zone and notice to show changes nothing.
		$this->view->render(
			$this->loader->load( isset( $_GET['zone_id'] ) ? absint( $_GET['zone_id'] ) : 0 ),
			isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '',
			isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : ''
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
