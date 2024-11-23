<?php
/*
Plugin Name: Zone Manager (Zoninator)
Description: Curation made easy! Create "zones" then add and order your content!
Author: Mohammad Jangda, Automattic
Version: 0.10.1
Author URI: http://vip.wordpress.com
Text Domain: zoninator
Domain Path: /language/

Copyright 2010-2015 Mohammad Jangda, Automattic

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

if ( ! class_exists( 'Zoninator' ) ) :
	define( 'ZONINATOR_VERSION', '0.10.1' );
	define( 'ZONINATOR_PATH', __DIR__ );
	define( 'ZONINATOR_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

	require_once ZONINATOR_PATH . '/functions.php';
	require_once ZONINATOR_PATH . '/src/class-zoninator-zoneposts-widget.php';
	require_once ZONINATOR_PATH . '/src/class-zoninator.php';

	function Zoninator() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid, Universal.Files.SeparateFunctionsFromOO.Mixed -- Windows is case-sensitive, so changing this is a breaking change.
		global $zoninator;
		if ( ! isset( $zoninator ) || null === $zoninator ) {
			$zoninator = new Zoninator();
		}

		return $zoninator;
	}

	Zoninator();
endif;
