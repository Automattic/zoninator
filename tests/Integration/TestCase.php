<?php
/**
 * Base test case for integration tests.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Admin\Screen;
use ReflectionProperty;
use Yoast\WPTestUtils\WPIntegration\TestCase as WPIntegrationTestCase;

/**
 * Base test case for integration tests.
 */
abstract class TestCase extends WPIntegrationTestCase {

	/**
	 * The plugin's Zones screen, for tests that render part of it.
	 *
	 * @return Screen
	 */
	protected static function admin_screen(): Screen {
		return ( new ReflectionProperty( Zoninator(), 'admin_screen' ) )->getValue( Zoninator() );
	}
}
