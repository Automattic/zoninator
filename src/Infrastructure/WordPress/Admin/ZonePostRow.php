<?php
/**
 * A zone post's row on the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

declare( strict_types = 1 );

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

use Automattic\Zoninator\Infrastructure\WordPress\Capabilities;
use Automattic\Zoninator\Infrastructure\WordPress\Zones;
use WP_Post;
use WP_Term;

/**
 * Renders the row for one post in a zone, as the Zones screen lists it: its
 * position, its title and status, and the links and buttons that act on it.
 *
 * The screen prints a row for each post when it loads, and ZonePostRowController
 * returns one when a post is added.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZonePostRow {

	/**
	 * Constructor.
	 *
	 * @param Zones        $zones        Zones and the posts in them.
	 * @param Capabilities $capabilities The capability checks.
	 */
	public function __construct(
		private readonly Zones $zones,
		private readonly Capabilities $capabilities,
	) {
	}

	/**
	 * Gets a zone post's row, as the Zones screen shows it.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $zone_id Zone ID.
	 * @return string HTML.
	 */
	public function get_html( $post_id, $zone_id ): string {
		ob_start();
		$this->render( get_post( $post_id ), $this->zones->get( (int) $zone_id ) );

		return (string) ob_get_clean();
	}

	/**
	 * Prints a zone post's row. Each column is printed by its callback in the
	 * zoninator_zone_post_columns filter, so sites can add, change and remove columns.
	 *
	 * A zone can hold a post the current user may not read. Its row keeps its
	 * place, so saving the zone's order doesn't drop it, and can be removed,
	 * but says nothing about the post, and the filter's callbacks aren't asked
	 * to print it.
	 *
	 * @param WP_Post $post The post.
	 * @param WP_Term $zone The zone it's in.
	 */
	public function render( $post, $zone ): void {
		if ( ! $this->capabilities->can_use_post( get_current_user_id(), (int) $post->ID ) ) {
			$this->render_columns(
				$post,
				$zone,
				array(
					'position' => array( $this, 'render_position_column' ),
					'info'     => array( $this, 'render_restricted_column' ),
				)
			);

			return;
		}

		/**
		 * Filters the columns shown for each post in a zone on the Zones screen.
		 *
		 * Each callback is given the post and the zone, and prints its cell.
		 *
		 * @since 0.4
		 *
		 * @param callable[] $columns Column key => callback.
		 * @param WP_Post    $post    The post.
		 * @param WP_Term    $zone    The zone it's in.
		 */
		$columns = apply_filters(
			'zoninator_zone_post_columns',
			array(
				'position' => array( $this, 'render_position_column' ),
				'info'     => array( $this, 'render_info_column' ),
			),
			$post,
			$zone
		);

		$this->render_columns( $post, $zone, (array) $columns );
	}

	/**
	 * Prints a row with the given columns.
	 *
	 * @param WP_Post    $post    The post.
	 * @param WP_Term    $zone    The zone it's in.
	 * @param callable[] $columns Column key => callback.
	 */
	private function render_columns( $post, $zone, array $columns ): void {
		?>
	<div id="zone-post-<?php echo esc_attr( $post->ID ); ?>" class="zone-post" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
		<table role="presentation">
			<tr>
				<?php foreach ( $columns as $column_key => $column_callback ) : ?>
					<?php if ( is_callable( $column_callback ) ) : ?>
						<td class="zone-post-col zone-post-<?php echo sanitize_html_class( $column_key ); ?>">
							<?php call_user_func( $column_callback, $post, $zone ); ?>
						</td>
						<?php
					endif;
				endforeach;
				?>
			</tr>
		</table>
		<input type="hidden" name="zone-post-id" value="<?php echo esc_attr( $post->ID ); ?>" />
	</div>
		<?php
	}

	public function render_position_column( $post, $zone ): void {
		$current_position = $this->zones->get_post_order( $post->ID, $zone );
		?>
	<span title="<?php esc_attr_e( 'Drag to change the position of this item, or use Move up and Move down.', 'zoninator' ); ?>">
		<?php echo esc_html( $current_position ); ?>
	</span>
		<?php
	}

	/**
	 * Prints the cell that stands in for a post the user may not read, with
	 * the one thing they can do to it: take it out of the zone.
	 */
	public function render_restricted_column(): void {
		esc_html_e( 'An item you do not have access to.', 'zoninator' );
		?>
	<div class="row-actions">
		<button type="button" class="button-link button-link-delete delete" aria-label="<?php esc_attr_e( 'Remove the item you do not have access to', 'zoninator' ); ?>"><?php esc_html_e( 'Remove', 'zoninator' ); ?></button>
	</div>
		<?php
	}

	public function render_info_column( $post, $zone ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Each accessible name is one string, so translators can put the title where their language needs it.
		$labels = array(
			/* translators: %s: post title. */
			'edit'      => __( 'Edit “%s” (opens in a new tab)', 'zoninator' ),
			/* translators: %s: post title. */
			'move-up'   => __( 'Move up “%s”', 'zoninator' ),
			/* translators: %s: post title. */
			'move-down' => __( 'Move down “%s”', 'zoninator' ),
			/* translators: %s: post title. */
			'remove'    => __( 'Remove “%s”', 'zoninator' ),
			/* translators: %s: post title. */
			'view'      => __( 'View “%s” (opens in a new tab)', 'zoninator' ),
		);
		foreach ( $labels as $action => $label ) {
			$labels[ $action ] = esc_attr( sprintf( $label, $post->post_title ) );
		}

		$action_links = array(
			sprintf( '<button type="button" class="button-link move-up" aria-label="%s">%s</button>', $labels['move-up'], esc_html__( 'Move up', 'zoninator' ) ),
			sprintf( '<button type="button" class="button-link move-down" aria-label="%s">%s</button>', $labels['move-down'], esc_html__( 'Move down', 'zoninator' ) ),
			sprintf( '<button type="button" class="button-link button-link-delete delete" aria-label="%s">%s</button>', $labels['remove'], esc_html__( 'Remove', 'zoninator' ) ),
			sprintf( '<a href="%s" class="view" target="_blank" aria-label="%s">%s</a>', esc_url( get_permalink( $post->ID ) ), $labels['view'], esc_html__( 'View', 'zoninator' ) ),
		);

		// Zone managers can curate posts they can't edit, and get no edit link for those. It goes
		// after the move buttons, so ordering, this screen's main job, comes first, and the move
		// buttons stay in the same place in every row.
		$edit_link = get_edit_post_link( $post->ID );
		if ( $edit_link ) {
			array_splice( $action_links, 2, 0, sprintf( '<a href="%s" class="edit" target="_blank" aria-label="%s">%s</a>', esc_url( $edit_link ), $labels['edit'], esc_html__( 'Edit', 'zoninator' ) ) );
		}
		$status = get_post_status_object( $post->post_status );
		?>
		<?php printf( '%s <span class="zone-post-status">(%s)</span>', esc_html( $post->post_title ), esc_html( $status ? $status->label : $post->post_status ) ); ?>

	<div class="row-actions">
		<?php echo wp_kses_post( implode( ' | ', $action_links ) ); ?>
	</div>
		<?php
	}
}
