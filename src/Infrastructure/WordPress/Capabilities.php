<?php
/**
 * Who may do what with zones.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\ZoneAccessInterface;

/**
 * Says what a user may do to zones, and which posts they may put in one,
 * by asking WordPress. Sites change the capabilities with the
 * zoninator_add_zone_cap, zoninator_edit_zone_cap and zoninator_manage_zone_cap
 * filters.
 *
 * Every check takes the user's ID. An entry point that acts for whoever is
 * logged in passes get_current_user_id().
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Capabilities implements ZoneAccessInterface {

	/**
	 * Constructor.
	 *
	 * @param Taxonomy $taxonomy The zone taxonomy.
	 */
	public function __construct( private readonly Taxonomy $taxonomy ) {
	}

	/**
	 * Gets the capability a user needs to curate zones, which is also what the Zones menu item needs.
	 *
	 * @return string
	 */
	public function get_manage_zones_cap(): string {
		/**
		 * Filters the capability needed to open the Zones screen and curate zones:
		 * add, remove and reorder their posts, search for posts, and take a lock.
		 *
		 * @since 0.4
		 *
		 * @param string $capability Capability. Default 'edit_others_posts'.
		 */
		return (string) apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' );
	}

	/**
	 * Whether a user may create zones.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_create_zones( int $user_id ): bool {
		/**
		 * Filters the capability needed to create zones.
		 *
		 * @since 0.4
		 *
		 * @param string $capability Capability. Default 'edit_others_posts'.
		 */
		return user_can( $user_id, (string) apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' ) );
	}

	/**
	 * Whether a user may change a zone's name and details, or delete it.
	 *
	 * @param int $user_id User ID.
	 * @param int $zone_id Zone ID.
	 * @return bool
	 */
	public function can_edit_zone( int $user_id, int $zone_id ): bool {
		/**
		 * Filters the capability needed to change a zone's name and details, or delete it.
		 *
		 * @since 0.4
		 *
		 * @param string $capability Capability. Default 'edit_others_posts'.
		 */
		$capability = (string) apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' );

		/**
		 * Filters whether a user may change a zone's name and details, or delete it.
		 *
		 * @since 0.3
		 * @since 1.0.0 Added $user_id.
		 *
		 * @param bool $has_cap Whether the user has the zoninator_edit_zone_cap capability.
		 * @param int  $zone_id Zone ID.
		 * @param int  $user_id ID of the user being asked about, who may not be the current user.
		 */
		return (bool) apply_filters( 'zoninator_current_user_can_edit_zone', user_can( $user_id, $capability ), $zone_id, $user_id );
	}

	/**
	 * Whether a user may curate zones: add, order and remove their posts.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_curate_zones( int $user_id ): bool {
		return user_can( $user_id, $this->get_manage_zones_cap() );
	}

	/**
	 * Whether a user may put a post in a zone, or take it out: the post must
	 * be of a post type that supports zones, and one the user may read.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function can_use_post( int $user_id, int $post_id ): bool {
		// get_post() falls back to the global post when given an empty value.
		$post = $post_id ? get_post( $post_id ) : null;

		return $post
			&& in_array( $post->post_type, $this->taxonomy->get_post_types(), true )
			&& user_can( $user_id, 'read_post', $post->ID );
	}
}
