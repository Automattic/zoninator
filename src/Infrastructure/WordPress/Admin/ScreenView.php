<?php
/**
 * The Zones admin screen's page.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Prints the Zones screen: its title, any notice, the list of zones, and the
 * editor for the open zone, which is its content and its details.
 *
 * It takes a ScreenData and decides nothing: it makes no capability check and
 * no query. ZoneDetailsView prints the zone form, SearchForm the "Add content"
 * field, and ZonePostRow each post's row.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ScreenView {

	/**
	 * Constructor.
	 *
	 * @param Messages        $messages    The screen's messages.
	 * @param ScreenLocation  $location    Where the Zones screen is.
	 * @param SearchForm      $search_form The field that finds content to add.
	 * @param ZonePostRow     $row         Renders a zone post's row.
	 * @param ZoneDetailsView $details     Prints the zone's name and description.
	 */
	public function __construct(
		private readonly Messages $messages,
		private readonly ScreenLocation $location,
		private readonly SearchForm $search_form,
		private readonly ZonePostRow $row,
		private readonly ZoneDetailsView $details,
	) {
	}

	/**
	 * Prints the page.
	 *
	 * @param ScreenData $data       What the screen shows.
	 * @param string     $message_id ID of a message to show, such as 'update-success', or ''.
	 * @param string     $error_code Code of an error to show, such as 'delete-zone', or ''.
	 */
	public function render( ScreenData $data, $message_id = '', $error_code = '' ): void {
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
						<?php $this->render_editor( $data ); ?>
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
	private function render_zone_list( ScreenData $data ): void {
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

	/**
	 * Prints the editor for the open zone: who else is editing it, if anyone,
	 * then its content and its details. With no zone open, it prints the form
	 * that adds one.
	 *
	 * @param ScreenData $data What the screen shows.
	 */
	private function render_editor( ScreenData $data ): void {
		$zone_id  = $data->get_zone_id();
		$can_open = $zone_id ? $data->can_manage_zones : $data->can_add_zones;
		?>
	<div id="zone-edit-wrapper">
		<?php if ( $can_open ) : ?>
			<?php if ( $data->locked_by ) : ?>
				<div class="updated below-h2">
					<p><?php printf( esc_html( $this->messages->get( 'error-zone-lock' ) ), esc_html( '' !== $data->locked_by_name ? $data->locked_by_name : __( 'another user', 'zoninator' ) ) ); ?></p>
				</div>
				<input type="hidden" id="zone-locked" name="zone-locked" value="1" />
				<?php
			endif;

			if ( $zone_id ) {
				// Curating content is the main task, so it comes before the zone's details.
				?>
				<div class="metabox-holder">
					<?php $this->render_content( $data ); ?>
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

	/**
	 * Prints the box for finding, adding and ordering the open zone's content.
	 * It's read only for a user who can't curate zones, and while someone else
	 * is editing the zone.
	 *
	 * @param ScreenData $data What the screen shows. A zone must be open.
	 */
	private function render_content( ScreenData $data ): void {
		?>
	<div class="postbox zone-col zone-posts-col">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'Zone content', 'zoninator' ); ?></h2>
			<div class="zone-posts-save-input">
				<span class="spinner"></span>
				<p id="zone-posts-save-info" class="zone-posts-save-info" role="status"></p>
			</div>
		</div>
		<div class="inside zone-posts-wrapper <?php echo ! $data->can_manage_zones || $data->locked_by ? 'readonly' : ''; ?>">
			<div id="zone-posts-notices"></div>

			<?php $this->search_form->render(); ?>

			<div class="zone-posts-list">
				<?php
				foreach ( $data->zone_posts as $post ) {
					$this->row->render( $post, $data->active_zone );
				}
				?>
			</div>
		</div>
	</div>
		<?php
	}
}
