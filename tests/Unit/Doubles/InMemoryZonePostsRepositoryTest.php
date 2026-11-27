<?php
declare( strict_types = 1 );

/**
 * Unit tests that hold the stand-in for a zone's posts to the real store's rules.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Doubles;

use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Tests\ZonePostsRepositoryContract;
use PHPUnit\Framework\TestCase;

final class InMemoryZonePostsRepositoryTest extends TestCase {

	use ZonePostsRepositoryContract;

	/**
	 * @var InMemoryZonePostsRepository
	 */
	private $repository;

	/**
	 * @var int
	 */
	private $last_id = 0;

	protected function setUp(): void {
		$this->repository = new InMemoryZonePostsRepository();
	}

	protected function repository(): ZonePostsRepositoryInterface {
		return $this->repository;
	}

	protected function make_post_ids( int $count ): array {
		return array_map( fn(): int => ++$this->last_id, array_fill( 0, $count, null ) );
	}

	protected function make_zone_id(): int {
		return ++$this->last_id;
	}
}
