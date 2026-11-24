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
		$this->clear_post_types_cache();

		parent::tear_down();
	}

	/**
	 * Clear the cached list of zone post types, so later tests see only posts again.
	 */
	private function clear_post_types_cache(): void {
		$taxonomy = \Closure::bind( fn() => $this->taxonomy, Zoninator(), \Zoninator::class )();
		\Closure::bind( fn() => $this->post_types = null, $taxonomy, \Automattic\Zoninator\Taxonomy::class )();
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

	/**
	 * The plugin's own instance works out its post types before any test runs,
	 * so only a fresh one shows whether the cache starts out declared.
	 */
	public function test_a_new_taxonomy_works_out_its_post_types(): void {
		$taxonomy = new \Automattic\Zoninator\Taxonomy();

		$this->assertContains( 'post', $taxonomy->get_post_types() );
	}
}
