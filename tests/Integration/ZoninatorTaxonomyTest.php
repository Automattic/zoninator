<?php
declare( strict_types = 1 );

/**
 * Integration tests for adding zone support to post types.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

class ZoninatorTaxonomyTest extends TestCase {

	public function tear_down(): void {
		remove_post_type_support( 'page', 'zoninator_zones' );
		unregister_taxonomy_for_object_type( 'zoninator_zones', 'page' );
		Zoninator()->default_post_types = array( 'post' );
		Zoninator()->post_types         = null;

		parent::tear_down();
	}

	public function test_register_zone_post_type_adds_a_post_type(): void {
		Zoninator()->get_supported_post_types();

		$this->assertTrue( Zoninator()->register_zone_post_type( 'page' ) );

		$this->assertTrue( post_type_supports( 'page', 'zoninator_zones' ) );
		$this->assertContains( 'page', get_taxonomy( 'zoninator_zones' )->object_type );
		$this->assertSame( array( 'post', 'page' ), Zoninator()->get_supported_post_types() );
	}
}
