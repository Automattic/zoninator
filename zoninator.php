<?php
/*
Plugin Name: Zone Manager (Zoninator)
Description: Create zones of selected posts in a set order, and display them on your site.
Author: Mohammad Jangda, Automattic
Version: 1.0.0
Author URI: https://wpvip.com
Requires at least: 6.9
Requires PHP: 8.2
Text Domain: zoninator
Domain Path: /languages/

Copyright 2010-2025 Mohammad Jangda, Automattic

This plugin was built by Mohammad Jangda in conjunction with William Davis and the Bangor Daily News.

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

use Automattic\Zoninator\Infrastructure\DI\Container;
use Automattic\Zoninator\Infrastructure\WordPress\PluginBootstrapper;

define( 'ZONINATOR_VERSION', '1.0.0' );
define( 'ZONINATOR_FILE', __FILE__ );

require_once __DIR__ . '/functions.php';

// Loads the classes under Automattic\Zoninator\ from src/, by PSR-4, when they're first used.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Automattic\\Zoninator\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Boots Zoninator the first time it's called, and returns its container.
 *
 * Use the container's get() to reach the object that handles one of
 * Zoninator's hooks, to unhook it, for example on plugins_loaded at priority 20:
 * remove_action( 'template_redirect', array( zoninator_container()->get( \Automattic\Zoninator\Infrastructure\WordPress\FeedEndpoint::class ), 'serve' ) ).
 *
 * @since 1.0.0
 *
 * @return Container
 */
function zoninator_container() {
	static $container = null;

	if ( null === $container ) {
		$container = new Container();

		( new PluginBootstrapper( $container ) )->boot();
	}

	return $container;
}

// Some themes load plugins from functions.php, after plugins_loaded has fired.
if ( did_action( 'plugins_loaded' ) ) {
	zoninator_container();
} else {
	add_action( 'plugins_loaded', 'zoninator_container' );
}
