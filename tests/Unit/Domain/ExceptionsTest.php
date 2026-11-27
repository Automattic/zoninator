<?php
declare( strict_types = 1 );

/**
 * Unit tests for what Zoninator's exceptions have in common.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Domain;

use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase {

	/**
	 * Entry points catch the interface, so an exception without it would go uncaught.
	 */
	public function test_every_domain_exception_implements_the_interface(): void {
		$files = glob( dirname( __DIR__, 3 ) . '/src/Domain/*Exception.php' );

		$this->assertNotEmpty( $files );

		foreach ( $files as $file ) {
			$class_name = 'Automattic\\Zoninator\\Domain\\' . basename( $file, '.php' );

			$this->assertTrue( is_subclass_of( $class_name, ZoninatorExceptionInterface::class ), "$class_name does not implement the interface." );
		}
	}
}
