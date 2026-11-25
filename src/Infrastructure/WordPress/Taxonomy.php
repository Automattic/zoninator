<?php
/**
 * The zone taxonomy.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

/**
 * Registers the zoninator_zones taxonomy, which stores the zones, and
 * tracks which post types can be put in a zone.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (get_supported_post_types() for get_post_types(), and
 * register_zone_post_type() for add_post_types()), which delegates here.
 *
 * @since 0.12.0
 * @internal Use Zoninator(), the template tags and the hooks instead.
 */
final class Taxonomy {

	/**
	 * The taxonomy's name. Zoninator::ZONE_TAXONOMY is its public name.
	 */
	public const NAME = 'zoninator_zones';

	/**
	 * Gives posts zone support, and hooks the taxonomy's registration. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks() {
		// This only writes to core's list of post type features, so it works before
		// core registers the post type on init. Sites opt posts out with
		// remove_post_type_support( 'post', 'zoninator_zones' ).
		add_post_type_support( 'post', self::NAME );

		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers the taxonomy.
	 *
	 * It has no object types: a post's place in a zone is post meta, never a
	 * term relationship, so the taxonomy doesn't need to know the post types.
	 */
	public function register() {
		if ( ! taxonomy_exists( self::NAME ) ) {
			register_taxonomy(
				self::NAME,
				array(),
				array(
					'label'        => __( 'Zones', 'zoninator' ),
					'hierarchical' => false,
					'query_var'    => false,
					'rewrite'      => false,
					'public'       => false,
					// Zoninator's own capability checks decide who changes zones, and
					// zone posts are post meta, not terms. These keep core's term
					// screens, admin-ajax actions, XML-RPC methods and tax_input out.
					'capabilities' => array(
						'manage_terms' => 'do_not_allow',
						'edit_terms'   => 'do_not_allow',
						'delete_terms' => 'do_not_allow',
						'assign_terms' => 'do_not_allow',
					),
				)
			);
		}
	}

	/**
	 * The registered post types that support zones, in the order they were registered.
	 *
	 * @return string[]
	 */
	public function get_post_types() {
		return array_values( array_intersect( get_post_types(), get_post_types_by_support( self::NAME ) ) );
	}

	/**
	 * Registers a post type to be available for zones
	 *
	 * @param string|array $post_types A post type string or array of post type strings to register.
	 * @return bool True if any post types were added, false if not.
	 */
	public function add_post_types( $post_types = '' ) {

		$did_register_post_types = false;

		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			add_post_type_support( $post_type, self::NAME );

			$did_register_post_types = true;
		}

		return $did_register_post_types;
	}
}
