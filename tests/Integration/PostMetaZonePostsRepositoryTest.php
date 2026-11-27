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

	/**
	 * How many zones make_zone_id() has made, to give each its own slug.
	 *
	 * @var int
	 */
	private $zones_made = 0;

	protected function repository(): ZonePostsRepositoryInterface {
		return zoninator_container()->get( PostMetaZonePostsRepository::class );
	}

	protected function make_post_ids( int $count ): array {
		return self::factory()->post->create_many( $count );
	}

	protected function make_zone_id(): int {
		return self::insert_zone( 'contract-' . ++$this->zones_made );
	}

	/**
	 * The Zones screen saves the whole order after every change, so a reorder
	 * that rewrote every post would cost two writes per post in the zone.
	 */
	public function test_replacing_writes_only_the_posts_whose_place_changes(): void {
		$repository = $this->repository();
		$zone_id    = $this->make_zone_id();
		$post_ids   = $this->make_post_ids( 4 );
		$repository->replace( $zone_id, $post_ids );

		$written = array();
		$record  = static function ( $meta_ids, $post_id ) use ( &$written ): void {
			$written[] = $post_id;
		};
		add_action( 'added_post_meta', $record, 10, 2 );
		add_action( 'updated_post_meta', $record, 10, 2 );
		add_action( 'deleted_post_meta', $record, 10, 2 );

		// Swap the first two, and drop the last.
		$repository->replace( $zone_id, array( $post_ids[1], $post_ids[0], $post_ids[2] ) );

		sort( $written );
		$this->assertSame( array( $post_ids[0], $post_ids[1], $post_ids[3] ), $written );
		$this->assertSame( array( $post_ids[1], $post_ids[0], $post_ids[2] ), $repository->get_post_ids( $zone_id ) );
	}
}
