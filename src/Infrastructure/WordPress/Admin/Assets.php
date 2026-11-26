<?php
/**
 * The Zones admin screen's script and styles.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Settings;

/**
 * Loads the Zones screen's script, with the options it needs, and its styles.
 *
 * It registers its own hooks in register_hooks(), and sites reach it with
 * zoninator_container()->get( Assets::class ) to unhook them.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Assets {

	/**
	 * Constructor.
	 *
	 * @param ScreenLocation $location Where the Zones screen is.
	 * @param Settings       $settings Zoninator's settings.
	 * @param Messages       $messages The screen's messages.
	 */
	public function __construct(
		private readonly ScreenLocation $location,
		private readonly Settings $settings,
		private readonly Messages $messages,
	) {
	}

	/**
	 * Hooks the script and the styles. PluginBootstrapper::register_hooks() calls this once.
	 */
	public function register_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Loads the screen's script on the Zones screen.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->location->is_current() ) {
			return;
		}

		wp_enqueue_script( 'zoninator-js', plugin_dir_url( ZONINATOR_FILE ) . 'js/zoninator.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-sortable', 'jquery-ui-autocomplete', 'wp-a11y', 'wp-api-fetch' ), ZONINATOR_VERSION, true );

		$options = array(
			'baseUrl'           => $this->location->get_url(),
			'adminUrl'          => admin_url(),
			'errorGeneral'      => $this->messages->get( 'error-general' ),
			'errorZoneLock'     => sprintf( $this->messages->get( 'error-zone-lock' ), __( 'another user', 'zoninator' ) ),
			'errorSave'         => __( 'The order of the zone could not be saved.', 'zoninator' ),
			'retry'             => __( 'Try again', 'zoninator' ),
			/* translators: %s: time of day. */
			'savedAt'           => __( 'Saved at %s', 'zoninator' ),
			'confirmZoneLock'   => __( 'You have had this zone open for a while. Select OK to keep editing it, or Cancel to unlock it and go to the Dashboard.', 'zoninator' ),
			'zoneLockPeriod'    => $this->settings->get_lock_period(),
			'zoneLockPeriodMax' => $this->settings->get_max_lock_period(),
			'postAdded'         => __( 'Item added to the zone.', 'zoninator' ),
			'postRemoved'       => __( 'Item removed from the zone.', 'zoninator' ),
			/* translators: 1: new position of the item in the zone, 2: number of items in the zone. */
			'postMoved'         => __( 'Item moved to position %1$d of %2$d.', 'zoninator' ),
			/* translators: Shown between a search result's post type, date and status. Keep the spaces your language needs. */
			'metaSeparator'     => _x( ' · ', 'search result details separator', 'zoninator' ),
			/* translators: Read out by screen readers between a search result's title, post type, date and status. Keep the spaces your language needs. */
			'listSeparator'     => _x( ', ', 'search result accessible name separator', 'zoninator' ),
		);
		wp_localize_script( 'zoninator-js', 'zoninatorOptions', $options );

		// For mobile support: https://github.com/furf/jquery-ui-touch-punch
		wp_enqueue_script( 'jquery-ui-touch-punch', plugin_dir_url( ZONINATOR_FILE ) . 'js/jquery.ui.touch-punch.min.js', array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse' ), ZONINATOR_VERSION, true );
	}

	/**
	 * Loads the screen's styles on the Zones screen.
	 */
	public function enqueue_styles(): void {
		if ( $this->location->is_current() ) {
			wp_enqueue_style( 'zoninator-styles', plugin_dir_url( ZONINATOR_FILE ) . 'css/zoninator.css', false, ZONINATOR_VERSION, 'all' );
		}
	}
}
