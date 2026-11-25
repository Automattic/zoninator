<?php
/**
 * Zoninator's settings.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress;

use Automattic\Zoninator\Domain\LockSettingsInterface;
use Automattic\Zoninator\Domain\ZoneSettingsInterface;

/**
 * The values sites tune with filters (lock periods, results per page, zone
 * details) and the messages the Zones screen shows.
 *
 * Each getter applies its filter when it's called, so a filter added at any
 * point before then counts.
 *
 * @since 0.12.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Settings implements LockSettingsInterface, ZoneSettingsInterface {

	/**
	 * Default number of seconds a zone lock lasts without being renewed.
	 */
	private const LOCK_PERIOD = 30;

	/**
	 * Default number of seconds the Zones screen keeps a zone locked while the user makes no changes, before asking whether they're still working.
	 */
	private const MAX_LOCK_PERIOD = 600;

	/**
	 * Default number of posts in a zone search or the latest content.
	 */
	private const POSTS_PER_PAGE = 10;

	/**
	 * Number of seconds a lock is valid for.
	 *
	 * @return int
	 */
	public function get_lock_period(): int {
		return (int) apply_filters( 'zoninator_zone_lock_period', self::LOCK_PERIOD );
	}

	/**
	 * Max number of seconds for all locks in a session.
	 *
	 * @return int
	 */
	public function get_max_lock_period() {
		return apply_filters( 'zoninator_zone_max_lock_period', self::MAX_LOCK_PERIOD );
	}

	/**
	 * Number of posts shown in zone searches and the latest content.
	 *
	 * @return int
	 */
	public function get_posts_per_page() {
		return apply_filters( 'zoninator_posts_per_page', self::POSTS_PER_PAGE );
	}

	/**
	 * Zone details saved in the zone term, with their default values.
	 *
	 * @return array
	 */
	public function get_detail_defaults(): array {
		/**
		 * Filters the zone details saved in the zone term, and their default values.
		 *
		 * @since 0.12.0
		 *
		 * @param array $defaults Detail name => default value. Includes 'description'.
		 */
		return (array) apply_filters( 'zoninator_zone_detail_defaults', array( 'description' => '' ) );
	}

	/**
	 * Gets one of the Zones screen's messages.
	 *
	 * @param string $message_id Message ID, such as 'error-zone-lock'.
	 * @param bool   $encode     Whether to URL-encode it.
	 * @return string The message, or '' if there's none with that ID.
	 */
	public function get_message( $message_id, $encode = false ) {
		$messages = array(
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

		$message = $messages[ $message_id ] ?? '';

		return $encode ? urlencode( $message ) : $message;
	}
}
