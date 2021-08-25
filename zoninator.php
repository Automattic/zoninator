<?php
/*
Plugin Name: Zone Manager (Zoninator)
Description: Curation made easy! Create "zones" then add and order your content!
Author: Mohammad Jangda, Automattic
Version: 0.8
Author URI: http://vip.wordpress.com
Text Domain: zoninator
Domain Path: /language/

Copyright 2010-2015 Mohammad Jangda, Automattic

This plugin was built by Mohammad Jangda in conjunction with William Davis and the Bangor Daily News.

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

if ( ! class_exists( 'Zoninator' ) ) :
	define( 'ZONINATOR_VERSION', '0.8' );
	define( 'ZONINATOR_PATH', dirname( __FILE__ ) );
	define( 'ZONINATOR_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

	require_once ZONINATOR_PATH . '/functions.php';
	require_once ZONINATOR_PATH . '/widget.zone-posts.php';

	/**
	 * Class Zoninator
	 */
	class Zoninator {

		/**
		 * Key
		 *
		 * @var string
		 */
		public $key = 'zoninator';

		/**
		 * Zone Taxonomy
		 *
		 * @var string
		 */
		public $zone_taxonomy = 'zoninator_zones';

		/**
		 * Zone term prefix
		 *
		 * @var string
		 */
		public $zone_term_prefix = 'zone-';

		/**
		 * Zone meta prefix
		 *
		 * @var string
		 */
		public $zone_meta_prefix = '_zoninator_order_';

		/**
		 * Zone nonce prefix
		 *
		 * @var string
		 */
		public $zone_nonce_prefix = 'zone-nonce';

		/**
		 * Ajax nonce action
		 *
		 * @var string
		 */
		public $zone_ajax_nonce_action = 'ajax-action';

		/**
		 * Number of seconds a lock is valid for
		 *
		 * @var int
		 */
		public $zone_lock_period = 30;

		/**
		 * Max number of seconds for all locks in a session
		 *
		 * @var int
		 */
		public $zone_max_lock_period = 600;

		/**
		 * Post types
		 *
		 * @var null
		 */
		public $post_types = null;

		/**
		 * Detail defaults
		 *
		 * @var string[]
		 */
		public $zone_detail_defaults = array(
			'description' => '',
		// Add additional properties here!
		);

		/**
		 * Zone messages
		 *
		 * @var null
		 */
		public $zone_messages = null;

		/**
		 * Default posts per page
		 *
		 * @var int
		 */
		public $posts_per_page = 10;

		/**
		 * REST API
		 *
		 * @var Zoninator_Api
		 */
		public $rest_api = null;

		/**
		 * Zoninator constructor.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ), 99 ); // init later after other post types have been registered.

			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

			add_action( 'widgets_init', array( $this, 'widgets_init' ) );

			add_action( 'init', array( $this, 'add_zone_feed' ) );

			add_action( 'template_redirect', array( $this, 'do_zoninator_feeds' ) );

			add_action( 'split_shared_term', array( $this, 'split_shared_term' ), 10, 4 );

			$this->maybe_add_rest_api();

			$this->default_post_types = array( 'post' );
		}

		/**
		 * Maybe load the REST API
		 *
		 * @return false
		 */
		public function maybe_add_rest_api() {
			global $wp_version;
			if ( version_compare( $wp_version, '4.7', '<' ) ) {
				return false;
			}

			include_once 'includes/class-zoninator-api.php';
			$this->rest_api = new Zoninator_Api( $this );
		}

		/**
		 * Add zone feeds
		 */
		public function add_zone_feed() {
			add_rewrite_tag( '%' . $this->zone_taxonomy . '%', '([^&]+)' );
			add_rewrite_rule( '^zones/([^/]+)/feed.json/?$', 'index.php?' . $this->zone_taxonomy . '=$matches[1]', 'top' );
		}

		/**
		 * Initialize Zoninator
		 */
		public function init() {
			$this->zone_messages = array(
				'insert-success'      => __( 'The zone was successfully created.', 'zoninator' ),
				'update-success'      => __( 'The zone was successfully updated.', 'zoninator' ),
				'delete-success'      => __( 'The zone was successfully deleted.', 'zoninator' ),
				'error-general'       => __( 'Sorry, something went wrong! Please try again?', 'zoninator' ),
				'error-zone-lock'     => __( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
				'error-zone-lock-max' => __( 'Sorry, you have reached the maximum idle limit and will now be redirected to the Dashboard.', 'zoninator' ),
			);

			$this->zone_lock_period     = apply_filters( 'zoninator_zone_lock_period', $this->zone_lock_period );
			$this->zone_max_lock_period = apply_filters( 'zoninator_zone_max_lock_period', $this->zone_max_lock_period );
			$this->posts_per_page       = apply_filters( 'zoninator_posts_per_page', $this->posts_per_page );

			do_action( 'zoninator_pre_init' );

			// Default post type support.
			foreach ( $this->default_post_types as $post_type ) {
				add_post_type_support( $post_type, $this->zone_taxonomy );
			}

			// Register taxonomy.
			if ( ! taxonomy_exists( $this->zone_taxonomy ) ) {
				register_taxonomy(
					$this->zone_taxonomy,
					$this->get_supported_post_types(),
					array(
						'label'        => __( 'Zones', 'zoninator' ),
						'hierarchical' => false,
						'query_var'    => false,
						'rewrite'      => false,
						'public'       => false,

					)
				);
			}

			add_action( 'admin_init', array( $this, 'admin_controller' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );

			add_action( 'admin_menu', array( $this, 'admin_page_init' ) );

			// Add default advanced search fields.
			add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_cat_filter' ) );
			add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_date_filter' ), 20 );

			do_action( 'zoninator_post_init' );
		}

		/**
		 * Load text domain
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'zoninator', false, basename( ZONINATOR_PATH ) . '/language' );
		}

		/**
		 * Initialize the widgets
		 */
		public function widgets_init() {
			register_widget( 'Zoninator_ZonePosts_Widget' );
		}

		/**
		 * Add the necessary admin AJAX actions
		 */
		public function admin_ajax_init() {
			add_action( 'wp_ajax_zoninator_reorder_posts', array( $this, 'ajax_reorder_posts' ) );
			add_action( 'wp_ajax_zoninator_add_post', array( $this, 'ajax_add_post' ) );
			add_action( 'wp_ajax_zoninator_remove_post', array( $this, 'ajax_remove_post' ) );
			add_action( 'wp_ajax_zoninator_search_posts', array( $this, 'ajax_search_posts' ) );
			add_action( 'wp_ajax_zoninator_update_lock', array( $this, 'ajax_update_lock' ) );
			add_action( 'wp_ajax_zoninator_update_recent', array( $this, 'ajax_recent_posts' ) );
		}

		/**
		 * Admin init actions
		 */
		public function admin_init() {
			$this->admin_ajax_init();

			// Enqueue Scripts and Styles.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );
		}

		/**
		 * Initialize the admin page
		 */
		public function admin_page_init() {
			// Set up page.
			add_menu_page( __( 'Zoninator', 'zoninator' ), __( 'Zones', 'zoninator' ), $this->get_manage_zones_cap(), $this->key, array( $this, 'admin_page' ), '', 11 );
		}

		/**
		 * Enqueue admin scripts
		 */
		public function admin_enqueue_scripts() {
			if ( $this->is_zoninator_page() ) {
				wp_enqueue_script( 'zoninator-js', ZONINATOR_URL . 'js/zoninator.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-sortable', 'jquery-ui-autocomplete' ), ZONINATOR_VERSION, true );

				$options = array(
					'baseUrl'           => $this->get_zone_page_url(),
					'adminUrl'          => admin_url(),
					'ajaxNonceAction'   => $this->get_nonce_key( $this->zone_ajax_nonce_action ),
					'errorGeneral'      => $this->get_message( 'error-general' ),
					'errorZoneLock'     => sprintf( $this->get_message( 'error-zone-lock' ), __( 'another user', 'zoninator' ) ),
					'errorZoneLockMax'  => $this->get_message( 'error-zone-lock-max' ),
					'zoneLockPeriod'    => $this->zone_lock_period,
					'zoneLockPeriodMax' => $this->zone_max_lock_period,
				);
				wp_localize_script( 'zoninator-js', 'zoninatorOptions', $options );

				// For mobile support
				// http://github.com/furf/jquery-ui-touch-punch.
				wp_enqueue_script( 'jquery-ui-touch-punch', ZONINATOR_URL . 'js/jquery.ui.touch-punch.min.js', array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse' ), ZONINATOR_VERSION, false );
			}
		}

		/**
		 * Enqueue admin styles
		 */
		public function admin_enqueue_styles() {
			if ( $this->is_zoninator_page() ) {
				wp_enqueue_style( 'zoninator-jquery-ui', ZONINATOR_URL . 'css/jquery-ui/smoothness/jquery-ui-zoninator.css', false, ZONINATOR_VERSION, 'all' );
				wp_enqueue_style( 'zoninator-styles', ZONINATOR_URL . 'css/zoninator.css', false, ZONINATOR_VERSION, 'all' );
			}
		}

		/**
		 * Admin Controller
		 */
		public function admin_controller() {
			if ( $this->is_zoninator_page() ) {
				$action = $this->get_request_var( 'action' );

				switch ( $action ) {
					case 'insert':
					case 'update':
						$zone_id = $this->get_post_var( 'zone_id', 0, 'absint' );

						$this->verify_nonce( $action );
						$this->verify_access( $action, $zone_id );

						$name    = $this->get_post_var( 'name', '', array( $this, 'sanitize_value' ) );
						$slug    = $this->get_post_var( 'slug', sanitize_title( $name ) );
						$details = array(
							'description' => $this->get_post_var( 'description', '', array( $this, 'sanitize_value' ) ),
						);

						// TODO: handle additional properties.
						if ( $zone_id ) {
							$result = $this->update_zone(
								$zone_id,
								array(
									'name'    => $name,
									'slug'    => $slug,
									'details' => $details,
								)
							);
						} else {
							$result = $this->insert_zone( $slug, $name, $details );
						}

						if ( is_wp_error( $result ) ) {
							wp_safe_redirect( add_query_arg( 'message', 'error-general' ) );
							exit;
						} else {
							if ( ! $zone_id && isset( $result['term_id'] ) ) {
								$zone_id = $result['term_id'];
							}

							// Redirect with success message.
							$message = sprintf( '%s-success', $action );
							wp_safe_redirect(
								$this->get_zone_page_url(
									array(
										'action'  => 'edit',
										'zone_id' => $zone_id,
										'message' => $message,
									)
								)
							);
							exit;
						}
						break;

					case 'delete':
						$zone_id = $this->get_request_var( 'zone_id', 0, 'absint' );

						$this->verify_nonce( $action );
						$this->verify_access( $action, $zone_id );

						if ( $zone_id ) {
							$result = $this->delete_zone( $zone_id );
						}

						if ( is_wp_error( $result ) ) {
							$redirect_args = array( 'error' => $result->get_error_messages() );
						} else {
							$redirect_args = array( 'message' => 'delete-success' );
						}

						wp_safe_redirect( $this->get_zone_page_url( $redirect_args ) );
						exit;
				}
			}
		}

		/**
		 * Admin page
		 */
		public function admin_page() {
			global $zoninator_admin_page;

			$view  = $this->get_value_or_default( 'view', $zoninator_admin_page, 'edit.php' );
			$view  = sprintf( '%s/views/%s', ZONINATOR_PATH, $view );
			$title = __( 'Zones', 'zoninator' );

			$zones = $this->get_zones( apply_filters( 'zoninator_admin_page_get_zones_args', array() ) );

			$default_active_zone = 0;
			if ( ! $this->current_user_can_add_zones() ) {
				if ( ! empty( $zones ) ) {
					$default_active_zone = $zones[0]->term_id;
				}
			}

			$active_zone_id = $this->get_request_var( 'zone_id', $default_active_zone, 'absint' );
			$active_zone    = ! empty( $active_zone_id ) ? $this->get_zone( $active_zone_id ) : array();
			if ( ! empty( $active_zone ) ) {
				$title = __( 'Edit Zone', 'zoninator' );
			}

			$message = $this->get_message( $this->get_get_var( 'message', '', 'urldecode' ) );
			$error   = $this->get_get_var( 'error', '', 'urldecode' );

			?>
		<div class="wrap zoninator-page">
			<div id="icon-edit-pages" class="icon32"><br /></div>
			<h2>
				<?php echo esc_html( $title ); ?>
				<?php
				if ( $this->current_user_can_add_zones() ) :
					$new_link = $this->get_zone_page_url( array( 'action' => 'new' ) );
					?>
					<?php if ( $active_zone_id ) : ?>
						<a href="<?php echo esc_url( $new_link ); ?>" class="add-new-h2 zone-button-add-new"><?php esc_html_e( 'Add New', 'zoninator' ); ?></a>
					<?php else : ?>
						<span class="nav-tab nav-tab-active zone-tab zone-tab-active"><?php esc_html_e( 'Add New', 'zoninator' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</h2>

				<?php if ( $message ) : ?>
				<div id="zone-message" class="updated below-h2">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>
				<?php if ( $error ) : ?>
				<div id="zone-message" class="error below-h2">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<div id="zoninator-wrap">

					<?php $this->admin_page_zone_tabs( $zones, $active_zone_id ); ?>
					<?php $this->admin_page_zone_edit( $active_zone ); ?>

			</div>
		</div>
			<?php
		}

		/**
		 * Tabs in the admin page
		 *
		 * @param array[] $zones Zones.
		 * @param int     $active_zone_id The active zone ID.
		 */
		public function admin_page_zone_tabs( $zones, $active_zone_id = 0 ) {
			$new_link = $this->get_zone_page_url( array( 'action' => 'new' ) );
			?>
		<div class="nav-tabs-container zone-tabs-container">
			<div class="nav-tabs-nav-wrapper zone-tabs-nav-wrapper">
				<div class="nav-tabs-wrapper zone-tabs-wrapper">
					<div class="nav-tabs zone-tabs">
						<?php foreach ( $zones as $zone ) : ?>
							<?php $zone_id = $this->get_zone_id( $zone ); ?>
							<?php
							$zone_link = $this->get_zone_page_url(
								array(
									'action'  => 'edit',
									'zone_id' => $zone_id,
								)
							);
							?>

							<?php if ( $active_zone_id && $zone_id == $active_zone_id ) : ?>
								<span class="nav-tab nav-tab-active zone-tab zone-tab-active"><?php echo esc_html( $zone->name ); ?></span>
							<?php else : ?>
								<a href="<?php echo esc_url( $zone_link ); ?>" class="nav-tab zone-tab"><?php echo esc_html( $zone->name ); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
			<?php
		}

		/**
		 * Edit zone (Admin Page)
		 *
		 * @param int|null $zone zone to edit.
		 */
		public function admin_page_zone_edit( $zone = null ) {
			$zone_id          = $this->get_value_or_default( 'term_id', $zone, 0, 'absint' );
			$zone_name        = $this->get_value_or_default( 'name', $zone );
			$zone_slug        = $this->get_value_or_default( 'slug', $zone, '', array( $this, 'get_unformatted_zone_slug' ) );
			$zone_description = $this->get_value_or_default( 'description', $zone );

			$zone_posts = $zone_id ? $this->get_zone_posts( $zone ) : array();

			$zone_locked = $this->is_zone_locked( $zone_id );

			$delete_link = $this->get_zone_page_url(
				array(
					'action'  => 'delete',
					'zone_id' => $zone_id,
				)
			);
			$delete_link = wp_nonce_url( $delete_link, $this->get_nonce_key( 'delete' ) );
			?>
		<div id="zone-edit-wrapper">
			<?php if ( ( 0 == $zone_id && $this->current_user_can_add_zones() ) || ( 0 != $zone_id && $this->current_user_can_manage_zones() ) ) : ?>
				<?php if ( $zone_locked ) : ?>
					<?php $locking_user = get_userdata( $zone_locked ); ?>
					<div class="updated below-h2">
						<p><?php echo sprintf( $this->get_message( 'error-zone-lock' ), sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $locking_user->user_email ), esc_html( $locking_user->display_name ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					</div>
					<input type="hidden" id="zone-locked" name="zone-locked" value="1" />
				<?php endif; ?>
				<div class="col-wrap zone-col zone-info-col">
					<div class="form-wrap zone-form zone-info-form">

						<?php if ( $this->current_user_can_edit_zones( $zone_id ) && ! $zone_locked ) : ?>

							<form id="zone-info" method="post">

								<?php do_action( 'zoninator_pre_zone_fields', $zone ); ?>

								<div class="form-field zone-field">
									<label for="zone-name"><?php esc_html_e( 'Name', 'zoninator' ); ?></label>
									<input type="text" id="zone-name" name="name" value="<?php echo esc_attr( $zone_name ); ?>" />
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
									<?php wp_nonce_field( $this->get_nonce_key( 'update' ) ); ?>
								<?php else : ?>
									<?php wp_nonce_field( $this->get_nonce_key( 'insert' ) ); ?>
								<?php endif; ?>

								<div class="submit-field submitbox">
									<input type="submit" value="<?php esc_attr_e( 'Save zone info', 'zoninator' ); ?>" name="submit" class="button" />

									<?php if ( $zone_id ) : ?>
										<a href="<?php echo esc_url( $delete_link ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( 'Are you sure you want to delete this zone?', 'zoninator' ); ?>')"><?php esc_html_e( 'Delete', 'zoninator' ); ?></a>
									<?php endif; ?>
								</div>

								<input type="hidden" name="action" value="<?php echo $zone_id ? 'update' : 'insert'; ?>">
								<input type="hidden" name="page" value="<?php echo esc_attr( $this->key ); ?>">

							</form>
						<?php else : ?>
							<div id="zone-info-readonly" class="readonly">
								<?php do_action( 'zoninator_pre_zone_readonly', $zone ); ?>

								<div class="form-field zone-field">
									<label for="zone-name"><?php esc_html_e( 'Name', 'zoninator' ); ?></label>
									<span><?php echo esc_html( $zone_name ); ?></span>
								</div>

								<!--
								<div class="form-field zone-field">
									<label for="zone-slug"><?php esc_html_e( 'Slug', 'zoninator' ); ?></label>
									<span><?php echo esc_html( $zone_slug ); ?></span>
								</div>
								-->

								<div class="form-field zone-field">
									<label for="zone-description"><?php esc_html_e( 'Description', 'zoninator' ); ?></label>
									<span><?php echo esc_html( $zone_description ); ?></span>
								</div>

								<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ); ?>" />

								<?php do_action( 'zoninator_post_zone_readonly', $zone ); ?>
							</div>
						<?php endif; ?>

						<?php // Ideally, we should seperate nonces for each action. But this will do for simplicity. ?>
						<?php wp_nonce_field( $this->get_nonce_key( $this->zone_ajax_nonce_action ), $this->get_nonce_key( $this->zone_ajax_nonce_action ), false ); ?>
					</div>

				</div>

				<div class="col-wrap zone-col zone-posts-col">
					<div class="zone-posts-wrapper <?php echo ! $this->current_user_can_manage_zones( $zone_id ) || $zone_locked ? 'readonly' : ''; ?>">
						<?php if ( $zone_id ) : ?>
							<h3><?php esc_html_e( 'Zone Content', 'zoninator' ); ?></h3>

							<?php $this->zone_advanced_search_filters(); ?>

							<?php $this->zone_admin_recent_posts_dropdown( $zone_id ); ?>

							<?php $this->zone_admin_search_form(); ?>

							<div class="zone-posts-save-input">
								<input type="button" value="Save zone posts" name="zone-posts-save" id="zone-posts-save" class="button-primary" />
								<p id="zone-posts-save-info" class="zone-posts-save-info"></p>
							</div>

							<div class="zone-posts-list">
								<?php foreach ( $zone_posts as $post ) : ?>
									<?php $this->admin_page_zone_post( $post, $zone ); ?>
								<?php endforeach; ?>
							</div>

						<?php else : ?>
							<p class="description"><?php esc_html_e( 'To create a zone, enter a name (and any other info) to to left and click "Save". You can then choose content items to add to the zone.', 'zoninator' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

			<?php
		}

		/**
		 * Admin Page Zone Post
		 *
		 * @param WP_Post $post Post.
		 * @param int     $zone Zone.
		 */
		public function admin_page_zone_post( $post, $zone ) {
			$columns = apply_filters(
				'zoninator_zone_post_columns',
				array(
					'position' => array( $this, 'admin_page_zone_post_col_position' ),
					'info'     => array( $this, 'admin_page_zone_post_col_info' ),
				),
				$post,
				$zone
			);
			?>
		<div id="zone-post-<?php echo (int) $post->ID; ?>" class="zone-post" data-post-id="<?php echo (int) $post->ID; ?>">
			<table>
				<tr>
					<?php foreach ( $columns as $column_key => $column_callback ) : ?>
						<?php if ( is_callable( $column_callback ) ) : ?>
							<td class="zone-post-col zone-post-<?php echo esc_attr( $column_key ); ?>">
								<?php call_user_func( $column_callback, $post, $zone ); ?>
							</td>
						<?php endif; ?>
					<?php endforeach; ?>
				</tr>
			</table>
			<input type="hidden" name="zone-post-id" value="<?php echo esc_attr( $post->ID ); ?>" />
		</div>
			<?php
		}

		/**
		 * Admin Page Zone Post Column Position
		 *
		 * @param WP_Post $post Post.
		 * @param int     $zone Zone.
		 */
		public function admin_page_zone_post_col_position( $post, $zone ) {
			$current_position = $this->get_post_order( $post->ID, $zone );
			?>
		<span title="<?php esc_attr_e( 'Click and drag to change the position of this item.', 'zoninator' ); ?>">
			<?php echo esc_html( $current_position ); ?>
		</span>
			<?php
		}

		/**
		 * Admin Page Zone Post Column Info
		 *
		 * @param WP_Post $post Post.
		 * @param int     $zone Zone.
		 */
		public function admin_page_zone_post_col_info( $post, $zone ) {
			$action_links = array(
				sprintf( '<a href="%s" class="edit" target="_blank" title="%s">%s</a>', get_edit_post_link( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'Edit', 'zoninator' ) ),
				sprintf( '<a href="#" class="delete" title="%s">%s</a>', __( 'Remove this item from the zone', 'zoninator' ), __( 'Remove', 'zoninator' ) ),
				sprintf( '<a href="%s" class="view" target="_blank" title="%s">%s</a>', get_permalink( $post->ID ), __( 'Opens in new window', 'zoninator' ), __( 'View', 'zoninator' ) ),
			// Move To.
			// Copy To.
			);
			?>
			<?php echo sprintf( '%s <span class="zone-post-status">(%s)</span>', esc_html( $post->post_title ), esc_html( $post->post_status ) ); ?>

		<div class="row-actions">
			<?php echo implode( ' | ', $action_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
			<?php
		}

		/**
		 * Zone advanced search filters
		 */
		public function zone_advanced_search_filters() {
			?>
		<div class="zone-advanced-search-filters-heading">
			<span class="zone-toggle-advanced-search" data-alt-label="<?php esc_attr_e( 'Hide', 'zoninator' ); ?>"><?php esc_html_e( 'Show Filters', 'zoninator' ); ?></span>
		</div>
		<div class="zone-advanced-search-filters-wrapper">
			<?php do_action( 'zoninator_advanced_search_fields' ); ?>
		</div>
			<?php
		}

		/**
		 * Zone advanced search category filter
		 */
		public function zone_advanced_search_cat_filter() {
			$current_cat = $this->get_post_var( 'zone_advanced_filter_taxonomy', '', 'absint' );
			?>
		<label for="zone_advanced_filter_taxonomy"><?php esc_html_e( 'Filter:', 'zoninator' ); ?></label>
			<?php
			wp_dropdown_categories(
				apply_filters(
					'zoninator_advanced_filter_category',
					array(
						'show_option_all' => __( 'Show all Categories', 'zoninator' ),
						'selected'        => $current_cat,
						'name'            => 'zone_advanced_filter_taxonomy',
						'id'              => 'zone_advanced_filter_taxonomy',
						'hide_if_empty'   => true,
					)
				)
			);
		}

		/**
		 * Zone advanced search date filter
		 */
		public function zone_advanced_search_date_filter() {
			$current_date = $this->get_post_var( 'zone_advanced_filter_date', apply_filters( 'zoninator_advanced_filter_date_default', '' ), 'striptags' );
			$date_filters = apply_filters( 'zoninator_advanced_filter_date', array( 'all', 'today', 'yesterday' ) );
			?>
		<select name="zone_advanced_filter_date" id="zone_advanced_filter_date">
			<?php
			// Convert string dates into actual dates.
			foreach ( $date_filters as $date ) :
				if ( true === is_array( $date ) ) {
					$output = array_key_exists( 'value', $date ) ? $date['value'] : 0;
					$label  = array_key_exists( 'label', $date ) ? $date['label'] : $output;
				} else {
					$timestamp = strtotime( $date );
					$output    = ( $timestamp ) ? gmdate( 'Y-m-d', $timestamp ) : 0;
					$label     = $date;
				}
				echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $output ), selected( $output, $current_date, false ), esc_html( $label ) );
				endforeach;
			?>
		</select>
			<?php
		}

		/**
		 * AJAX recent posts
		 */
		public function ajax_recent_posts() {
			$cat     = $this->get_post_var( 'cat', '', 'absint' );
			$date    = $this->get_post_var( 'date', '', 'striptags' );
			$zone_id = $this->get_post_var( 'zone_id', 0, 'absint' );

			$limit         = $this->posts_per_page;
			$post_types    = $this->get_supported_post_types();
			$zone_posts    = $this->get_zone_posts( $zone_id );
			$zone_post_ids = wp_list_pluck( $zone_posts, 'ID' );


			// Verify nonce.
			$this->verify_nonce( $this->zone_ajax_nonce_action );
			$this->verify_access( '', $zone_id );

			if ( is_wp_error( $zone_posts ) ) {
				$status  = 0;
				$content = $zone_posts->get_error_message();
			} else {
				$args = apply_filters(
					'zoninator_recent_posts_args',
					array(
						'posts_per_page'      => $limit,
						'order'               => 'DESC',
						'orderby'             => 'post_date',
						'post_type'           => $post_types,
						'ignore_sticky_posts' => true,
						'post_status'         => array( 'publish', 'future' ),
						'post__not_in'        => $zone_post_ids,
						'suppress_filters'    => false,
					)
				);

				if ( $this->validate_category_filter( $cat ) ) {
					$args['cat'] = $cat;
				}

				if ( $this->validate_date_filter( $date ) ) {
					$filter_date_parts = explode( '-', $date );
					$args['year']      = $filter_date_parts[0];
					$args['monthnum']  = $filter_date_parts[1];
					$args['day']       = $filter_date_parts[2];
				}

				$content      = '';
				$recent_posts = get_posts( $args );
				foreach ( $recent_posts as $post ) :
					$content .= sprintf( '<option value="%d">%s</option>', $post->ID, get_the_title( $post->ID ) . ' (' . $post->post_status . ')' );
				endforeach;
				wp_reset_postdata();
				$status = 1;
			}

			$empty_label = '';
			if ( ! $content ) {
				$empty_label = __( 'No results found', 'zoninator' );
			} elseif ( $cat ) {
				$empty_label = sprintf( __( 'Choose post from %s', 'zoninator' ), get_the_category_by_ID( $cat ) );
			} else {
				$empty_label = __( 'Choose a post', 'zoninator' );
			}

			$content = '<option value="">' . esc_html( $empty_label ) . '</option>' . $content;

			$this->ajax_return( $status, $content );
		}

		/**
		 * Zone Admin recent posts dropdown
		 *
		 * @param int $zone_id Zone ID.
		 */
		public function zone_admin_recent_posts_dropdown( $zone_id ) {
			$limit         = $this->posts_per_page;
			$post_types    = $this->get_supported_post_types();
			$zone_posts    = $this->get_zone_posts( $zone_id );
			$zone_post_ids = wp_list_pluck( $zone_posts, 'ID' );

			$args = apply_filters(
				'zoninator_recent_posts_args',
				array(
					'posts_per_page'      => $limit,
					'order'               => 'DESC',
					'orderby'             => 'post_date',
					'post_type'           => $post_types,
					'ignore_sticky_posts' => true,
					'post_status'         => array( 'publish', 'future' ),
					'post__not_in'        => $zone_post_ids,
					'suppress_filters'    => false,
				)
			);



			$recent_posts = get_posts( $args );
			?>
		<div class="zone-search-wrapper">
			<label for="zone-post-search-latest"><?php esc_html_e( 'Add Recent Content', 'zoninator' ); ?></label><br />
			<select name="search-posts" id="zone-post-latest">
				<option value=""><?php esc_html_e( 'Choose a post', 'zoninator' ); ?></option>
				<?php
				foreach ( $recent_posts as $post ) :
					echo sprintf( '<option value="%d">%s</option>', (int) $post->ID, esc_html( get_the_title( $post->ID ) . ' (' . $post->post_status . ')' ) );
					endforeach;
				wp_reset_postdata();
				?>
			</select>
		</div>
			<?php
		}

		/**
		 * Zone Admin search form
		 */
		public function zone_admin_search_form() {
			?>
		<div class="zone-search-wrapper">
			<label for="zone-post-search"><?php esc_html_e( 'Search for content', 'zoninator' ); ?></label>
			<input type="text" id="zone-post-search" name="search" />
			<p class="description"><?php esc_html_e( 'Enter a term or phrase in the text box above to search for and add content to this zone.', 'zoninator' ); ?></p>
		</div>
			<?php
		}

		/**
		 * Check if is a zoninator page
		 *
		 * @return bool
		 */
		public function is_zoninator_page() {
			global $current_screen;

			if ( function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
			}

			if ( empty( $screen ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == $this->key;
			} else {
				return ! empty( $screen->id ) && strstr( $screen->id, $this->key );
			}
		}

		/**
		 * Return AJAX content
		 *
		 * @param mixed  $status  Status.
		 * @param string $content Content.
		 * @param string $action  Action.
		 */
		public function ajax_return( $status, $content = '', $action = '' ) {
			$action = ! empty( $action ) ? $action : $this->zone_ajax_nonce_action;
			$nonce  = wp_create_nonce( $this->get_nonce_key( $action ) );

			echo wp_json_encode(
				array(
					'status'  => $status,
					'content' => $content,
					'nonce'   => $nonce,
				)
			);
			exit;
		}

		/**
		 * Add posts with AJAX
		 */
		public function ajax_add_post() {
			$zone_id = $this->get_post_var( 'zone_id', 0, 'absint' );
			$post_id = $this->get_post_var( 'post_id', 0, 'absint' );

			// Verify nonce.
			$this->verify_nonce( $this->zone_ajax_nonce_action );
			$this->verify_access( '', $zone_id );

			// Validate.
			if ( ! $zone_id || ! $post_id ) {
				$this->ajax_return( 0 );
			}

			$result = $this->add_zone_posts( $zone_id, $post_id, true );

			if ( is_wp_error( $result ) ) {
				$status  = 0;
				$content = $result->get_error_message();
			} else {
				$post = get_post( $post_id );
				$zone = $this->get_zone( $zone_id );

				ob_start();
				$this->admin_page_zone_post( $post, $zone );
				$content = ob_get_contents();
				ob_end_clean();

				$status = 1;
			}

			$this->ajax_return( $status, $content );
		}

		/**
		 * Remove post with AJAX
		 */
		public function ajax_remove_post() {
			$zone_id = $this->get_post_var( 'zone_id', 0, 'absint' );
			$post_id = $this->get_post_var( 'post_id', 0, 'absint' );

			// Verify nonce.
			$this->verify_nonce( $this->zone_ajax_nonce_action );
			$this->verify_access( '', $zone_id );

			// Validate.
			if ( ! $zone_id || ! $post_id ) {
				$this->ajax_return( 0 );
			}

			$result = $this->remove_zone_posts( $zone_id, $post_id );

			if ( is_wp_error( $result ) ) {
				$status  = 0;
				$content = $result->get_error_message();
			} else {
				$status  = 1;
				$content = '';
			}

			$this->ajax_return( $status, $content );
		}

		/**
		 * Reorder posts with AJAX
		 */
		public function ajax_reorder_posts() {
			$zone_id  = $this->get_post_var( 'zone_id', 0, 'absint' );
			$post_ids = (array) $this->get_post_var( 'posts', array(), 'absint' );

			// Verify nonce.
			$this->verify_nonce( $this->zone_ajax_nonce_action );
			$this->verify_access( '', $zone_id );

			// validate.
			if ( ! $zone_id || empty( $post_ids ) ) {
				$this->ajax_return( 0 );
			}

			$result = $this->add_zone_posts( $zone_id, $post_ids, false );

			if ( is_wp_error( $result ) ) {
				$status  = 0;
				$content = $result->get_error_message();
			} else {
				$status  = 1;
				$content = '';
			}

			$this->ajax_return( $status, $content );
		}

		/**
		 * Move zone from a zone to another zone with AJAX
		 *
		 * @param int $from_zone From zone.
		 * @param int $to_zone   To zone.
		 * @param int $post_id   Post ID.
		 */
		public function ajax_move_zone_post( $from_zone, $to_zone, $post_id ) {
			// TODO: implement in front-end.
			$from_zone_id = $this->get_post_var( 'from_zone_id', 0, 'absint' );
			$to_zone_id   = $this->get_post_var( 'to_zone_id', 0, 'absint' );

			$this->verify_nonce( $this->zone_ajax_nonce_action );

			// TODO: validate both zones exist, post exists.

			// Add to new zone.
			$this->add_zone_posts( $to_zone_id, $post_id, true );

			// Remove from old zone.
			$this->remove_zone_posts( $from_zone_id, $post_id );
		}

		/**
		 * Search Posts with AJAX
		 */
		public function ajax_search_posts() {
			$q = $this->get_request_var( 'term', '', 'stripslashes' );

			if ( ! empty( $q ) ) {
				$filter_cat  = $this->get_request_var( 'cat', '', 'absint' );
				$filter_date = $this->get_request_var( 'date', '', 'striptags' );

				$post_types = $this->get_supported_post_types();
				$limit      = $this->get_request_var( 'limit', $this->posts_per_page );
				if ( $limit <= 0 ) {
					$limit = $this->posts_per_page;
				}
				$exclude = (array) $this->get_request_var( 'exclude', array(), 'absint' );

				$args = apply_filters(
					'zoninator_search_args',
					array(
						's'                => $q,
						'post__not_in'     => $exclude,
						'posts_per_page'   => $limit,
						'post_type'        => $post_types,
						'post_status'      => array( 'publish', 'future' ),
						'order'            => 'DESC',
						'orderby'          => 'post_date',
						// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
						'suppress_filters' => true, // TODO: avoid suppressing filters?
						'no_found_rows'    => true,
					)
				);

				if ( $this->validate_category_filter( $filter_cat ) ) {
					$args['cat'] = $filter_cat;
				}

				if ( $this->validate_date_filter( $filter_date ) ) {
					$filter_date_parts = explode( '-', $filter_date );
					$args['year']      = $filter_date_parts[0];
					$args['monthnum']  = $filter_date_parts[1];
					$args['day']       = $filter_date_parts[2];
				}

				$query          = new WP_Query( $args );
				$stripped_posts = array();

				if ( ! $query->have_posts() ) {
					exit;
				}

				foreach ( $query->posts as $post ) {
					$stripped_posts[] = apply_filters(
						'zoninator_search_results_post',
						array(
							'title'       => ! empty( $post->post_title ) ? $post->post_title : __( '(no title)', 'zoninator' ),
							'post_id'     => $post->ID,
							'date'        => get_the_time( get_option( 'date_format' ), $post ),
							'post_type'   => $post->post_type,
							'post_status' => $post->post_status,
						),
						$post
					);
				}

				echo wp_json_encode( $stripped_posts );
				exit;
			}
		}

		/**
		 * Update zone lock with AJAX
		 */
		public function ajax_update_lock() {
			$zone_id = $this->get_post_var( 'zone_id', 0, 'absint' );

			$this->verify_nonce( $this->zone_ajax_nonce_action );
			$this->verify_access( '', $zone_id );

			if ( ! $zone_id ) {
				exit;
			}

			if ( ! $this->is_zone_locked( $zone_id ) ) {
				$this->lock_zone( $zone_id );
				$this->ajax_return( 1, '' );
			}
		}

		/**
		 * Get the supported post types
		 *
		 * @return array
		 */
		public function get_supported_post_types() {
			if ( isset( $this->post_types ) ) {
				return $this->post_types;
			}

			$this->post_types = array();

			foreach ( get_post_types() as $post_type ) {
				if ( post_type_supports( $post_type, $this->zone_taxonomy ) ) {
					array_push( $this->post_types, $post_type );
				}
			}

			return $this->post_types;
		}

		/**
		 * Registers a post type to be available for zones
		 *
		 * @param string|array $post_types A post type string or array of post type strings to register.
		 * @return bool True if any post types were added, false if not.
		 */
		public function register_zone_post_type( $post_types = '' ) {
			$did_register_post_types = false;

			if ( ! is_array( $post_types ) ) {
				$post_types = array( $post_types );
			}

			foreach ( $post_types as $post_type ) {
				if ( ! post_type_exists( $post_type ) ) {
					continue;
				}

				add_post_type_support( $post_type, $this->zone_taxonomy );
				register_taxonomy_for_object_type( $this->zone_taxonomy, $post_type );

				// Clear Zoninator supported post types cache.
				unset( $this->post_types );

				$did_register_post_types = true;
			}

			return $did_register_post_types;
		}

		/**
		 * Insert a new zone
		 *
		 * @param string $slug    Zone slug.
		 * @param string $name    Zone name.
		 * @param array  $details Details.
		 * @return array|int[]|WP_Error
		 */
		public function insert_zone( $slug, $name = '', $details = array() ) {

			// slug cannot be empty.
			if ( empty( $slug ) ) {
				return new WP_Error( 'zone-empty-slug', __( 'Slug is a required field.', 'zoninator' ) );
			}

			$slug = $this->get_formatted_zone_slug( $slug );
			$name = ! empty( $name ) ? $name : $slug;

			$details = wp_parse_args( $details, $this->zone_detail_defaults );
			$details = maybe_serialize( stripslashes_deep( $details ) );

			$args = array(
				'slug'        => $slug,
				'description' => $details,
			);

			// Filterize to allow other inputs.
			$args = apply_filters( 'zoninator_insert_zone', $args );

			return wp_insert_term( $name, $this->zone_taxonomy, $args );
		}

		/**
		 * Update zone
		 *
		 * @param int|WP_Term $zone Zone ID.
		 * @param array       $data New data.
		 * @return array|WP_Error|WP_Term|null
		 */
		public function update_zone( $zone, $data = array() ) {
			$zone_id = $this->get_zone_id( $zone );

			if ( $this->zone_exists( $zone_id ) ) {
				$zone = $this->get_zone( $zone );

				$name    = $this->get_value_or_default( 'name', $data, $zone->name );
				$slug    = $this->get_value_or_default( 'slug', $data, $zone->slug, array( $this, 'get_formatted_zone_slug' ) );
				$details = $this->get_value_or_default( 'details', $data, array() );

				// TODO: Back-fill current zone details.
				$details = wp_parse_args( $details, $this->zone_detail_defaults );
				$details = maybe_serialize( stripslashes_deep( $details ) );

				$args = array(
					'name'        => $name,
					'slug'        => $slug,
					'description' => $details,
				);

				// Filterize to allow other inputs.
				$args = apply_filters( 'zoninator_update_zone', $args, $zone_id, $zone );

				return wp_update_term( $zone_id, $this->zone_taxonomy, $args );
			}
			return new WP_Error( 'invalid-zone', __( 'Sorry, that zone doesn\'t exist.', 'zoninator' ) );
		}

		/**
		 * Delete zone
		 *
		 * @param int|WP_Term $zone Zone to be removed.
		 * @return array|bool|int|WP_Error|WP_Term
		 */
		public function delete_zone( $zone ) {
			$zone_id  = $this->get_zone_id( $zone );
			$meta_key = $this->get_zone_meta_key( $zone );

			$this->empty_zone_posts_cache( $meta_key );

			if ( $this->zone_exists( $zone_id ) ) {
				// Delete all post associations for the zone.
				$this->remove_zone_posts( $zone_id );

				// Delete the term.
				$delete = wp_delete_term( $zone_id, $this->zone_taxonomy );

				if ( ! $delete ) {
					return new WP_Error( 'delete-zone', __( 'Sorry, we couldn\'t delete the zone.', 'zoninator' ) );
				} else {
					do_action( 'zoninator_delete_zone', $zone_id );
					return $delete;
				}
			}
			return new WP_Error( 'invalid-zone', __( 'Sorry, that zone doesn\'t exist.', 'zoninator' ) );
		}

		/**
		 * Add posts to zone.
		 *
		 * @param int|WP_Term $zone   Zone.
		 * @param array       $posts  Array of posts.
		 * @param bool        $append If true, should append the post to the zone. If false, older posts will be removed from zone.
		 * @return bool|WP_Error
		 */
		public function add_zone_posts( $zone, $posts, $append = false ) {
			$zone     = $this->get_zone( $zone );
			$meta_key = $this->get_zone_meta_key( $zone );

			$this->empty_zone_posts_cache( $meta_key );

			if ( $append ) {
				// Order should be the highest post order.
				$last_post = $this->get_last_post_in_zone( $zone );
				if ( $last_post ) {
					$order = $this->get_post_order( $last_post, $zone );
				} else {
					$order = 0;
				}
			} else {
				$order = 0;
				$this->remove_zone_posts( $zone );
			}

			foreach ( (array) $posts as $post ) {
				$post_id = $this->get_post_id( $post );
				if ( $post_id ) {
					$order++;
					update_metadata( 'post', $post_id, $meta_key, $order, true );
				}
				// TODO: remove_object_terms -- but need remove object terms function :(.
			}

			clean_term_cache( $this->get_zone_id( $zone ), $this->zone_taxonomy ); // flush cache for our zone term and related APC caches.

			do_action( 'zoninator_add_zone_posts', $posts, $zone );

			return true;
		}

		/**
		 * Remove posts from zone.
		 *
		 * @param int|WP_Term $zone  Zone.
		 * @param array       $posts Array of posts.
		 */
		public function remove_zone_posts( $zone, $posts = null ) {
			$zone     = $this->get_zone( $zone );
			$meta_key = $this->get_zone_meta_key( $zone );

			$this->empty_zone_posts_cache( $meta_key );

			// if null, delete all.
			if ( ! $posts ) {
				$posts = $this->get_zone_posts( $zone );
			}

			foreach ( (array) $posts as $post ) {
				$post_id = $this->get_post_id( $post );
				if ( $post_id ) {
					delete_metadata( 'post', $post_id, $meta_key );
				}
			}

			clean_term_cache( $this->get_zone_id( $zone ), $this->zone_taxonomy ); // flush cache for our zone term and related APC caches.

			do_action( 'zoninator_remove_zone_posts', $posts, $zone );
		}

		/**
		 * Get posts from zone
		 *
		 * @param int|WP_Term $zone Zone.
		 * @param array       $args Search args.
		 * @return WP_Post[]
		 */
		public function get_zone_posts( $zone, $args = array() ) {
			// Check cache first.
			if ( $posts = $this->get_zone_posts_from_cache( $zone, $args ) ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
				return $posts;
			}

			$query = $this->get_zone_query( $zone, $args );
			$posts = $query->posts;

			// Add posts to cache.
			$this->add_zone_posts_to_cache( $posts, $zone, $args );

			return $posts;
		}

		/**
		 * Get zone query
		 *
		 * @param int|WP_Term $zone Zone.
		 * @param array       $args Search args.
		 * @return WP_Query
		 */
		public function get_zone_query( $zone, $args = array() ) {
			$meta_key = $this->get_zone_meta_key( $zone );

			$defaults = array(
				'order'               => 'ASC',
				'posts_per_page'      => -1, // TODO: avoid limitless query?
				'post_type'           => $this->get_supported_post_types(),
				'ignore_sticky_posts' => '1', // don't want sticky posts messing up our order.
			);

			// Default to published posts on the front-end.
			if ( ! is_admin() ) {
				$defaults['post_status'] = array( 'publish' );
			}

			if ( is_admin() ) { // skip APC in the admin.
				$defaults['suppress_filters'] = true; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
			}

			$args = wp_parse_args( $args, $defaults );

			// Un-overridable args.
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = $meta_key;

			return new WP_Query( $args );
		}

		/**
		 * Get last post in a Zone
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return false|WP_Post
		 */
		public function get_last_post_in_zone( $zone ) {
			return $this->get_single_post_in_zone(
				$zone,
				array(
					'order' => 'DESC',
				)
			);
		}

		/**
		 * Get first post in zone.
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return false|WP_Post
		 */
		public function get_first_post_in_zone( $zone ) {
			return $this->get_single_post_in_zone( $zone );
		}

		/**
		 * Get previous post in zone
		 *
		 * @param int|WP_Term $zone    Zone.
		 * @param int         $post_id the post ID.
		 * @return false|WP_Post
		 */
		public function get_prev_post_in_zone( $zone, $post_id ) {
			// TODO: test this works.
			$order = $this->get_post_order_in_zone( $zone, $post_id ); // TODO: remove? method don't exists.

			return $this->get_single_post_in_zone(
				$zone,
				array(
					'meta_value'   => $order,
					'meta_compare' => '<=',
				)
			);
		}

		/**
		 * Get next post in zone.
		 *
		 * @param int|WP_Term $zone    Zone.
		 * @param int         $post_id the post ID.
		 * @return false|WP_Post
		 */
		public function get_next_post_in_zone( $zone, $post_id ) {
			// TODO: test this works.
			$order = $this->get_post_order_in_zone( $zone, $post_id );  // TODO: remove? method don't exists.

			return $this->get_single_post_in_zone(
				$zone,
				array(
					'meta_value'   => $order,
					'meta_compare' => '>=',
				)
			);
		}

		/**
		 * Get single post in a zone.
		 *
		 * @param int|WP_Term $zone Zone.
		 * @param array       $args Search args.
		 * @return false|WP_Post
		 */
		public function get_single_post_in_zone( $zone, $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'posts_per_page' => 1,
					'showposts'      => 1,
				)
			);

			$post = $this->get_zone_posts( $zone, $args );

			if ( is_array( $post ) && ! empty( $post ) ) {
				return array_pop( $post );
			}

			return false;
		}

		/**
		 * Get zones for a post.
		 *
		 * @param int $post_id Post ID.
		 */
		public function get_zones_for_post( $post_id ) {
			// TODO: build this out.
			// phpcs:disable Squiz.Commenting,Squiz.PHP.CommentedOutCode
			// get_object_terms
			// get_terms

			// OR

			// get all meta_keys that match the prefix
			// strip the prefix

			// OR

			// get all zones and see if there's a matching meta entry
			// strip the prefix from keys

			// array_map( 'absint', $zone_ids )
			// $zones = array();
			// foreach( $zone_ids as $zone_id ) {
			// $zones[] = get_zone( $zone_id );
			// }
			// return $zones;
			// phpcs:enable Squiz.Commenting,Squiz.PHP.CommentedOutCode
		}

		/**
		 * Get zones
		 *
		 * @param array $args Search arguments.
		 * @return int[]|string|string[]|WP_Error|WP_Term[]
		 */
		public function get_zones( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'orderby'    => 'id',
					'order'      => 'ASC',
					'hide_empty' => 0,
				)
			);

			$zones = get_terms( $this->zone_taxonomy, $args );

			// Add extra fields in description as properties.
			foreach ( $zones as $zone ) {
				$zone = $this->fill_zone_details( $zone );
			}

			return $zones;
		}

		/**
		 * Get single zone.
		 *
		 * @param int|string|WP_Term $zone Zone to return.
		 * @return false|WP_Term
		 */
		public function get_zone( $zone ) {
			if ( is_int( $zone ) ) {
				$field = 'id';
			} elseif ( is_string( $zone ) ) {
				$field = 'slug';
				$zone  = $this->get_zone_slug( $zone );
			} elseif ( is_object( $zone ) ) {
				return $zone;
			} else {
				return false;
			}

			$zone = get_term_by( $field, $zone, $this->zone_taxonomy );

			if ( ! $zone ) {
				return false;
			}

			return $this->fill_zone_details( $zone );
		}

		/**
		 * Lock a zone
		 *
		 * @param int|WP_Term $zone    Zone to be locked.
		 * @param int         $user_id User ID locking the post.
		 * @return bool
		 */
		public function lock_zone( $zone, $user_id = 0 ) {
			$zone_id = $this->get_zone_id( $zone );

			if ( ! $zone_id ) {
				return false;
			}

			if ( ! $user_id ) {
				$user    = wp_get_current_user();
				$user_id = $user->ID;
			}

			$lock_key = $this->get_zone_meta_key( $zone );
			$expiry   = $this->zone_lock_period + 1; // Add a one to avoid most race condition issues between lock expiry and ajax call.
			set_transient( $lock_key, $user->ID, $expiry );

			// Possible alternative: set zone lock as property with time and user.
			return true;
		}


		/**
		 * Unlock a zone
		 *
		 * @param int|WP_Term $zone Zone to be unlocked.
		 * @return bool
		 */
		public function unlock_zone( $zone ) {
			// Not really needed with transients...
			$zone_id = $this->get_zone_id( $zone );

			if ( ! $zone_id ) {
				return false;
			}

			$lock_key = $this->get_zone_meta_key( $zone );

			delete_transient( $lock_key );
			return true;
		}

		/**
		 * Checks if a zone is locked.
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return false|int
		 */
		public function is_zone_locked( $zone ) {
			$zone_id = $this->get_zone_id( $zone );
			if ( ! $zone_id ) {
				return false;
			}

			$user     = wp_get_current_user();
			$lock_key = $this->get_zone_meta_key( $zone );

			$lock = get_transient( $lock_key );

			// If lock doesn't exist, or check if current user same as lock user.
			if ( ! $lock || absint( $lock ) === absint( $user->ID ) ) {
				return false;
			} else {          // return user_id of locking user.
				return absint( $lock );
			}
		}

		/**
		 * Checks is a zone exists
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return bool
		 */
		public function zone_exists( $zone ) {
			$zone_id = $this->get_zone_id( $zone );

			if ( function_exists( 'wpcom_vip_term_exists' ) ) {
				return wpcom_vip_term_exists( $zone_id, $this->zone_taxonomy ) == true;
			} else {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.term_exists_term_exists
				return term_exists( $zone_id, $this->zone_taxonomy ) == true;
			}
		}

		/**
		 * Gets a zone ID
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return int
		 */
		public function get_zone_id( $zone ) {
			if ( is_int( $zone ) ) {
				return $zone;
			}

			$zone = $this->get_zone( $zone );
			if ( is_object( $zone ) ) {
				$zone = $zone->term_id;
			}

			return (int) $zone;
		}

		/**
		 * Get zone meta key
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return string
		 */
		public function get_zone_meta_key( $zone ) {
			$zone_id = $this->get_zone_id( $zone );
			return $this->zone_meta_prefix . $zone_id;
		}

		/**
		 * Get zone slug
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return mixed
		 */
		public function get_zone_slug( $zone ) {
			if ( is_int( $zone ) ) {
				$zone = $this->get_zone( $zone );
			}

			if ( is_object( $zone ) ) {
				$zone = $zone->slug;
			}

			return $this->get_formatted_zone_slug( $zone );
		}

		/**
		 * Legacy function -- slugs can no longer be changed
		 *
		 * @param string $slug slug.
		 * @return string
		 */
		public function get_formatted_zone_slug( $slug ) {
			return $slug;
		}

		/**
		 * Legacy function -- slugs can no longer be changed
		 *
		 * @param string $slug slug.
		 * @return string
		 */
		public function get_unformatted_zone_slug( $slug ) {
			return $slug;
		}

		/**
		 * Get post ID
		 *
		 * @param int|WP_Post|array $post Post.
		 * @return false|int
		 */
		public function get_post_id( $post ) {
			if ( is_int( $post ) ) {
				return $post;
			} elseif ( is_array( $post ) ) {
				return absint( $post['ID'] );
			} elseif ( is_object( $post ) ) {
				return $post->ID;
			}

			return false;
		}

		/**
		 * Get post order
		 *
		 * @param int|WP_Post|array $post Post.
		 * @param int|WP_Term       $zone Zone.
		 * @return array|false|mixed
		 */
		public function get_post_order( $post, $zone ) {
			$post_id  = $this->get_post_id( $post );
			$meta_key = $this->get_zone_meta_key( $zone );

			return get_metadata( 'post', $post_id, $meta_key, true );
		}

		/**
		 * Verify nonce.
		 *
		 * @param string $action action name.
		 */
		public function verify_nonce( $action ) {
			$action = $this->get_nonce_key( $action );
			$nonce  = $this->get_request_var( $action );

			if ( empty( $nonce ) ) {
				$nonce = $this->get_request_var( '_wpnonce' );
			}

			if ( ! wp_verify_nonce( $nonce, $action ) ) {
				$this->unauthorized_access();
			}
		}

		/**
		 * Verify access
		 *
		 * @param string $action  action name.
		 * @param int    $zone_id zone ID.
		 */
		public function verify_access( $action = '', $zone_id = null ) {
			// TODO: should check if zone locked.

			$verify_function = '';
			switch ( $action ) {
				case 'insert':
					$verify_function = 'current_user_can_add_zones';
					break;
				case 'update':
				case 'delete':
					$verify_function = 'current_user_can_edit_zones';
					break;
				default:
					$verify_function = 'current_user_can_manage_zones';
					break;
			}

			if ( ! call_user_func( array( $this, $verify_function ), $zone_id ) ) {
				$this->unauthorized_access();
			}
		}

		/**
		 * Unauthorized access
		 */
		private function unauthorized_access() {
			wp_die( esc_html__( 'Sorry, you\'re not supposed to do that...', 'zoninator' ) );
		}

		/**
		 * Fill zone details
		 *
		 * @param int|WP_Term $zone Zone.
		 * @return mixed
		 */
		private function fill_zone_details( $zone ) {
			if ( ! empty( $zone->zoninator_parsed ) && $zone->zoninator_parsed ) {
				return $zone;
			}

			$details = array();

			if ( ! empty( $zone->description ) ) {
				$details = maybe_unserialize( $zone->description );
			}

			$details = wp_parse_args( $details, $this->zone_detail_defaults );

			foreach ( $details as $detail_key => $detail_value ) {
				$zone->$detail_key = $detail_value;
			}

			$zone->zoninator_parsed = true;

			return $zone;
		}

		/**
		 * Handle zoninator feeds
		 */
		public function do_zoninator_feeds() {
			$query_var = get_query_var( $this->zone_taxonomy );

			if ( ! empty( $query_var ) ) {
				$zone_slug = get_query_var( $this->zone_taxonomy );
				$results   = $this->get_zone_feed( $zone_slug );
				if ( is_wp_error( $results ) ) {
					$this->send_user_error( $results->get_error_message() );
				}
				$this->json_return( $results, false );
			}
		}

		/**
		 * Filter zone feed fields
		 *
		 * @param array $results results.
		 * @return array
		 */
		private function filter_zone_feed_fields( $results ) {
			$filtered_results   = array();
			$whitelisted_fields = apply_filters(
				'zoninator_zone_feed_fields',
				array( 'ID', 'post_date', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'guid' )
			);

			$filtered_results = array();
			$i                = 0;
			foreach ( $results as $result ) {
				if ( ! isset( $filtered_results[ $i ] ) ) {
					$filtered_results[ $i ] = new stdClass();
				}
				foreach ( $whitelisted_fields as $field ) {
					if ( ! isset( $filtered_results[ $i ] ) ) {
						$filtered_results[ $i ] = new stdClass();
					}
					$filtered_results[ $i ]->$field = $result->$field; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
				}
				$i++;
			}


			return $filtered_results;
		}

		/**
		 * Encode some data and echo it (possibly without cached headers)
		 *
		 * @param array $data data.
		 */
		private function json_return( $data ) {
			if ( null == $data ) {
				return false;
			}

			header( 'Content-Type: application/json' );
			echo wp_json_encode( $data );
			exit();
		}

		/**
		 * Send user error
		 *
		 * @param string $message Message.
		 */
		private static function send_user_error( $message ) {
			self::status_header_with_message( 406, $message );
			exit();
		}

		/**
		 * Modify the header and description in the global array
		 *
		 * @global array $wp_header_to_desc
		 * @param  int    $status  Status code.
		 * @param  string $message Message.
		 */
		private static function status_header_with_message( $status, $message ) {
			global $wp_header_to_desc;

			$status                       = absint( $status );
			$official_message             = isset( $wp_header_to_desc[ $status ] ) ? $wp_header_to_desc[ $status ] : '';
			$wp_header_to_desc[ $status ] = $message; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			status_header( $status );

			$wp_header_to_desc[ $status ] = $official_message; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}


		/**
		 * Get zone cache key (TODO: Implement)
		 *
		 * @param int|WP_Term $zone Zone.
		 * @param array       $args arguments.
		 * @return string
		 */
		private function get_zone_cache_key( $zone, $args = array() ) {
			return ''; // TODO: Caching needs to be testing properly before being implemented!
		}

		/**
		 * Get zone posts from cache (TODO: Implement)
		 *
		 * @param int|WP_Term $zone Zone.
		 * @param array       $args arguments.
		 * @return false
		 */
		private function get_zone_posts_from_cache( $zone, $args = array() ) {
			return false; // TODO: implement.
		}

		/**
		 * Add zone posts to cache (TODO: Implement)
		 *
		 * @param array       $posts Array of posts.
		 * @param int|WP_Term $zone  Zone.
		 * @param array       $args  arguments.
		 */
		private function add_zone_posts_to_cache( $posts, $zone, $args = array() ) {
			return; // TODO: implement.
		}

		/**
		 * Handle 4.2 term-splitting
		 *
		 * @param int             $old_term_id      old term id.
		 * @param int             $new_term_id      new term id.
		 * @param int             $term_taxonomy_id term taxonomy id.
		 * @param int|WP_Taxonomy $taxonomy         taxonomy.
		 */
		public function split_shared_term( $old_term_id, $new_term_id, $term_taxonomy_id, $taxonomy ) {
			if ( $this->zone_taxonomy === $taxonomy ) {
				do_action( 'zoninator_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );

				// Quick, easy switcheroo; add posts to new zone id and remove from the old one.
				$posts = $this->get_zone_posts( $old_term_id );
				if ( ! empty( $posts ) ) {
					$this->add_zone_posts( $new_term_id, $posts );
					$this->remove_zone_posts( $old_term_id );
				}

				do_action( 'zoninator_did_split_shared_term', $old_term_id, $new_term_id, $term_taxonomy_id );
			}
		}

		/**
		 * Empty zone post cache (TODO: Implement)
		 *
		 * @param string $meta_key meta key to empty.
		 */
		private function empty_zone_posts_cache( $meta_key ) {
			return; // TODO: implement.
		}

		/**
		 * Get message
		 *
		 * @param int  $message_id Message ID.
		 * @param bool $encode     Encode URL.
		 * @return string
		 */
		private function get_message( $message_id, $encode = false ) {
			$message = '';

			if ( ! empty( $this->zone_messages[ $message_id ] ) ) {
				$message = $this->zone_messages[ $message_id ];
			}

			if ( $encode ) {
				$message = urlencode( $message );
			}

			return $message;
		}

		/**
		 * Get nonce key
		 *
		 * @param string $action nonce action.
		 * @return string
		 */
		private function get_nonce_key( $action ) {
			return sprintf( '%s-%s', $this->zone_nonce_prefix, $action );
		}

		/**
		 * Validate if current user can add zones.
		 *
		 * @return bool
		 */
		private function current_user_can_add_zones() {
			return current_user_can( $this->get_add_zones_cap() );
		}

		/**
		 * Validate if current user can edit a specific zone.
		 *
		 * @param int $zone_id Zone to edit.
		 * @return mixed|void|null
		 */
		private function current_user_can_edit_zones( $zone_id ) {
			$has_cap = current_user_can( $this->get_edit_zones_cap() );
			return apply_filters( 'zoninator_current_user_can_edit_zone', $has_cap, $zone_id );
		}

		/**
		 * Validate if current user can manage zones.
		 *
		 * @return bool
		 */
		private function current_user_can_manage_zones() {
			return current_user_can( $this->get_manage_zones_cap() );
		}

		/**
		 * Get the capabilities required to add zones
		 *
		 * @return mixed|void|null
		 */
		private function get_add_zones_cap() {
			return apply_filters( 'zoninator_add_zone_cap', 'edit_others_posts' );
		}

		/**
		 * Get the capabilities required to edit zones.
		 *
		 * @return mixed|void|null
		 */
		private function get_edit_zones_cap() {
			return apply_filters( 'zoninator_edit_zone_cap', 'edit_others_posts' );
		}

		/**
		 * Get the capabilities required to manage zones.
		 *
		 * @return mixed|void|null
		 */
		private function get_manage_zones_cap() {
			return apply_filters( 'zoninator_manage_zone_cap', 'edit_others_posts' );
		}

		/**
		 * Get the Zone admin page URL
		 *
		 * @param array $args Extra URL parameters.
		 * @return string
		 */
		private function get_zone_page_url( $args = array() ) {
			$url = menu_page_url( $this->key, false );

			foreach ( $args as $arg_key => $arg_value ) {
				$url = add_query_arg( $arg_key, $arg_value, $url );
			}

			return $url;
		}

		/**
		 * Filter to validate a date
		 *
		 * @param string $date date.
		 * @return false|int
		 */
		private function validate_date_filter( $date ) {
			return preg_match( '/([0-9]{4})-([0-9]{2})-([0-9]{2})/', $date );
		}

		/**
		 * Filter to validate the category
		 *
		 * @param int $cat category ID.
		 * @return bool
		 */
		private function validate_category_filter( $cat ) {
			return $cat && get_term_by( 'id', $cat, 'category' );
		}

		/**
		 * Sanitize a value.
		 *
		 * @param string $var value to be sanitized.
		 * @return string
		 */
		private function sanitize_value( $var ) {
			return htmlentities( stripslashes( $var ) );
		}

		/**
		 * Get a value, or a default if it doesn't exist.
		 *
		 * @param string $var               value to retrieve.
		 * @param mixed  $object            object where it should be retrieved.
		 * @param string $default           default value to be returned in case $var doesn't exist.
		 * @param string $sanitize_callback sanitization function.
		 * @return false|mixed|string
		 */
		private function get_value_or_default( $var, $object, $default = '', $sanitize_callback = '' ) {
			if ( is_object( $object ) ) {
				$value = ! empty( $object->$var ) ? $object->$var : $default;
			} elseif ( is_array( $object ) ) {
				$value = ! empty( $object[ $var ] ) ? $object[ $var ] : $default;
			} else {
				$value = $default;
			}

			if ( is_callable( $sanitize_callback ) ) {
				if ( is_array( $value ) ) {
					$value = array_map( $sanitize_callback, $value );
				} else {
					$value = call_user_func( $sanitize_callback, $value );
				}
			}

			return $value;
		}

		/**
		 * Get a $_REQUEST variable.
		 *
		 * @param string $var               variable name.
		 * @param string $default           default value.
		 * @param string $sanitize_callback sanitization function.
		 * @return false|mixed|string
		 */
		private function get_request_var( $var, $default = '', $sanitize_callback = '' ) {
			return $this->get_value_or_default( $var, $_REQUEST, $default, $sanitize_callback ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Get a $_GET variable.
		 *
		 * @param string $var               variable name.
		 * @param string $default           default value.
		 * @param string $sanitize_callback sanitization function.
		 * @return false|mixed|string
		 */
		private function get_get_var( $var, $default = '', $sanitize_callback = '' ) {
			return $this->get_value_or_default( $var, $_GET, $default, $sanitize_callback ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		/**
		 * Get a $_POST variable.
		 *
		 * @param string $var               variable name.
		 * @param string $default           default value.
		 * @param string $sanitize_callback sanitization function.
		 * @return false|mixed|string
		 */
		private function get_post_var( $var, $default = '', $sanitize_callback = '' ) {
			return $this->get_value_or_default( $var, $_POST, $default, $sanitize_callback ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		/**
		 * Get admin zone post
		 *
		 * @param WP_Post     $post Post.
		 * @param int|WP_Term $zone Zone.
		 * @return mixed|void|null
		 */
		public function get_admin_zone_post( $post, $zone ) {
			return apply_filters(
				'zoninator_zone_post_columns',
				array(
					'post_id'  => $post->ID,
					'position' => array(
						'current_position'        => intval( $this->get_post_order( $post->ID, $zone ) ),
						'change_position_message' => esc_attr__( 'Click and drag to change the position of this item.', 'zoninator' ),
						'key'                     => 'position',
					),
					'info'     => array(
						'key'              => 'info',
						'post'             => array(
							'post_title'  => esc_html( $post->post_title ),
							'post_status' => esc_html( $post->post_status ),
						),
						'action_link_data' => array(
							array(
								'action' => 'edit',
								'anchor' => get_edit_post_link( $post->ID ),
								'title'  => __( 'Opens in new window', 'zoninator' ),
								'text'   => __( 'Edit', 'zoninator' ),
								'target' => '_blank',
							),
							array(
								'action' => 'delete',
								'anchor' => '#',
								'title'  => '',
								'text'   => __( 'Remove', 'zoninator' ),
							),
							array(
								'action' => 'view',
								'anchor' => get_permalink( $post->ID ),
								'title'  => __( 'Opens in new window', 'zoninator' ),
								'text'   => __( 'View', 'zoninator' ),
								'target' => '_blank',
							),
						),
					),
				),
				$post,
				$zone
			);
		}

		/**
		 * Get admin zone posts
		 *
		 * @param int|WP_Term $zone_or_id Zone.
		 * @return array
		 */
		public function get_admin_zone_posts( $zone_or_id ) {
			$zone             = $this->get_zone( $zone_or_id );
			$posts            = $this->get_zone_posts( $zone );
			$admin_zone_posts = array();

			foreach ( $posts as $post ) {
				$admin_zone_posts[] = $this->get_admin_zone_post( $post, $zone );
			}

			return $admin_zone_posts;
		}

		/**
		 * Get zone feed
		 *
		 * @param int|string $zone_slug_or_id Zone Slug or zone ID.
		 * @return array|WP_Error
		 */
		public function get_zone_feed( $zone_slug_or_id ) {
			$zone_id = $this->get_zone( $zone_slug_or_id );

			if ( empty( $zone_id ) ) {
				return new WP_Error( 'invalid-zone-supplied', __( 'Invalid zone supplied', 'zoninator' ) );
			}

			$results          = $this->get_zone_posts( $zone_id, apply_filters( 'zoninator_json_feed_fields', array(), $zone_slug_or_id ) );
			$filtered_results = $this->filter_zone_feed_fields( $results );

			return apply_filters( 'zoninator_json_feed_results', $filtered_results, $zone_slug_or_id );
		}

		/**
		 * Check zone
		 *
		 * @param string $action  Action.
		 * @param int    $zone_id Zone ID.
		 * @return bool|mixed|void|null
		 */
		public function check( $action = '', $zone_id = null ) {
			// TODO: should check if zone locked.
			if ( 'insert' == $action ) {
				return $this->current_user_can_add_zones();
			}

			if ( 'update' == $action || 'delete' == $action ) {
				return $this->current_user_can_edit_zones( $zone_id );
			}

			return $this->current_user_can_manage_zones();
		}
	}

	/**
	 * Returns Zoninator instance
	 *
	 * @return Zoninator
	 */
	function Zoninator() {  // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		global $zoninator;
		if ( ! isset( $zoninator ) || null === $zoninator ) {
			$zoninator = new Zoninator();
		}
		return $zoninator;
	}

	Zoninator();
endif;
