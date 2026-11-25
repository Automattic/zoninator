<?php
/**
 * The Zones admin screen's page.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Prints the Zones screen: its title, any notice, the list of zones, and the
 * editor for the open zone.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ScreenView {

	/**
	 * The screen's messages.
	 *
	 * @var Messages
	 */
	private $messages;

	/**
	 * Where the Zones screen is, for its links.
	 *
	 * @var ScreenLocation
	 */
	private $location;

	/**
	 * Prints the editor for the open zone.
	 *
	 * @var ZoneEditorView
	 */
	private $editor;

	/**
	 * Constructor.
	 *
	 * @param Messages       $messages The screen's messages.
	 * @param ScreenLocation $location Where the Zones screen is.
	 * @param ZoneEditorView $editor   Prints the editor for the open zone.
	 */
	public function __construct( Messages $messages, ScreenLocation $location, ZoneEditorView $editor ) {
		$this->messages = $messages;
		$this->location = $location;
		$this->editor   = $editor;
	}

	/**
	 * Prints the page.
	 *
	 * @param ScreenData $data       What the screen shows.
	 * @param string     $message_id ID of a message to show, such as 'update-success', or ''.
	 * @param string     $error_code Code of an error to show, such as 'delete-zone', or ''.
	 */
	public function render( ScreenData $data, $message_id = '', $error_code = '' ) {
		if ( $data->active_zone ) {
			$title = __( 'Edit Zone', 'zoninator' );
		} elseif ( $data->can_add_zones ) {
			$title = __( 'Add New Zone', 'zoninator' );
		} else {
			$title = __( 'Zones', 'zoninator' );
		}

		$message = $this->messages->get( $message_id );
		$error   = '';
		if ( $error_code ) {
			$error = $this->messages->get( 'error-' . $error_code );
			if ( '' === $error ) {
				$error = $this->messages->get( 'error-general' );
			}
		}
		?>
	<div class="wrap zoninator-page">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
		<?php if ( $data->active_zone_id && $data->can_add_zones ) : ?>
			<a href="<?php echo esc_url( $this->location->get_url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action zone-button-add-new"><?php echo esc_html_x( 'Add New', 'zone', 'zoninator' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">

			<?php if ( $message ) : ?>
			<div id="zone-message" class="updated below-h2">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
				<?php
			endif;
			?>
			<?php if ( $error ) : ?>
			<div id="zone-message" class="error below-h2">
				<p><?php echo esc_html( $error ); ?></p>
			</div>
				<?php
			endif;
			?>

		<div id="zoninator-wrap">
			<div id="col-container" class="wp-clearfix">
				<div id="col-left">
					<div class="col-wrap">
						<?php $this->render_zone_list( $data ); ?>
					</div>
				</div>
				<div id="col-right">
					<div class="col-wrap">
						<?php $this->editor->render( $data ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php
	}

	/**
	 * Prints the list of zones, each a link that opens it.
	 *
	 * @param ScreenData $data What the screen shows.
	 */
	private function render_zone_list( ScreenData $data ) {
		?>
	<table class="wp-list-table widefat fixed striped zone-tabs">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Zones', 'zoninator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $data->zones ) ) : ?>
				<tr class="no-items">
					<td><?php esc_html_e( 'No zones found.', 'zoninator' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $data->zones as $zone ) : ?>
				<?php
				$is_active = $data->active_zone_id && (int) $zone->term_id === $data->active_zone_id;
				$zone_link = $this->location->get_url(
					array(
						'action'  => 'edit',
						'zone_id' => $zone->term_id,
					)
				);
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $zone_link ); ?>"<?php echo $is_active ? ' class="row-title" aria-current="page"' : ''; ?>><?php echo esc_html( $zone->name ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
		<?php
	}
}
