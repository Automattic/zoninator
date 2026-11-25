<?php
declare( strict_types = 1 );

/**
 * Unit tests for curating the posts in a zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Unit\Application;

use Automattic\Zoninator\Application\ZoneCurator;
use Automattic\Zoninator\Domain\Zone;
use Automattic\Zoninator\Domain\ZoneFullException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Tests\Unit\Doubles\FixedSettings;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZonePostsRepository;
use Automattic\Zoninator\Tests\Unit\Doubles\InMemoryZoneRepository;
use PHPUnit\Framework\TestCase;

final class ZoneCuratorTest extends TestCase {

	/**
	 * @var InMemoryZonePostsRepository
	 */
	private $zone_posts;

	/**
	 * @var FixedSettings
	 */
	private $settings;

	/**
	 * @var ZoneCurator
	 */
	private $curator;

	/**
	 * @var int
	 */
	private $zone_id;

	protected function setUp(): void {
		$zones            = new InMemoryZoneRepository();
		$this->zone_posts = new InMemoryZonePostsRepository();
		$this->settings   = new FixedSettings();
		$this->curator    = new ZoneCurator( $zones, $this->zone_posts, $this->settings );
		$this->zone_id    = (int) $zones->insert( new Zone( null, 'homepage', 'Home page', array() ) )->get_id();
	}

	public function test_add_posts_puts_them_at_the_end(): void {
		$this->curator->set_posts( $this->zone_id, array( 5, 6 ) );

		$this->curator->add_posts( $this->zone_id, array( 7 ) );

		$this->assertSame( array( 5, 6, 7 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	public function test_set_posts_replaces_the_posts_in_the_order_given(): void {
		$this->curator->set_posts( $this->zone_id, array( 5, 6 ) );

		$this->curator->set_posts( $this->zone_id, array( 7, 5 ) );

		$this->assertSame( array( 7, 5 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	public function test_remove_posts_takes_out_only_those_posts(): void {
		$this->curator->set_posts( $this->zone_id, array( 5, 6, 7 ) );

		$this->curator->remove_posts( $this->zone_id, array( 6 ) );

		$this->assertSame( array( 5, 7 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	public function test_clear_posts_empties_the_zone(): void {
		$this->curator->set_posts( $this->zone_id, array( 5, 6 ) );

		$this->curator->clear_posts( $this->zone_id );

		$this->assertArrayNotHasKey( $this->zone_id, $this->zone_posts->post_ids );
	}

	/**
	 * A zone's posts follow it when its ID changes, and the old ID needn't be a zone any more.
	 */
	public function test_move_posts_takes_every_post_to_the_other_zone_in_order(): void {
		$this->zone_posts->replace( 99, array( 7, 5 ) );
		$this->curator->set_posts( $this->zone_id, array( 6 ) );

		$this->curator->move_posts( 99, $this->zone_id );

		$this->assertSame( array( 7, 5 ), $this->zone_posts->post_ids[ $this->zone_id ] );
		$this->assertArrayNotHasKey( 99, $this->zone_posts->post_ids );
	}

	public function test_move_posts_leaves_the_other_zone_alone_when_there_is_nothing_to_move(): void {
		$this->curator->set_posts( $this->zone_id, array( 6 ) );

		$this->curator->move_posts( 99, $this->zone_id );

		$this->assertSame( array( 6 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	public function test_a_zone_takes_posts_up_to_its_limit_and_no_more(): void {
		$this->settings->post_limit = 2;
		$this->curator->add_posts( $this->zone_id, array( 5, 6 ) );

		try {
			$this->curator->add_posts( $this->zone_id, array( 7 ) );
			$this->fail( 'Expected the full zone to refuse another post.' );
		} catch ( ZoneFullException $exception ) {
			$this->assertSame( 2, $exception->get_limit() );
		}

		$this->assertSame( array( 5, 6 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	public function test_adding_a_post_already_in_a_full_zone_is_not_one_too_many(): void {
		$this->settings->post_limit = 2;
		$this->curator->set_posts( $this->zone_id, array( 5, 6 ) );

		$this->curator->add_posts( $this->zone_id, array( 6 ) );

		$this->addToAssertionCount( 1 );
	}

	public function test_a_zones_posts_can_not_be_set_past_its_limit(): void {
		$this->settings->post_limit = 2;
		$this->curator->set_posts( $this->zone_id, array( 5 ) );

		try {
			$this->curator->set_posts( $this->zone_id, array( 5, 6, 7 ) );
			$this->fail( 'Expected the zone to refuse more posts than it may hold.' );
		} catch ( ZoneFullException $exception ) {
			$this->assertSame( array( 5 ), $this->zone_posts->post_ids[ $this->zone_id ] );
		}
	}

	/**
	 * A zone's posts follow it to its new ID even if a limit was set since they were added.
	 */
	public function test_moving_posts_ignores_the_limit(): void {
		$this->zone_posts->replace( 99, array( 5, 6, 7 ) );
		$this->settings->post_limit = 2;

		$this->curator->move_posts( 99, $this->zone_id );

		$this->assertSame( array( 5, 6, 7 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	/**
	 * A post is in a zone once, and only real post IDs count.
	 */
	public function test_repeated_and_invalid_ids_are_left_out(): void {
		$this->curator->set_posts( $this->zone_id, array( 5, '6', 5, 0, -1, 'seven', 8 ) );

		$this->assertSame( array( 5, 6, 8 ), $this->zone_posts->post_ids[ $this->zone_id ] );
	}

	/**
	 * @dataProvider data_changes
	 *
	 * @param string $method Method that changes a zone's posts.
	 * @param array  $args   Its arguments after the zone ID.
	 */
	public function test_a_zone_that_does_not_exist_is_refused( string $method, array $args ): void {
		try {
			$this->curator->$method( 9, ...$args );
			$this->fail( 'Expected the missing zone to be refused.' );
		} catch ( ZoneNotFoundException $exception ) {
			$this->assertArrayNotHasKey( 9, $this->zone_posts->post_ids );
		}
	}

	/**
	 * @return array<string, array{string, array}>
	 */
	public function data_changes(): array {
		return array(
			'add'    => array( 'add_posts', array( array( 5 ) ) ),
			'set'    => array( 'set_posts', array( array( 5 ) ) ),
			'remove' => array( 'remove_posts', array( array( 5 ) ) ),
			'clear'  => array( 'clear_posts', array() ),
		);
	}
}
