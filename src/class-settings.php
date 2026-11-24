<?php
/**
 * Zoninator's settings.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator;

/**
 * The values sites tune with filters (lock periods, results per page, zone
 * details) and the messages the Zones screen shows.
 *
 * The filters apply on init, as they always have. Until 1.0.0, a write to one
 * of the old public Zoninator properties still changes the value here.
 *
 * @since 0.12.0
 */
final class Settings {

	/**
	 * Current values, keyed by the name of the old Zoninator property.
	 *
	 * @var array
	 */
	private $values = array(
		// Number of seconds a lock is valid for.
		'zone_lock_period'     => 30,
		// Max number of seconds for all locks in a session.
		'zone_max_lock_period' => 600,
		// Number of posts shown in zone searches and the latest content.
		'posts_per_page'       => 10,
		// Zone details saved in the zone term, with their default values. Add
		// your own with the zoninator_zone_detail_defaults filter.
		'zone_detail_defaults' => array(
			'description' => '',
		),
		// Set on init, so the messages are translated.
		'zone_messages'        => null,
	);

	/**
	 * Sets the messages and applies the settings filters. Runs on init.
	 */
	public function init() {
		$this->values['zone_messages'] = array(
			'insert-success'      => __( 'The zone was successfully created.', 'zoninator' ),
			'update-success'      => __( 'The zone was successfully updated.', 'zoninator' ),
			'delete-success'      => __( 'The zone was successfully deleted.', 'zoninator' ),
			'error-general'       => __( 'Sorry, something went wrong! Please try again?', 'zoninator' ),
			'error-delete-zone'   => __( "Sorry, we couldn't delete the zone.", 'zoninator' ),
			'error-invalid-zone'  => __( "Sorry, that zone doesn't exist.", 'zoninator' ),
			/* translators: %s: the locking user's display name, or "another user". */
			'error-zone-lock'     => __( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
			'error-zone-lock-max' => __( 'Sorry, you have reached the maximum idle limit and will now be redirected to the Dashboard.', 'zoninator' ),
		);

		$this->values['zone_lock_period']     = apply_filters( 'zoninator_zone_lock_period', $this->values['zone_lock_period'] );
		$this->values['zone_max_lock_period'] = apply_filters( 'zoninator_zone_max_lock_period', $this->values['zone_max_lock_period'] );
		$this->values['posts_per_page']       = apply_filters( 'zoninator_posts_per_page', $this->values['posts_per_page'] );
	}

	/**
	 * Gets a value by the name of the old Zoninator property, unfiltered.
	 *
	 * @param string $name Old property name.
	 * @return mixed
	 */
	public function get( $name ) {
		return $this->values[ $name ] ?? null;
	}

	/**
	 * Sets a value by the name of the old Zoninator property.
	 *
	 * Only for writes to the old properties, which still take effect until 1.0.0.
	 *
	 * @param string $name  Old property name.
	 * @param mixed  $value New value.
	 * @return bool Whether it's a setting.
	 */
	public function set( $name, $value ) {
		if ( ! array_key_exists( $name, $this->values ) ) {
			return false;
		}

		$this->values[ $name ] = $value;
		return true;
	}

	/**
	 * Number of seconds a lock is valid for.
	 *
	 * @return int
	 */
	public function get_lock_period() {
		return $this->values['zone_lock_period'];
	}

	/**
	 * Max number of seconds for all locks in a session.
	 *
	 * @return int
	 */
	public function get_max_lock_period() {
		return $this->values['zone_max_lock_period'];
	}

	/**
	 * Number of posts shown in zone searches and the latest content.
	 *
	 * @return int
	 */
	public function get_posts_per_page() {
		return $this->values['posts_per_page'];
	}

	/**
	 * Zone details saved in the zone term, with their default values.
	 *
	 * @return array
	 */
	public function get_detail_defaults() {
		/**
		 * Filters the zone details saved in the zone term, and their default values.
		 *
		 * @since 0.12.0
		 *
		 * @param array $defaults Detail name => default value. Includes 'description'.
		 */
		return (array) apply_filters( 'zoninator_zone_detail_defaults', $this->values['zone_detail_defaults'] );
	}

	/**
	 * Gets one of the Zones screen's messages.
	 *
	 * @param string $message_id Message ID, such as 'error-zone-lock'.
	 * @param bool   $encode     Whether to URL-encode it.
	 * @return string The message, or '' if there's none with that ID.
	 */
	public function get_message( $message_id, $encode = false ) {
		$message = '';

		if ( ! empty( $this->values['zone_messages'][ $message_id ] ) ) {
			$message = $this->values['zone_messages'][ $message_id ];
		}

		if ( $encode ) {
			$message = urlencode( $message );
		}

		return $message;
	}
}
