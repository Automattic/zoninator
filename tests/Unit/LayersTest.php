<?php
declare( strict_types = 1 );

/**
 * Unit tests that keep each layer from depending on one above it.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LayersTest extends TestCase {

	/**
	 * Domain knows no other layer. Application knows Domain. Neither knows
	 * Infrastructure, which is the only layer that may call WordPress.
	 *
	 * @dataProvider data_layers
	 *
	 * @param string   $layer     Layer to check.
	 * @param string[] $forbidden Layers it must not name.
	 */
	public function test_layer_does_not_depend_on_one_above_it( string $layer, array $forbidden ): void {
		$directory = dirname( __DIR__, 2 ) . '/src/' . $layer;
		$files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		$checked   = 0;

		foreach ( $files as $file ) {
			foreach ( $forbidden as $other ) {
				$this->assertStringNotContainsString(
					'Automattic\\Zoninator\\' . $other . '\\',
					(string) file_get_contents( $file->getPathname() ),
					$file->getFilename() . " in $layer names the $other layer."
				);
			}
			++$checked;
		}

		$this->assertGreaterThan( 0, $checked );
	}

	/**
	 * @return array<string, array{string, string[]}>
	 */
	public function data_layers(): array {
		return array(
			'Domain'      => array( 'Domain', array( 'Application', 'Infrastructure' ) ),
			'Application' => array( 'Application', array( 'Infrastructure' ) ),
		);
	}
}
