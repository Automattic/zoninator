<?php
/**
 * The zone taxonomy.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

/**
 * Registers the zoninator_zones taxonomy, which stores the zones, and
 * tracks which post types can be put in a zone.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (get_supported_post_types() for get_post_types(), and
 * register_zone_post_type() for add_post_types()), which delegates here.
 *
 * @since 0.12.0
 */
final class Taxonomy {

	/**
	 * The taxonomy's name. Zoninator::ZONE_TAXONOMY is its public name.
	 */
	public const NAME = 'zoninator_zones';

	/**
	 * Cache of the post types that support zones, or null to work it out again.
	 *
	 * @var string[]|null
	 */
	private $post_types;

	/**
	 * Adds zone support to the default post types, and registers the taxonomy.
	 *
	 * @param string[] $default_post_types Post types that support zones by default.
	 */
	public function register( array $default_post_types ) {
		// Default post type support
		foreach ( $default_post_types as $post_type ) {
			add_post_type_support( $post_type, self::NAME );
		}

		// Register taxonomy
		if ( ! taxonomy_exists( self::NAME ) ) {
			register_taxonomy(
				self::NAME,
				$this->get_post_types(),
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

	public function get_post_types() {
		if ( null !== $this->post_types ) {
			return $this->post_types;
		}

		$this->post_types = array();

		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, self::NAME ) ) {
				$this->post_types[] = $post_type;
			}
		}

		return $this->post_types;
	}

	/**
	 * Replaces the list of post types that support zones.
	 *
	 * Only for writes to the old Zoninator::$post_types property, which still
	 * take effect until 1.0.0. Use add_post_types() instead.
	 *
	 * @param string[]|null $post_types Post types, or null to work them out again.
	 */
	public function set_post_types( $post_types ) {
		$this->post_types = $post_types;
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
			register_taxonomy_for_object_type( self::NAME, $post_type );

			// Clear Zoninator supported post types cache
			$this->post_types = null;

			$did_register_post_types = true;
		}

		return $did_register_post_types;
	}
}
