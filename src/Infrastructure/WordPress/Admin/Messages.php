<?php
/**
 * The messages the Zones admin screen shows.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * The Zones screen's notices, by ID. They're translated when they're asked for.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class Messages {

	/**
	 * Gets a message.
	 *
	 * @param string $message_id Message ID, such as 'error-zone-lock'.
	 * @return string The message, or '' if there's none with that ID.
	 */
	public function get( $message_id ): string {
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

		return $messages[ $message_id ] ?? '';
	}
}
