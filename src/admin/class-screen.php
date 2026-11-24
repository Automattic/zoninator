<?php
/**
 * The Zones admin screen.
 *
 * @package Automattic\Zoninator
 */

namespace Automattic\Zoninator\Admin;

use Automattic\Zoninator\Capabilities;
use Automattic\Zoninator\Locks;
use Automattic\Zoninator\Request;
use Automattic\Zoninator\Settings;
use Automattic\Zoninator\Zones;

/**
 * Renders the Zones admin screen, saves its zone form, and loads its assets.
 *
 * Zoninator keeps a public method for each of these under its original name
 * (admin_page() for render(), admin_page_zone_post() for render_zone_post(),
 * and so on), which delegates here. Hooks are registered on it, so
 * array( Zoninator(), 'method' ) keeps working as a callback. Callbacks this
 * class hands to WordPress point at Zoninator too, for the same reason.
 *
 * @since 0.12.0
 */
final class Screen {

	/**
	 * The admin page's slug, as in admin.php?page=zoninator.
	 */
	public const PAGE_SLUG = 'zoninator';

	/**
	 * Zones and the posts in them.
	 *
	 * @var Zones
	 */
	private $zones;

	/**
	 * Zone locks.
	 *
	 * @var Locks
	 */
	private $locks;

	/**
	 * Zoninator's settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The capability checks.
	 *
	 * @var Capabilities
	 */
	private $capabilities;

	/**
	 * Reads and checks requests.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * Callbacks on the Zoninator facade for the menu page and the default zone post columns, keyed 'page', 'position' and 'info'. Sites unhook and replace these, so they must stay on the facade.
	 *
	 * @var array
	 */
	private $callbacks;

	/**
	 * Constructor.
	 *
	 * @param Zones $zones Zones and the posts in them.
	 * @param Locks $locks Zone locks.
	 * @param Settings $settings Zoninator's settings.
	 * @param Capabilities $capabilities The capability checks.
	 * @param Request $request Reads and checks requests.
	 * @param array $callbacks Callbacks on the Zoninator facade for the menu page and the default zone post columns, keyed 'page', 'position' and 'info'. Sites unhook and replace these, so they must stay on the facade.
	 */
	public function __construct( Zones $zones, Locks $locks, Settings $settings, Capabilities $capabilities, Request $request, array $callbacks ) {
		$this->zones        = $zones;
		$this->locks        = $locks;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->request      = $request;
		$this->callbacks    = $callbacks;
	}

	/**
	 * Gets the URL of the Zones screen, with any query arguments added.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	public function get_page_url( $args = array() ) {
		$url = menu_page_url( self::PAGE_SLUG, false );

		foreach ( $args as $arg_key => $arg_value ) {
			$url = add_query_arg( $arg_key, $arg_value, $url );
		}

		return $url;
	}

	public function register_menu_page() {
		// Set up page
		add_menu_page( __( 'Zones', 'zoninator' ), __( 'Zones', 'zoninator' ), $this->capabilities->get_manage_zones_cap(), self::PAGE_SLUG, $this->callbacks['page'], 'dashicons-layout', 11 );
	}

	public function enqueue_scripts() {
		if ( $this->is_current() ) {
			wp_enqueue_script( 'zoninator-js', plugin_dir_url( ZONINATOR_FILE ) . 'js/zoninator.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-sortable', 'jquery-ui-autocomplete', 'wp-a11y', 'wp-api-fetch' ), ZONINATOR_VERSION, true );

			$options = array(
				'baseUrl'           => $this->get_page_url(),
				'adminUrl'          => admin_url(),
				'ajaxNonceAction'   => Request::get_nonce_key( Ajax::NONCE_ACTION ),
				'errorGeneral'      => $this->settings->get_message( 'error-general' ),
				'errorZoneLock'     => sprintf( $this->settings->get_message( 'error-zone-lock' ), __( 'another user', 'zoninator' ) ),
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
			);
			wp_localize_script( 'zoninator-js', 'zoninatorOptions', $options );

			// For mobile support: https://github.com/furf/jquery-ui-touch-punch
			wp_enqueue_script( 'jquery-ui-touch-punch', plugin_dir_url( ZONINATOR_FILE ) . 'js/jquery.ui.touch-punch.min.js', array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse' ), ZONINATOR_VERSION, true );
		}
	}

	public function enqueue_styles() {
		if ( $this->is_current() ) {
				wp_enqueue_style( 'zoninator-styles', plugin_dir_url( ZONINATOR_FILE ) . 'css/zoninator.css', false, ZONINATOR_VERSION, 'all' );
		}
	}

	public function handle_request() {
		if ( $this->is_current() ) {
			$action = $this->request->get_request_var( 'action' );

			switch ( $action ) {
				case 'insert':
				case 'update':
					$zone_id = $this->request->get_post_var( 'zone_id', 0, 'absint' );

					$this->request->verify_nonce( $action );
					$this->request->verify_access( $action, $zone_id );

					$name    = $this->request->get_post_var( 'name', '', array( Request::class, 'sanitize_value' ) );
					$slug    = $this->request->get_post_var( 'slug', sanitize_title( $name ) );
					$details = array(
						'description' => $this->request->get_post_var( 'description', '', array( Request::class, 'sanitize_value' ) ),
					);

					// Go by the action that was checked, not by whether a zone ID was sent.
					if ( 'update' === $action ) {
						$result = $this->zones->update(
							$zone_id,
							array(
								'name'    => $name,
								'slug'    => $slug,
								'details' => $details,
							)
						);
					} else {
						$result = $this->zones->insert( $slug, $name, $details );
					}

					if ( is_wp_error( $result ) ) {
						wp_safe_redirect( add_query_arg( 'message', 'error-general' ) );
						exit;
					} else {
						if ( 'insert' === $action && isset( $result['term_id'] ) ) {
							$zone_id = $result['term_id'];
						}

						// Redirect with success message
						$message = sprintf( '%s-success', $action );
						wp_safe_redirect(
							$this->get_page_url(
								array(
									'action'  => 'edit',
									'zone_id' => $zone_id,
									'message' => $message,
								)
							)
						);
						exit;
					}

				case 'delete':
					$zone_id = $this->request->get_request_var( 'zone_id', 0, 'absint' );

					$this->request->verify_nonce( $action );
					$this->request->verify_access( $action, $zone_id );

					if ( $zone_id ) {
						$result = $this->zones->delete( $zone_id );
					}

					if ( is_wp_error( $result ) ) {
						$redirect_args = array( 'error' => $result->get_error_code() );
					} else {
						$redirect_args = array( 'message' => 'delete-success' );
					}

					wp_safe_redirect( $this->get_page_url( $redirect_args ) );
					exit;
			}
		}
	}

	public function render() {
		global $zoninator_admin_page;

		$title = __( 'Zones', 'zoninator' );

		$zones = $this->zones->get_all( apply_filters( 'zoninator_admin_page_get_zones_args', array() ) );

		$default_active_zone = 0;
		if ( ! $this->capabilities->current_user_can_add_zones() && ! empty( $zones ) ) {
			$default_active_zone = $zones[0]->term_id;
		}

		$active_zone_id = $this->request->get_request_var( 'zone_id', $default_active_zone, 'absint' );
		$active_zone    = empty( $active_zone_id ) ? array() : $this->zones->get( $active_zone_id );
		if ( ! empty( $active_zone ) ) {
			$title = __( 'Edit Zone', 'zoninator' );
		} elseif ( $this->capabilities->current_user_can_add_zones() ) {
			$title = __( 'Add New Zone', 'zoninator' );
		}

		$message    = $this->settings->get_message( $this->request->get_get_var( 'message', '', 'sanitize_key' ) );
		$error_code = $this->request->get_get_var( 'error', '', 'sanitize_key' );
		if ( $error_code ) {
			$error = $this->settings->get_message( 'error-' . $error_code );
			if ( '' === $error ) {
				$error = $this->settings->get_message( 'error-general' );
			}
		} else {
			$error = '';
		}

		?>
	<div class="wrap zoninator-page">
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
		<?php if ( $active_zone_id && $this->capabilities->current_user_can_add_zones() ) : ?>
			<a href="<?php echo esc_url( $this->get_page_url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action zone-button-add-new"><?php echo esc_html_x( 'Add New', 'zone', 'zoninator' ); ?></a>
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
						<?php $this->render_zone_tabs( $zones, $active_zone_id ); ?>
					</div>
				</div>
				<div id="col-right">
					<div class="col-wrap">
						<?php $this->render_zone_edit( $active_zone ); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
		<?php
	}

	public function render_zone_tabs( $zones, $active_zone_id = 0 ) {
		?>
	<table class="wp-list-table widefat fixed striped zone-tabs">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Zones', 'zoninator' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $zones ) ) : ?>
				<tr class="no-items">
					<td><?php esc_html_e( 'No zones found.', 'zoninator' ); ?></td>
				</tr>
			<?php endif; ?>
			<?php foreach ( $zones as $zone ) : ?>
				<?php
				$zone_id   = $this->zones->get_id( $zone );
				$is_active = $active_zone_id && $zone_id == $active_zone_id;
				$zone_link = $this->get_page_url(
					array(
						'action'  => 'edit',
						'zone_id' => $zone_id,
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

	public function render_zone_edit( $zone = null ) {
		$zone_id = Request::get_value_or_default( 'term_id', $zone, 0, 'absint' );

		$zone_posts = $zone_id ? $this->zones->get_posts( $zone ) : array();

		$zone_locked = $this->locks->is_locked( $zone_id );

		$delete_link = $this->get_page_url(
			array(
				'action'  => 'delete',
				'zone_id' => $zone_id,
			)
		);
		$delete_link = wp_nonce_url( $delete_link, Request::get_nonce_key( 'delete' ) );
		$can_open    = ( 0 == $zone_id && $this->capabilities->current_user_can_add_zones() ) || ( 0 != $zone_id && $this->capabilities->current_user_can_manage_zones() );
		?>
	<div id="zone-edit-wrapper">
		<?php if ( $can_open ) : ?>
			<?php if ( $zone_locked ) : ?>
				<?php $locking_user = get_userdata( $zone_locked ); ?>
				<div class="updated below-h2">
					<p><?php printf( esc_html( $this->settings->get_message( 'error-zone-lock' ) ), esc_html( $locking_user->display_name ) ); ?></p>
				</div>
				<input type="hidden" id="zone-locked" name="zone-locked" value="1" />
				<?php
			endif;

			// Ideally, we should separate nonces for each action. But this will do for simplicity.
			wp_nonce_field( Request::get_nonce_key( Ajax::NONCE_ACTION ), Request::get_nonce_key( Ajax::NONCE_ACTION ), false );

			if ( $zone_id ) {
				// Curating content is the main task, so it comes before the zone's details.
				?>
				<div class="metabox-holder">
					<?php $this->render_zone_content( $zone, $zone_posts, $zone_locked ); ?>
					<div class="postbox zone-col zone-info-col">
						<div class="postbox-header">
							<h2 class="hndle"><?php esc_html_e( 'Zone details', 'zoninator' ); ?></h2>
						</div>
						<div class="inside">
							<?php $this->render_zone_details( $zone, $zone_locked, $delete_link ); ?>
						</div>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="col-wrap zone-col zone-info-col">
					<?php $this->render_zone_details( $zone, $zone_locked, $delete_link ); ?>
				</div>
				<?php
			}
		endif;
		?>
	</div>
		<?php
	}

	/**
	 * Renders the zone's name and description, as a form or read only.
	 *
	 * @param WP_Term|array $zone        The zone, or an empty array for a new one.
	 * @param int|false     $zone_locked ID of the user holding the zone's lock, or false.
	 * @param string        $delete_link URL that deletes the zone.
	 */
	public function render_zone_details( $zone, $zone_locked, $delete_link ) {
		$zone_id          = Request::get_value_or_default( 'term_id', $zone, 0, 'absint' );
		$zone_name        = Request::get_value_or_default( 'name', $zone );
		$zone_slug        = Request::get_value_or_default( 'slug', $zone, '' );
		$zone_description = Request::get_value_or_default( 'description', $zone );
		?>
	<div class="form-wrap zone-form zone-info-form">

		<?php if ( $this->capabilities->current_user_can_edit_zones( $zone_id ) && ! $zone_locked ) : ?>

			<form id="zone-info" method="post">

				<?php do_action( 'zoninator_pre_zone_fields', $zone ); ?>

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

				<?php do_action( 'zoninator_post_zone_fields', $zone ); ?>

				<?php if ( $zone_id ) : ?>
					<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ); ?>" />
					<?php wp_nonce_field( Request::get_nonce_key( 'update' ) ); ?>
				<?php else : ?>
					<?php wp_nonce_field( Request::get_nonce_key( 'insert' ) ); ?>
				<?php endif; ?>

				<p class="submit submitbox">
					<?php if ( $zone_id ) : ?>
						<input type="submit" value="<?php esc_attr_e( 'Update', 'zoninator' ); ?>" name="submit" class="button" />
						<a href="<?php echo esc_url( $delete_link ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this zone?', 'zoninator' ) ); ?>')"><?php esc_html_e( 'Delete', 'zoninator' ); ?></a>
					<?php else : ?>
						<input type="submit" value="<?php esc_attr_e( 'Add New Zone', 'zoninator' ); ?>" name="submit" class="button button-primary" />
					<?php endif; ?>
				</p>

				<?php if ( ! $zone_id ) : ?>
					<p class="description"><?php esc_html_e( 'Once the zone is added, you can add content to it.', 'zoninator' ); ?></p>
				<?php endif; ?>

				<input type="hidden" name="action" value="<?php echo $zone_id ? 'update' : 'insert'; ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">

			</form>
		<?php else : ?>
			<div id="zone-info-readonly" class="readonly">
				<?php do_action( 'zoninator_pre_zone_readonly', $zone ); ?>

				<div class="form-field zone-field">
					<strong><?php esc_html_e( 'Name', 'zoninator' ); ?></strong>
					<span><?php echo esc_html( $zone_name ); ?></span>
				</div>

				<div class="form-field zone-field">
					<strong><?php esc_html_e( 'Description', 'zoninator' ); ?></strong>
					<span><?php echo esc_html( $zone_description ); ?></span>
				</div>

				<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ); ?>" />

				<?php do_action( 'zoninator_post_zone_readonly', $zone ); ?>
			</div>
		<?php endif; ?>
	</div>
		<?php
	}

	/**
	 * Renders the box for finding, adding and ordering a zone's content.
	 *
	 * @param WP_Term   $zone        The zone.
	 * @param WP_Post[] $zone_posts  Posts in the zone, in order.
	 * @param int|false $zone_locked ID of the user holding the zone's lock, or false.
	 */
	public function render_zone_content( $zone, $zone_posts, $zone_locked ) {
		?>
	<div class="postbox zone-col zone-posts-col">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'Zone content', 'zoninator' ); ?></h2>
			<div class="zone-posts-save-input">
				<span class="spinner"></span>
				<p id="zone-posts-save-info" class="zone-posts-save-info" role="status"></p>
			</div>
		</div>
		<div class="inside zone-posts-wrapper <?php echo ! $this->capabilities->current_user_can_manage_zones() || $zone_locked ? 'readonly' : ''; ?>">
			<div id="zone-posts-notices"></div>

			<?php $this->render_search_form(); ?>

			<div class="zone-posts-list">
				<?php
				foreach ( $zone_posts as $post ) {
					$this->render_zone_post( $post, $zone );
				}
				?>
			</div>
		</div>
	</div>
		<?php
	}

	public function render_zone_post( $post, $zone ) {
		$columns = apply_filters(
			'zoninator_zone_post_columns',
			array(
				'position' => $this->callbacks['position'],
				'info'     => $this->callbacks['info'],
			),
			$post,
			$zone
		);
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

	public function render_position_column( $post, $zone ) {
		$current_position = $this->zones->get_post_order( $post->ID, $zone );
		?>
	<span title="<?php esc_attr_e( 'Drag to change the position of this item, or use Move up and Move down.', 'zoninator' ); ?>">
		<?php echo esc_html( $current_position ); ?>
	</span>
		<?php
	}

	public function render_info_column( $post, $zone ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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
		?>
		<?php printf( '%s <span class="zone-post-status">(%s)</span>', esc_html( $post->post_title ), esc_html( $this->zones->get_post_status_label( $post ) ) ); ?>

	<div class="row-actions">
		<?php echo wp_kses_post( implode( ' | ', $action_links ) ); ?>
	</div>
		<?php
	}

	public function render_search_filters() {
		?>
	<div class="zone-advanced-search-filters-heading">
		<button type="button" class="button-link zone-toggle-advanced-search" aria-expanded="false" aria-controls="zone-advanced-search-filters" data-alt-label="<?php esc_attr_e( 'Hide filters', 'zoninator' ); ?>"><?php esc_html_e( 'Show filters', 'zoninator' ); ?></button>
	</div>
	<div class="zone-advanced-search-filters-wrapper" id="zone-advanced-search-filters">
		<?php do_action( 'zoninator_advanced_search_fields' ); ?>
	</div>
		<?php
	}

	public function render_category_filter() {
		$current_cat = $this->request->get_post_var( 'zone_advanced_filter_taxonomy', '', 'absint' );
		?>
	<label for="zone_advanced_filter_taxonomy" class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'zoninator' ); ?></label>
		<?php
		wp_dropdown_categories(
			apply_filters(
				'zoninator_advanced_filter_category',
				array(
					'show_option_all' => __( 'All categories', 'zoninator' ),
					'selected'        => $current_cat,
					'name'            => 'zone_advanced_filter_taxonomy',
					'id'              => 'zone_advanced_filter_taxonomy',
					'hide_if_empty'   => true,
				)
			)
		);
	}

	public function render_date_filter() {
		$current_date = $this->request->get_post_var( 'zone_advanced_filter_date', apply_filters( 'zoninator_advanced_filter_date_default', '' ), 'striptags' );
		$date_filters = apply_filters( 'zoninator_advanced_filter_date', array( 'all', 'today', 'yesterday' ) );
		$date_labels  = array(
			'all'       => __( 'All dates', 'zoninator' ),
			'today'     => __( 'Today', 'zoninator' ),
			'yesterday' => __( 'Yesterday', 'zoninator' ),
		);
		?>
	<label for="zone_advanced_filter_date" class="screen-reader-text"><?php esc_html_e( 'Filter by date', 'zoninator' ); ?></label>
	<select name="zone_advanced_filter_date" id="zone_advanced_filter_date">
		<?php
		// Convert string dates into actual dates
		foreach ( $date_filters as $date ) :
			if ( is_array( $date ) ) {
				$output = array_key_exists( 'value', $date ) ? $date['value'] : 0;
				$label  = array_key_exists( 'label', $date ) ? $date['label'] : $output;
			} else {
				$timestamp = strtotime( $date );
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$output = ( $timestamp ) ? date( 'Y-m-d', $timestamp ) : 0;
				$label  = $date_labels[ $date ] ?? $date;
			}

			printf( '<option value="%s" %s>%s</option>', esc_attr( $output ), selected( $output, $current_date, false ), esc_html( $label ) );
			endforeach;
		?>
	</select>
		<?php
	}

	public function render_search_form() {
		?>
	<div class="zone-search-wrapper">
		<label for="zone-post-search"><?php esc_html_e( 'Add content', 'zoninator' ); ?></label>
		<input type="text" id="zone-post-search" name="search" class="large-text" aria-describedby="zone-post-search-description" />
		<p class="description" id="zone-post-search-description"><?php esc_html_e( 'Type a title or post ID to search, or choose from the latest content.', 'zoninator' ); ?></p>
		<?php $this->render_search_filters(); ?>
	</div>
		<?php
	}

	public function is_current() {
		global $current_screen;

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
		}

		if ( empty( $screen ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == self::PAGE_SLUG;
		} else {
			return ! empty( $screen->id ) && strstr( $screen->id, (string) self::PAGE_SLUG );
		}
	}
}
