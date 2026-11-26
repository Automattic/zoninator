<?php
/**
 * What the zoninator/v1 REST controllers share.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Rest;

/**
 * What every zoninator/v1 controller needs to register its routes: the
 * namespace, the hook, and the zone ID arg most routes share.
 *
 * @since 1.0.0
 */
trait RegistersRoutes {

	/**
	 * The namespace every route is registered under.
	 *
	 * @var string
	 */
	private static $rest_namespace = 'zoninator/v1';

	/**
	 * Hooks the controller's routes. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * The zone ID arg. With a type and no callbacks, core checks it's an integer and makes it one.
	 *
	 * @return array
	 */
	private function get_zone_id_param(): array {
		return array(
			'zone_id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);
	}
}
