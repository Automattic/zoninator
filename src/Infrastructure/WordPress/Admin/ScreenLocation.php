<?php
/**
 * Where the Zones admin screen is.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * The Zones admin screen's slug, URL and nonce actions, and whether a request is for it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ScreenLocation {

	/**
	 * The screen's slug, as in admin.php?page=zoninator.
	 */
	public const SLUG = 'zoninator';

	/**
	 * Prefix of the nonce action for each thing the zone form does.
	 */
	public const NONCE_PREFIX = 'zone-nonce';

	/**
	 * Gets the nonce action for one of the zone form's actions.
	 *
	 * @param string $action 'insert', 'update' or 'delete'.
	 * @return string
	 */
	public function get_nonce_action( string $action ): string {
		return self::NONCE_PREFIX . '-' . $action;
	}

	/**
	 * Gets the URL of the Zones screen, with any query arguments added.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public function get_url( array $args = array() ) {
		$url = menu_page_url( self::SLUG, false );

		foreach ( $args as $arg_key => $arg_value ) {
			$url = add_query_arg( $arg_key, $arg_value, $url );
		}

		return $url;
	}

	/**
	 * Whether the current request is for the Zones screen.
	 *
	 * @return bool
	 */
	public function is_current() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( empty( $screen ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) === self::SLUG;
		}

		return ! empty( $screen->id ) && false !== strpos( $screen->id, self::SLUG );
	}
}
