<?php
/**
 * The Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Request;

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
	 * Reads requests.
	 *
	 * @var Request
	 */
	private $request;

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
	 * @param Request          $request Reads requests.
	 * @param ScreenDataLoader $loader  Gathers what the screen shows.
	 * @param ScreenView       $view    Prints the screen.
	 */
	public function __construct( Request $request, ScreenDataLoader $loader, ScreenView $view ) {
		$this->request = $request;
		$this->loader  = $loader;
		$this->view    = $view;
	}

	/**
	 * Prints the screen for the current request.
	 */
	public function render() {
		$this->view->render(
			$this->loader->load( $this->request->get_request_var( 'zone_id', 0, 'absint' ) ),
			$this->request->get_get_var( 'message', '', 'sanitize_key' ),
			$this->request->get_get_var( 'error', '', 'sanitize_key' )
		);
	}
}
