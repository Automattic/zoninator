<?php
/**
 * The zone form on the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Domain\AccessDeniedException;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use Automattic\Zoninator\Infrastructure\WordPress\Errors;

/**
 * Saves the Zones screen's zone form: creating a zone, changing its name,
 * slug and description, and deleting it.
 *
 * It registers its own hook in register_hooks(), and sites reach it with
 * zoninator_container()->get( ZoneForm::class ) to unhook it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZoneForm {

	/**
	 * Constructor.
	 *
	 * @param ScreenLocation     $location Where the Zones screen is.
	 * @param GuardedZoneManager $manager Creates, changes and deletes zones for the current user.
	 * @param Errors $errors Builds the error for each thing that can go wrong.
	 */
	public function __construct(
		private readonly ScreenLocation $location,
		private readonly GuardedZoneManager $manager,
		private readonly Errors $errors,
	) {
	}

	/**
	 * Hooks the form's handler. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'handle_request' ) );
	}

	/**
	 * Saves the form if this request submitted it, then redirects.
	 */
	public function handle_request(): void {
		if ( ! $this->location->is_current() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only says which nonce to check, just below.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';

		if ( ! in_array( $action, array( 'insert', 'update', 'delete' ), true ) ) {
			return;
		}

		// The delete link sends the zone in the URL; the form posts it. A new zone has no ID yet.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- Only says which nonce to check, just below.
		$source  = 'delete' === $action ? $_REQUEST : $_POST;
		$zone_id = 'insert' !== $action && isset( $source['zone_id'] ) ? absint( $source['zone_id'] ) : 0;

		// The nonce is for this zone, so one made for another zone doesn't pass.
		check_admin_referer( $this->location->get_nonce_action( $action, $zone_id ), ScreenLocation::NONCE_NAME );

		try {
			$url = $this->location->get_url( $this->save( $action, $zone_id ) );
		} catch ( AccessDeniedException $exception ) {
			wp_die( $this->errors->permission_denied() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A WP_Error; wp_die() prints its message.
		} catch ( ZoneLockedException $exception ) {
			// The zone's own page says who is editing it.
			$url = $this->location->get_url(
				array(
					'action'  => 'edit',
					'zone_id' => $zone_id,
				)
			);
		} catch ( ZoninatorExceptionInterface $exception ) {
			if ( 'delete' === $action ) {
				$url = $this->location->get_url( array( 'error' => $exception instanceof ZoneNotFoundException ? 'invalid-zone' : 'delete-zone' ) );
			} elseif ( 'update' === $action ) {
				// Back to the zone that couldn't be changed.
				$url = $this->location->get_url(
					array(
						'action'  => 'edit',
						'zone_id' => $zone_id,
						'error'   => 'general',
					)
				);
			} else {
				$url = $this->location->get_url( array( 'error' => 'general' ) );
			}
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Does what the form asked, for the current user. GuardedZoneManager checks that they may.
	 *
	 * @param string $action  'insert', 'update' or 'delete'.
	 * @param int    $zone_id The zone to change or delete.
	 * @return array Query args for the page to show next.
	 */
	private function save( $action, $zone_id ): array {
		$user_id = get_current_user_id();

		if ( 'delete' === $action ) {
			$this->manager->remove( $user_id, $zone_id );

			return array( 'message' => 'delete-success' );
		}

		$name    = $this->get_text_field( 'name' );
		$slug    = sanitize_title( '' === $this->get_text_field( 'slug' ) ? $name : $this->get_text_field( 'slug' ) );
		$details = array( 'description' => $this->get_text_field( 'description' ) );

		// Go by the action that was checked, not by whether a zone ID was sent.
		if ( 'update' === $action ) {
			$this->manager->update(
				$user_id,
				$zone_id,
				array(
					'name'    => $name,
					'slug'    => $slug,
					'details' => $details,
				)
			);
		} else {
			$zone_id = (int) $this->manager->create( $user_id, $slug, $name, $details )->get_id();
		}

		return array(
			'action'  => 'edit',
			'zone_id' => $zone_id,
			'message' => $action . '-success',
		);
	}

	/**
	 * Reads one of the form's text fields.
	 *
	 * @param string $key The field's name.
	 * @return string The text, or '' if the field is empty, missing, or isn't text, such as an array.
	 */
	private function get_text_field( string $key ): string {
		// handle_request() has checked the nonce. The storage encodes the name and description for
		// itself, so they only need the slashes WordPress adds to a request taken off.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return empty( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ? '' : wp_unslash( $_POST[ $key ] );
	}
}
