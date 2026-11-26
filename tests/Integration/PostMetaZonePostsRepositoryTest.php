<?php
declare( strict_types = 1 );

/**
 * Integration tests that hold the post meta store of a zone's posts to the rules every store follows.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;
use Automattic\Zoninator\Infrastructure\WordPress\PostMetaZonePostsRepository;
use Automattic\Zoninator\Tests\ZonePostsRepositoryContract;

// The integration tests have no autoloader for the tests' own classes.
require_once dirname( __DIR__ ) . '/ZonePostsRepositoryContract.php';

final class PostMetaZonePostsRepositoryTest extends TestCase {

	use ZonePostsRepositoryContract;

	protected function repository(): ZonePostsRepositoryInterface {
		return zoninator_container()->get( PostMetaZonePostsRepository::class );
	}

	protected function make_post_ids( int $count ): array {
		return self::factory()->post->create_many( $count );
	}

	protected function make_zone_id(): int {
		return (int) self::insert_zone( 'contract-' . wp_rand() )['term_id'];
	}
}
