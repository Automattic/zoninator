<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how the zone taxonomy is registered and which post types it covers.
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

	public function test_the_taxonomy_is_a_private_data_store(): void {
		$taxonomy = get_taxonomy( 'zoninator_zones' );

		$this->assertSame( 'Zones', $taxonomy->label );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertFalse( $taxonomy->query_var );
		$this->assertFalse( $taxonomy->rewrite );
		$this->assertFalse( $taxonomy->public );
		$this->assertSame( array( 'post' ), $taxonomy->object_type );
	}

	public function test_posts_support_zones_by_default(): void {
		$this->assertTrue( post_type_supports( 'post', 'zoninator_zones' ) );
		$this->assertSame( array( 'post' ), Zoninator()->get_supported_post_types() );
	}

	public function test_register_zone_post_type_adds_a_post_type(): void {
		Zoninator()->get_supported_post_types();

		$this->assertTrue( Zoninator()->register_zone_post_type( 'page' ) );

		$this->assertTrue( post_type_supports( 'page', 'zoninator_zones' ) );
		$this->assertContains( 'page', get_taxonomy( 'zoninator_zones' )->object_type );
		$this->assertSame( array( 'post', 'page' ), Zoninator()->get_supported_post_types() );
	}

	public function test_register_zone_post_type_skips_a_missing_post_type(): void {
		$this->assertFalse( Zoninator()->register_zone_post_type( 'no_such_type' ) );
		$this->assertTrue( Zoninator()->register_zone_post_type( array( 'no_such_type', 'page' ) ) );
	}

	public function test_pre_init_can_add_default_post_types(): void {
		$add_page = static function () {
			Zoninator()->default_post_types[] = 'page';
		};
		add_action( 'zoninator_pre_init', $add_page );

		Zoninator()->init();

		remove_action( 'zoninator_pre_init', $add_page );
		$this->assertTrue( post_type_supports( 'page', 'zoninator_zones' ) );
	}
}
