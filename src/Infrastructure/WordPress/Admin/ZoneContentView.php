<?php
/**
 * The box that holds a zone's content on the Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Infrastructure\WordPress\Admin;

/**
 * Prints the box for finding, adding and ordering a zone's content.
 *
 * @since 1.0.0
 * @internal Use the template tags, the Application classes and the hooks instead.
 */
final class ZoneContentView {

	/**
	 * The field that finds content to add.
	 *
	 * @var SearchForm
	 */
	private $search_form;

	/**
	 * Renders a zone post's row.
	 *
	 * @var ZonePostRow
	 */
	private $row;

	/**
	 * Constructor.
	 *
	 * @param SearchForm  $search_form The field that finds content to add.
	 * @param ZonePostRow $row         Renders a zone post's row.
	 */
	public function __construct( SearchForm $search_form, ZonePostRow $row ) {
		$this->search_form = $search_form;
		$this->row         = $row;
	}

	/**
	 * Prints the box. It's read only for a user who can't curate zones, and while someone else is editing the zone.
	 *
	 * @param ScreenData $data What the screen shows. A zone must be open.
	 */
	public function render( ScreenData $data ) {
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
