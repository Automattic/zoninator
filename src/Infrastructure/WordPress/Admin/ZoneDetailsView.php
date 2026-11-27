<?php
/**
 * A zone's name and description on the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use WP_Term;

/**
 * Prints a zone's name and description: as a form for a user who may change
 * them, and as text for anyone else, or while someone else is editing the
 * zone. With no zone open, the form adds one. ZoneForm saves it.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZoneDetailsView {

	/**
	 * Constructor.
	 *
	 * @param ScreenLocation $location Where the Zones screen is.
	 */
	public function __construct( private readonly ScreenLocation $location ) {
	}

	/**
	 * Prints the zone's details.
	 *
	 * @param ScreenData $data What the screen shows.
	 */
	public function render( ScreenData $data ): void {
		$zone_id = $data->get_zone_id();
		// The zone field hooks have always been given an empty array when no zone is open.
		$zone             = $data->active_zone ?? array();
		$zone_name        = $data->active_zone ? $data->active_zone->name : '';
		$zone_slug        = $data->active_zone ? $data->active_zone->slug : '';
		$zone_description = $data->active_zone ? $data->active_zone->description : '';
		?>
	<div class="form-wrap zone-form zone-info-form">

		<?php if ( $data->can_edit_zone && ! $data->locked_by ) : ?>

			<form id="zone-info" method="post">

				<?php
				/**
				 * Fires before the fields of the zone form on the Zones screen.
				 *
				 * @since 0.4
				 *
				 * @param WP_Term|array $zone The zone, or an empty array on the form for a new zone.
				 */
				do_action( 'zoninator_pre_zone_fields', $zone );
				?>

				<div class="form-field zone-field">
					<label for="zone-name"><?php esc_html_e( 'Name', 'zoninator' ); ?></label>
					<input type="text" id="zone-name" name="name" value="<?php echo esc_attr( $zone_name ); ?>" required />
				</div>

				<?php if ( $zone_id ) : ?>
				<div class="form-field zone-field">
					<label for="zone-slug"><?php esc_html_e( 'Slug', 'zoninator' ); ?></label>
					<span><?php echo esc_html( $zone_slug ); ?></span>
					<input type="hidden" id="zone-slug" name="slug" value="<?php echo esc_attr( $zone_slug ); ?>" />
				</div>
				<?php endif; ?>

				<div class="form-field zone-field">
					<label for="zone-description"><?php esc_html_e( 'Description', 'zoninator' ); ?></label>
					<textarea id="zone-description" name="description"><?php echo esc_html( $zone_description ); ?></textarea>
				</div>

				<?php
				/**
				 * Fires after the fields of the zone form on the Zones screen.
				 *
				 * @since 0.4
				 *
				 * @param WP_Term|array $zone The zone, or an empty array on the form for a new zone.
				 */
				do_action( 'zoninator_post_zone_fields', $zone );
				?>

				<?php if ( $zone_id ) : ?>
					<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ); ?>" />
					<?php wp_nonce_field( $this->location->get_nonce_action( 'update', $zone_id ), ScreenLocation::NONCE_NAME ); ?>
				<?php else : ?>
					<?php wp_nonce_field( $this->location->get_nonce_action( 'insert' ), ScreenLocation::NONCE_NAME ); ?>
				<?php endif; ?>

				<p class="submit submitbox">
					<?php if ( $zone_id ) : ?>
						<input type="submit" value="<?php esc_attr_e( 'Update', 'zoninator' ); ?>" name="submit" class="button" />
						<a href="<?php echo esc_url( $this->get_delete_url( $zone_id ) ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this zone?', 'zoninator' ) ); ?>')"><?php esc_html_e( 'Delete', 'zoninator' ); ?></a>
					<?php else : ?>
						<input type="submit" value="<?php esc_attr_e( 'Add New Zone', 'zoninator' ); ?>" name="submit" class="button button-primary" />
					<?php endif; ?>
				</p>

				<?php if ( ! $zone_id ) : ?>
					<p class="description"><?php esc_html_e( 'Once the zone is added, you can add content to it.', 'zoninator' ); ?></p>
				<?php endif; ?>

				<input type="hidden" name="action" value="<?php echo $zone_id ? 'update' : 'insert'; ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( ScreenLocation::SLUG ); ?>">

			</form>
		<?php else : ?>
			<div id="zone-info-readonly" class="readonly">
				<?php
				/**
				 * Fires before a zone's details when the Zones screen shows them read-only,
				 * because another user has the zone locked or the user can't edit it.
				 *
				 * @since 0.4
				 *
				 * @param WP_Term|array $zone The zone.
				 */
				do_action( 'zoninator_pre_zone_readonly', $zone );
				?>

				<div class="form-field zone-field">
					<strong><?php esc_html_e( 'Name', 'zoninator' ); ?></strong>
					<span><?php echo esc_html( $zone_name ); ?></span>
				</div>

				<div class="form-field zone-field">
					<strong><?php esc_html_e( 'Description', 'zoninator' ); ?></strong>
					<span><?php echo esc_html( $zone_description ); ?></span>
				</div>

				<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ); ?>" />

				<?php
				/**
				 * Fires after a zone's details when the Zones screen shows them read-only.
				 *
				 * @since 0.4
				 *
				 * @param WP_Term|array $zone The zone.
				 */
				do_action( 'zoninator_post_zone_readonly', $zone );
				?>
			</div>
		<?php endif; ?>
	</div>
		<?php
	}

	/**
	 * @param int $zone_id Zone ID.
	 * @return string The URL that deletes the zone.
	 */
	private function get_delete_url( $zone_id ): string {
		return wp_nonce_url(
			$this->location->get_url(
				array(
					'action'  => 'delete',
					'zone_id' => $zone_id,
				)
			),
			$this->location->get_nonce_action( 'delete', (int) $zone_id ),
			ScreenLocation::NONCE_NAME
		);
	}
}
