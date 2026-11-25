<?php
/*
Plugin Name: Zone Manager (Zoninator)
Description: Create zones of selected posts in a set order, and display them on your site.
Author: Mohammad Jangda, Automattic
Version: 0.12.0
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

define( 'ZONINATOR_VERSION', '0.12.0' );
define( 'ZONINATOR_FILE', __FILE__ );

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/src/class-zoninator-zoneposts-widget.php';
require_once __DIR__ . '/src/class-settings.php';
require_once __DIR__ . '/src/class-taxonomy.php';
require_once __DIR__ . '/src/class-capabilities.php';
require_once __DIR__ . '/src/class-request.php';
require_once __DIR__ . '/src/class-zones.php';
require_once __DIR__ . '/src/class-locks.php';
require_once __DIR__ . '/src/class-feed.php';
require_once __DIR__ . '/src/class-block.php';
require_once __DIR__ . '/src/admin/class-screen.php';
require_once __DIR__ . '/src/class-zoninator.php';
require_once __DIR__ . '/src/class-container.php';
require_once __DIR__ . '/src/class-bootstrapper.php';

/**
 * Boots Zoninator the first time it's called, and returns its container.
 *
 * Use the container's get() to reach the object that handles one of
 * Zoninator's hooks, to unhook it, for example on plugins_loaded at priority 20:
 * remove_action( 'template_redirect', array( zoninator_container()->get( \Automattic\Zoninator\Feed::class ), 'serve' ) ).
 *
 * @since 1.0.0
 *
 * @return \Automattic\Zoninator\Container
 */
function zoninator_container() {
	static $container = null;

	if ( null === $container ) {
		$container = new \Automattic\Zoninator\Container();

		( new \Automattic\Zoninator\Bootstrapper( $container ) )->boot();
	}

	return $container;
}

/**
 * Zoninator's public API. Called before plugins_loaded, it boots Zoninator early.
 *
 * @return Zoninator
 */
function Zoninator() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Windows is case-sensitive, so changing this is a breaking change.
	return zoninator_container()->get( Zoninator::class );
}

// Some themes load plugins from functions.php, after plugins_loaded has fired.
if ( did_action( 'plugins_loaded' ) ) {
	zoninator_container();
} else {
	add_action( 'plugins_loaded', 'zoninator_container' );
}
