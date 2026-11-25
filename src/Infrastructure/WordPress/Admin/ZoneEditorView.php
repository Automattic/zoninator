<?php
/**
 * The part of the Zones admin screen that edits one zone.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Prints the editor for the open zone: who else is editing it, if anyone,
 * then its content and its details. With no zone open, it prints the form
 * that adds one.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZoneEditorView {

	/**
	 * The screen's messages.
	 *
	 * @var Messages
	 */
	private $messages;

	/**
	 * Prints the box that holds the zone's content.
	 *
	 * @var ZoneContentView
	 */
	private $content;

	/**
	 * Prints the zone's name and description.
	 *
	 * @var ZoneDetailsView
	 */
	private $details;

	/**
	 * Constructor.
	 *
	 * @param Messages        $messages The screen's messages.
	 * @param ZoneContentView $content  Prints the box that holds the zone's content.
	 * @param ZoneDetailsView $details  Prints the zone's name and description.
	 */
	public function __construct( Messages $messages, ZoneContentView $content, ZoneDetailsView $details ) {
		$this->messages = $messages;
		$this->content  = $content;
		$this->details  = $details;
	}

	/**
	 * Prints the editor.
	 *
	 * @param ScreenData $data What the screen shows.
	 */
	public function render( ScreenData $data ) {
		$zone_id  = $data->get_zone_id();
		$can_open = $zone_id ? $data->can_manage_zones : $data->can_add_zones;
		?>
	<div id="zone-edit-wrapper">
		<?php if ( $can_open ) : ?>
			<?php if ( $data->locked_by ) : ?>
				<?php $locking_user = get_userdata( $data->locked_by ); ?>
				<div class="updated below-h2">
					<p><?php printf( esc_html( $this->messages->get( 'error-zone-lock' ) ), esc_html( $locking_user ? $locking_user->display_name : __( 'another user', 'zoninator' ) ) ); ?></p>
				</div>
				<input type="hidden" id="zone-locked" name="zone-locked" value="1" />
				<?php
			endif;

			if ( $zone_id ) {
				// Curating content is the main task, so it comes before the zone's details.
				?>
				<div class="metabox-holder">
					<?php $this->content->render( $data ); ?>
					<div class="postbox zone-col zone-info-col">
						<div class="postbox-header">
							<h2 class="hndle"><?php esc_html_e( 'Zone details', 'zoninator' ); ?></h2>
						</div>
						<div class="inside">
							<?php $this->details->render( $data ); ?>
						</div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="col-wrap zone-col zone-info-col">
					<?php $this->details->render( $data ); ?>
				</div>
				<?php
			}
		endif;
		?>
	</div>
		<?php
	}
}
