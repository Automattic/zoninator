<?php
declare( strict_types = 1 );

/**
 * Unit tests for reading the post IDs a caller gives.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Domain;

use Automattic\Zoninator\Domain\PostIds;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PostIdsTest extends TestCase {

	public function test_integers_and_strings_of_digits_become_integers_in_order(): void {
		$this->assertSame( array( 7, 5, 0, 7 ), PostIds::from( array( 7, '5', 0, '7' ) ) );
	}

	/**
	 * PHP casts any object to 1, so a WP_Post passed by mistake would put post 1 in the zone.
	 *
	 * @dataProvider data_not_post_ids
	 *
	 * @param mixed $value Something that isn't a post ID.
	 */
	public function test_anything_else_is_refused( $value ): void {
		$this->expectException( InvalidArgumentException::class );

		PostIds::from( array( 5, $value ) );
	}

	/**
	 * @return Generator<string, array{mixed}>
	 */
	public static function data_not_post_ids(): Generator {
		yield 'an object' => array( new stdClass() );
		yield 'an array' => array( array( 'ID' => 5 ) );
		yield 'a float' => array( 5.0 );
		yield 'null' => array( null );
		yield 'true' => array( true );
		yield 'a negative string' => array( '-5' );
		yield 'text' => array( 'five' );
	}
}
