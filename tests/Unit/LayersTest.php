<?php
declare( strict_types = 1 );

/**
 * Unit tests that keep each layer from depending on one above it.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit;

use FilesystemIterator;
use Generator;
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
	 * Neither layer may call WordPress. A use statement would show a class,
	 * but a function such as get_post() needs none, so this reads every
	 * function call and checks it's one of PHP's own.
	 *
	 * @dataProvider data_layers
	 *
	 * @param string $layer Layer to check.
	 */
	public function test_layer_calls_only_php_s_own_functions( string $layer ): void {
		$internal  = array_flip( get_defined_functions()['internal'] );
		$directory = dirname( __DIR__, 2 ) . '/src/' . $layer;
		$files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
		$calls     = 0;

		foreach ( $files as $file ) {
			$tokens = array_values(
				array_filter(
					token_get_all( (string) file_get_contents( $file->getPathname() ) ),
					static fn( $token ): bool => ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true )
				)
			);

			foreach ( $tokens as $index => $token ) {
				// A function call is a bare name followed by "(", that isn't a method, a static method, a declaration or a class being built.
				if ( ! is_array( $token ) || ! in_array( $token[0], array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) || '(' !== ( $tokens[ $index + 1 ] ?? null ) ) {
					continue;
				}

				$before = $tokens[ $index - 1 ];
				if ( is_array( $before ) && in_array( $before[0], array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) ) {
					continue;
				}

				$this->assertArrayHasKey(
					strtolower( ltrim( $token[1], '\\' ) ),
					$internal,
					sprintf( '%s in %s calls %s() on line %d, which is not a PHP function.', $file->getFilename(), $layer, $token[1], $token[2] )
				);
				++$calls;
			}
		}

		$this->assertGreaterThan( 0, $calls );
	}

	/**
	 * @return Generator<string, array{string, string[]}>
	 */
	public static function data_layers(): Generator {
		yield 'Domain' => array( 'Domain', array( 'Application', 'Infrastructure' ) );
		yield 'Application' => array( 'Application', array( 'Infrastructure' ) );
	}
}
