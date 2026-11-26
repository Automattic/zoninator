<?php
/**
 * The zone form on the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Application\GuardedZoneManager;
use Automattic\Zoninator\Domain\ZoneLockedException;
use Automattic\Zoninator\Domain\ZoneNotFoundException;
use Automattic\Zoninator\Domain\ZoninatorExceptionInterface;
use Automattic\Zoninator\Infrastructure\WordPress\Request;

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
	 * Where the Zones screen is.
	 *
	 * @var ScreenLocation
	 */
	private $location;

	/**
	 * Reads and checks requests.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Creates, changes and deletes zones for the current user.
	 *
	 * @var GuardedZoneManager
	 */
	private $manager;

	/**
	 * Constructor.
	 *
	 * @param ScreenLocation     $location Where the Zones screen is.
	 * @param Request            $request Reads and checks requests.
	 * @param GuardedZoneManager $manager Creates, changes and deletes zones for the current user.
	 */
	public function __construct( ScreenLocation $location, Request $request, GuardedZoneManager $manager ) {
		$this->location = $location;
		$this->request  = $request;
		$this->manager  = $manager;
	}

	/**
	 * Hooks the form's handler. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'handle_request' ) );
	}

	/**
	 * Saves the form if this request submitted it, then redirects.
	 */
	public function handle_request() {
		if ( ! $this->location->is_current() ) {
			return;
		}

		$action = $this->request->get_request_var( 'action' );

		if ( ! in_array( $action, array( 'insert', 'update', 'delete' ), true ) ) {
			return;
		}

		$zone_id = 'delete' === $action ? $this->request->get_request_var( 'zone_id', 0, 'absint' ) : $this->request->get_post_var( 'zone_id', 0, 'absint' );

		$this->request->verify_nonce( $action );
		$this->request->verify_access( $action, $zone_id );

		try {
			$url = $this->location->get_url( $this->save( $action, $zone_id ) );
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
			} else {
				$url = add_query_arg( 'message', 'error-general' );
			}
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Does what the form asked.
	 *
	 * @param string $action  'insert', 'update' or 'delete'.
	 * @param int    $zone_id The zone to change or delete.
	 * @return array Query args for the page to show next.
	 */
	private function save( $action, $zone_id ) {
		$user_id = get_current_user_id();

		if ( 'delete' === $action ) {
			$this->manager->remove( $user_id, $zone_id );

			return array( 'message' => 'delete-success' );
		}

		// The storage encodes these for itself, so they only need the slashes WordPress adds to a request taken off.
		$name    = $this->request->get_post_var( 'name', '', 'wp_unslash' );
		$slug    = $this->request->get_post_var( 'slug', sanitize_title( $name ) );
		$details = array(
			'description' => $this->request->get_post_var( 'description', '', 'wp_unslash' ),
		);

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
			$zone_id = (int) $this->manager->create( $user_id, (string) $slug, (string) $name, $details )->get_id();
		}

		return array(
			'action'  => 'edit',
			'zone_id' => $zone_id,
			'message' => $action . '-success',
		);
	}
}
