<?php
/**
 * The zone taxonomy.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

/**
 * Registers the zoninator_zones taxonomy, which stores the zones, and
 * tracks which post types can be put in a zone.
 *
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Taxonomy {

	/**
	 * The taxonomy's name. Stored zones depend on it, so it can't change.
	 */
	public const NAME = 'zoninator_zones';

	/**
	 * Gives posts zone support, and hooks the taxonomy's registration. PluginBootstrapper::register_hooks() calls this once.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
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
	public function register(): void {
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
	public function get_post_types(): array {
		return array_values( array_intersect( get_post_types(), get_post_types_by_support( self::NAME ) ) );
	}
}
