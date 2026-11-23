<?php
/**
 * Who may do what with zones.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

/**
 * The capability checks for adding, editing and managing zones, and for
 * putting a post in one. Sites change the capabilities with the
 * zoninator_add_zone_cap, zoninator_edit_zone_cap and zoninator_manage_zone_cap
 * filters.
 *
 * @since 0.12.0
 */
final class Capabilities {

	/**
	 * The zone taxonomy, for the post types that support zones.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Constructor.
	 *
	 * @param Taxonomy $taxonomy The zone taxonomy.
	 */
	public function __construct( Taxonomy $taxonomy ) {
		$this->taxonomy = $taxonomy;
	}

	public function get_add_zones_cap() {
		return apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' );
	}

	public function get_edit_zones_cap() {
		return apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' );
	}

	public function get_manage_zones_cap() {
		return apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' );
	}

	public function current_user_can_add_zones() {
		return current_user_can( $this->get_add_zones_cap() );
	}

	public function current_user_can_edit_zones( $zone_id ) {
		$has_cap = current_user_can( $this->get_edit_zones_cap() );
		return apply_filters( 'zoninator_current_user_can_edit_zone', $has_cap, $zone_id );
	}

	public function current_user_can_manage_zones() {
		return current_user_can( $this->get_manage_zones_cap() );
	}

	/**
	 * Whether the current user may put a post in a zone.
	 *
	 * Request handlers take post IDs from the user, so they check here that
	 * each ID is a post of a zone post type that the user can read.
	 *
	 * @param int|\WP_Post $post Post ID or object.
	 * @return bool
	 */
	public function current_user_can_add_post_to_zone( $post ) {
		// get_post() falls back to the global post when given an empty value.
		$post = empty( $post ) ? null : get_post( $post );

		return $post
			&& in_array( $post->post_type, $this->taxonomy->get_post_types(), true )
			&& current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Whether the current user may take an action on zones.
	 *
	 * @param string   $action  'insert', 'update' or 'delete'. Anything else checks for managing zones.
	 * @param int|null $zone_id Zone ID, for 'update' and 'delete'.
	 * @return bool
	 */
	public function check( $action = '', $zone_id = null ) {
		// Zone locks aren't checked on writes yet. See https://github.com/Automattic/zoninator/issues/215.
		if ( 'insert' === $action ) {
			return $this->current_user_can_add_zones();
		}

		if ( 'update' === $action || 'delete' === $action ) {
			return $this->current_user_can_edit_zones( $zone_id );
		}

		return $this->current_user_can_manage_zones();
	}
}
