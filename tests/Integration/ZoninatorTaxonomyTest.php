<?php
declare( strict_types = 1 );

/**
 * Integration tests that pin how the zone taxonomy is registered and which post types it covers.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Tests\Integration;

use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;

class ZoninatorTaxonomyTest extends TestCase {

	public function tearDown(): void {
		remove_post_type_support( 'page', 'zoninator_zones' );
		add_post_type_support( 'post', 'zoninator_zones' );

		parent::tearDown();
	}

	public function test_the_taxonomy_is_a_private_data_store(): void {
		$taxonomy = get_taxonomy( 'zoninator_zones' );

		$this->assertSame( 'Zones', $taxonomy->label );
		$this->assertFalse( $taxonomy->hierarchical );
		$this->assertFalse( $taxonomy->query_var );
		$this->assertFalse( $taxonomy->rewrite );
		$this->assertFalse( $taxonomy->public );
		// Zone posts are post meta, not terms, so the taxonomy needs no post types.
		$this->assertSame( array(), $taxonomy->object_type );
	}

	/**
	 * Zoninator's own capabilities decide who changes zones, so core's term
	 * screens, admin-ajax actions and XML-RPC methods can't, even for an
	 * administrator.
	 *
	 * @dataProvider data_term_caps
	 *
	 * @param string $cap Taxonomy capability.
	 */
	public function test_no_one_has_the_taxonomy_capabilities( string $cap ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}

		$this->assertFalse( current_user_can( get_taxonomy( 'zoninator_zones' )->cap->$cap ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_term_caps(): array {
		return array(
			'manage_terms' => array( 'manage_terms' ),
			'edit_terms'   => array( 'edit_terms' ),
			'delete_terms' => array( 'delete_terms' ),
			'assign_terms' => array( 'assign_terms' ),
		);
	}

	public function test_a_post_saved_with_tax_input_does_not_add_a_zone(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		wp_set_current_user( $user_id );

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'Draft',
				'post_author' => $user_id,
				'tax_input'   => array( 'zoninator_zones' => 'contributor-zone' ),
			)
		);

		$this->assertFalse( get_term_by( 'slug', 'contributor-zone', 'zoninator_zones' ) );
		$this->assertSame( array(), wp_get_object_terms( $post_id, 'zoninator_zones' ) );
	}

	public function test_posts_support_zones_by_default(): void {
		$this->assertTrue( post_type_supports( 'post', 'zoninator_zones' ) );
		$this->assertSame( array( 'post' ), self::taxonomy()->get_post_types() );
	}

	public function test_a_site_can_give_zones_to_another_post_type(): void {
		add_post_type_support( 'page', 'zoninator_zones' );

		$this->assertSame( array( 'post', 'page' ), self::taxonomy()->get_post_types() );
	}

	public function test_a_site_can_take_zones_away_from_posts(): void {
		remove_post_type_support( 'post', 'zoninator_zones' );

		$this->assertSame( array(), self::taxonomy()->get_post_types() );
	}

	/**
	 * Post types registered after the zone taxonomy count too, so it can be
	 * registered at the default priority.
	 */
	public function test_a_post_type_registered_later_with_zone_support_counts(): void {
		self::taxonomy()->get_post_types();
		register_post_type( 'zoninator_late', array( 'supports' => array( 'title', 'zoninator_zones' ) ) );

		$post_types = self::taxonomy()->get_post_types();

		unregister_post_type( 'zoninator_late' );
		$this->assertSame( array( 'post', 'zoninator_late' ), $post_types );
	}

	public function test_a_new_taxonomy_works_out_its_post_types(): void {
		$taxonomy = new Taxonomy();

		$this->assertContains( 'post', $taxonomy->get_post_types() );
	}
}
