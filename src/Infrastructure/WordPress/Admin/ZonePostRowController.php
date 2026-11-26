<?php
/**
 * The route the Zones admin screen gets a zone post's row from.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\Permissions;
use Automattic\Zoninator\Infrastructure\WordPress\Rest\RegistersRoutes;
use Automattic\Zoninator\Infrastructure\WordPress\Taxonomy;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Returns a zone post's row as HTML, for the Zones screen to show after it
 * adds a post: /zones/{zone_id}/posts/{post_id}/row.
 *
 * It belongs to the screen, not to the REST API's data routes, which return
 * data only. The row is HTML because sites change its columns in PHP, with
 * the zoninator_zone_post_columns filter.
 *
 * @since 1.0.0
 * @internal The Zones screen's own route. Its response may change with the screen.
 */
final class ZonePostRowController extends WP_REST_Controller {

	use RegistersRoutes;

	/**
	 * Constructor.
	 *
	 * @param ZonePostRow  $row          Renders a zone post's row.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Permissions  $permissions  Says who may call the route.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly ZonePostRow $row,
		private readonly Capabilities $capabilities,
		private readonly Permissions $permissions,
		private readonly Errors $errors,
	) {
	}

	/**
	 * Register the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::$rest_namespace,
			'/zones/(?P<zone_id>[\d]+)/posts/(?P<post_id>\d+)/row',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_row' ),
					'permission_callback' => array( $this->permissions, 'manage_zones' ),
					'args'                => array_merge(
						$this->get_zone_id_param(),
						array(
							'post_id' => array(
								'type'              => 'integer',
								'sanitize_callback' => 'absint',
								'required'          => true,
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Get the row for a post in a zone.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response The row, as HTML.
	 */
	public function get_row( $request ): WP_REST_Response|WP_Error {
		$zone_id = (int) $request['zone_id'];
		$post_id = (int) $request['post_id'];

		if ( ! term_exists( $zone_id, Taxonomy::NAME ) ) {
			return $this->errors->zone_not_found();
		}

		if ( ! $this->capabilities->can_use_post( get_current_user_id(), $post_id ) ) {
			return $this->errors->invalid_post_id();
		}

		return rest_ensure_response( array( 'html' => $this->row->get_html( $post_id, $zone_id ) ) );
	}
}
