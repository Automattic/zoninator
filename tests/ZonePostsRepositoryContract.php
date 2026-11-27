<?php
declare( strict_types = 1 );

/**
 * What every store of a zone's posts must do.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests;

use Automattic\Zoninator\Domain\ZonePostsRepositoryInterface;

/**
 * The tests every ZonePostsRepositoryInterface must pass: the stand-in the
 * unit tests use, and the real one. They keep the two from drifting apart,
 * so a unit test that passes says something about the plugin.
 */
trait ZonePostsRepositoryContract {

	/**
	 * @return ZonePostsRepositoryInterface The store to test.
	 */
	abstract protected function repository(): ZonePostsRepositoryInterface;

	/**
	 * @param int $count How many.
	 * @return int[] IDs of posts the store can hold.
	 */
	abstract protected function make_post_ids( int $count ): array;

	/**
	 * @return int ID of a zone the store can hold posts for.
	 */
	abstract protected function make_zone_id(): int;

	public function test_a_zone_starts_with_no_posts(): void {
		$this->assertSame( array(), $this->repository()->get_post_ids( $this->make_zone_id() ) );
	}

	public function test_append_adds_posts_after_the_ones_there_in_the_order_given(): void {
		list( $a, $b, $c, $d ) = $this->make_post_ids( 4 );
		$zone_id               = $this->make_zone_id();

		$this->repository()->append( $zone_id, array( $b, $a ) );
		$this->repository()->append( $zone_id, array( $d, $c ) );

		$this->assertSame( array( $b, $a, $d, $c ), $this->repository()->get_post_ids( $zone_id ) );
	}

	public function test_replace_leaves_only_the_posts_given_in_the_order_given(): void {
		list( $a, $b, $c ) = $this->make_post_ids( 3 );
		$zone_id           = $this->make_zone_id();
		$this->repository()->append( $zone_id, array( $a, $b ) );

		$this->repository()->replace( $zone_id, array( $c, $a ) );

		$this->assertSame( array( $c, $a ), $this->repository()->get_post_ids( $zone_id ) );
	}

	public function test_remove_takes_out_those_posts_and_keeps_the_order_of_the_rest(): void {
		list( $a, $b, $c ) = $this->make_post_ids( 3 );
		$zone_id           = $this->make_zone_id();
		$this->repository()->append( $zone_id, array( $a, $b, $c ) );

		$this->repository()->remove( $zone_id, array( $b ) );

		$this->assertSame( array( $a, $c ), $this->repository()->get_post_ids( $zone_id ) );
	}

	public function test_append_after_a_removal_still_goes_to_the_end(): void {
		list( $a, $b, $c ) = $this->make_post_ids( 3 );
		$zone_id           = $this->make_zone_id();
		$this->repository()->append( $zone_id, array( $a, $b ) );
		$this->repository()->remove( $zone_id, array( $a ) );

		$this->repository()->append( $zone_id, array( $c, $a ) );

		$this->assertSame( array( $b, $c, $a ), $this->repository()->get_post_ids( $zone_id ) );
	}

	public function test_clear_empties_the_zone(): void {
		$zone_id = $this->make_zone_id();
		$this->repository()->append( $zone_id, $this->make_post_ids( 2 ) );

		$this->repository()->clear( $zone_id );

		$this->assertSame( array(), $this->repository()->get_post_ids( $zone_id ) );
	}

	public function test_a_post_has_its_own_place_in_each_zone(): void {
		list( $a, $b ) = $this->make_post_ids( 2 );
		$zone_id       = $this->make_zone_id();
		$other_id      = $this->make_zone_id();
		$this->repository()->append( $zone_id, array( $a, $b ) );
		$this->repository()->append( $other_id, array( $b, $a ) );

		$this->repository()->remove( $zone_id, array( $a ) );

		$this->assertSame( array( $b ), $this->repository()->get_post_ids( $zone_id ) );
		$this->assertSame( array( $b, $a ), $this->repository()->get_post_ids( $other_id ) );
	}
}
