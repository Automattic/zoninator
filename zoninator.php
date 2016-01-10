<?php
/*
Plugin Name: Zone Manager (Zoninator)
Description: Curation made easy! Create "zones" then add and order your content!
Author: Mohammad Jangda, Automattic
Version: 0.7
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

if( ! class_exists( 'Zoninator' ) ) :

define( 'ZONINATOR_VERSION', '0.7' );
define( 'ZONINATOR_PATH', dirname( __FILE__ ) );
define( 'ZONINATOR_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

require_once( ZONINATOR_PATH . '/functions.php' );
require_once( ZONINATOR_PATH . '/widget.zone-posts.php' );
require_once( ZONINATOR_PATH . '/class-zoninator-constants.php' );
require_once( ZONINATOR_PATH . '/class-zoninator-zone-gateway.php' );
require_once( ZONINATOR_PATH . '/class-zoninator-permissions.php' );
require_once( ZONINATOR_PATH . '/class-zoninator-view-renderer.php' );
require_once( ZONINATOR_PATH . '/class-zoninator-rest-api-controller.php' );

class Zoninator
{
	public $zone_detail_defaults = array(
		'description' => ''
		// Add additional properties here!
	);

	public $zone_messages = null;

	/**
	 * @var null|Zoninator_Zone_Gateway
	 */
	public $zone_gateway = null;

	/**
	 * @var null|Zoninator_Rest_Api_Controller
	 */
	public $rest_api_controller = null;

	/**
	 * @var null|Zoninator_Permissions
	 */
	public $permissions = null;

	function __construct() {
		$this->zone_gateway = new Zoninator_Zone_Gateway();
		$this->permissions = new Zoninator_Permissions();
		$this->_renderer = new Zoninator_View_Renderer( $this->zone_gateway );
		$this->rest_api_controller = new Zoninator_Rest_Api_Controller( $this->zone_gateway, $this->permissions, $this->_renderer );

		add_action( 'init', array( $this, 'init' ), 99 ); // init later after other post types have been registered

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'widgets_init', array( $this, 'widgets_init' ) );

		add_action( 'init', array( $this, 'add_zone_feed' ) );

		add_action( 'template_redirect', array( $this, 'do_zoninator_feeds' ) );

		$this->default_post_types = array( 'post' );
	}

    function get_zone_gateway() {
        return $this->zone_gateway;
    }

	function add_zone_feed() {
		add_rewrite_tag( '%' . Zoninator_Constants::ZONE_TAXONOMY . '%', '([^&]+)' );
		add_rewrite_rule( '^zones/([^/]+)/feed.json/?$', 'index.php?' . Zoninator_Constants::ZONE_TAXONOMY . '=$matches[1]', 'top' );
	}

	function init() {
		$this->zone_messages = array(
			'insert-success' => __( 'The zone was successfully created.', 'zoninator' ),
			'update-success' => __( 'The zone was successfully updated.', 'zoninator' ),
			'delete-success' => __( 'The zone was successfully deleted.', 'zoninator' ),
			'error-general' => __( 'Sorry, something went wrong! Please try again?', 'zoninator' ),
			'error-zone-lock' => __( 'Sorry, this zone is in use by %s and is currently locked. Please try again later.', 'zoninator' ),
			'error-zone-lock-max' => __( 'Sorry, you have reached the maximum idle limit and will now be redirected to the Dashboard.', 'zoninator' ),
		);

		do_action( 'zoninator_pre_init' );

		$this->zone_gateway->init();

		add_action( 'admin_init', array( $this, 'admin_controller' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'admin_menu', array( $this, 'admin_page_init' ) );

		# Add default advanced search fields
		add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_cat_filter' ) );
		add_action( 'zoninator_advanced_search_fields', array( $this, 'zone_advanced_search_date_filter' ), 20 );

		add_action( 'rest_api_init', array( $this->rest_api_controller, 'register_routes' ) );

		do_action( 'zoninator_post_init' );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'zoninator', false, basename( ZONINATOR_PATH ) . '/language' );
	}

	function widgets_init() {
		register_widget( 'Zoninator_ZonePosts_Widget' );
	}

	function admin_init() {
		// Enqueue Scripts and Styles
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ) );
	}

	function admin_page_init() {
		// Set up page
		add_menu_page( __( 'Zoninator', 'zoninator' ), __( 'Zones', 'zoninator' ), $this->permissions->get_manage_zones_cap(), Zoninator_Constants::KEY, array( $this, 'admin_page' ), '', 11 );
	}

	private function _get_rest_api_base_url() {
		$namespace = 'zoninator/v1';
		if ( is_multisite() ) {
			return get_rest_url(get_current_blog_id(), $namespace);
		}
		return get_rest_url(null, $namespace);
	}

	function admin_enqueue_scripts() {
		if( $this->is_zoninator_page() ) {
			wp_enqueue_script( 'zoninator-js', ZONINATOR_URL . 'js/zoninator.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-position', 'jquery-ui-sortable', 'jquery-ui-autocomplete' ), ZONINATOR_VERSION, true );

			$options = array(
				'baseUrl' => $this->_get_zone_page_url(),
				'restApiUrl' => $this->_get_rest_api_base_url(),
				'restApiNonce' => wp_create_nonce( 'wp_rest' ),
				'adminUrl' => admin_url(),
				'ajaxNonceAction' => $this->_get_nonce_key( Zoninator_Constants::ZONE_AJAX_NONCE_ACTION ),
				'errorGeneral' => $this->_get_message( 'error-general' ),
				'errorZoneLock' => sprintf( $this->_get_message( 'error-zone-lock' ), __( 'another user', 'zoninator' ) ),
				'errorZoneLockMax' => $this->_get_message( 'error-zone-lock-max' ),
				'zoneLockPeriod' => $this->zone_gateway->zone_lock_period,
				'zoneLockPeriodMax' => $this->zone_gateway->zone_max_lock_period,
			);
			wp_localize_script( 'zoninator-js', 'zoninatorOptions', $options );

			// For mobile support
			// http://github.com/furf/jquery-ui-touch-punch
			wp_enqueue_script( 'jquery-ui-touch-punch', ZONINATOR_URL . 'js/jquery.ui.touch-punch.min.js', array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse' ) );

		}
	}

	function admin_enqueue_styles() {
		if( $this->is_zoninator_page() ) {
			wp_enqueue_style( 'zoninator-jquery-ui', ZONINATOR_URL . 'css/jquery-ui/smoothness/jquery-ui-zoninator.css', false, ZONINATOR_VERSION, 'all' );
			wp_enqueue_style( 'zoninator-styles', ZONINATOR_URL . 'css/zoninator.css', false, ZONINATOR_VERSION, 'all' );
		}
	}

	function admin_controller() {
		if( $this->is_zoninator_page() ) {
			$action = $this->_get_request_var( 'action' );

			switch( $action ) {

				case 'insert':
				case 'update':
					$zone_id = $this->_get_post_var( 'zone_id', 0, 'absint' );

					$this->verify_nonce( $action );
					$this->verify_access( $action, $zone_id );

					$name = $this->_get_post_var( 'name' );
					$slug = $this->_get_post_var( 'slug', sanitize_title( $name ) );
					$details = array(
						'description' => $this->_get_post_var( 'description', '', 'strip_tags' )
					);

					// TODO: handle additional properties
					if( $zone_id ) {
						$result = $this->zone_gateway->update_zone( $zone_id, array(
							'name' => $name,
							'slug' => $slug,
							'details' => $details
						) );

					} else {
						$result = $this->zone_gateway->insert_zone( $slug, $name, $details );
					}

					if( is_wp_error( $result ) ) {
						wp_redirect( add_query_arg( 'message', 'error-general' ) );
						exit;
					} else {
						if( ! $zone_id && isset( $result['term_id'] ) )
							$zone_id = $result['term_id'];

						// Redirect with success message
						$message = sprintf( '%s-success', $action );
						wp_redirect( $this->_get_zone_page_url( array( 'action' => 'edit', 'zone_id' => $zone_id, 'message' => $message ) ) );
						exit;
					}
					break;

				case 'delete':
					$zone_id = $this->_get_request_var( 'zone_id', 0, 'absint' );

					$this->verify_nonce( $action );
					$this->verify_access( $action, $zone_id );

					if( $zone_id ) {
						$result = $this->zone_gateway->delete_zone( $zone_id );
					} else {
						//TODO 404
					}

					if( is_wp_error( $result ) ) {
						$redirect_args = array( 'error' => $result->get_error_messages() );
					} else {
						$redirect_args = array( 'message' => 'delete-success' );
					}

					wp_redirect( $this->_get_zone_page_url( $redirect_args ) );
					exit;
			}
		}
	}

	function admin_page() {
		global $zoninator_admin_page;

		$view = $this->_get_value_or_default( 'view', $zoninator_admin_page, 'edit.php' );
		$view = sprintf( '%s/views/%s', ZONINATOR_PATH, $view );
		$title = __( 'Zones', 'zoninator' );

		$zones = $this->zone_gateway->get_zones();

		$default_active_zone = 0;
		if( ! $this->permissions->current_user_can_add_zones() ) {
			if( ! empty( $zones ) )
				$default_active_zone = $zones[0]->term_id;
		}

		$active_zone_id = $this->_get_request_var( 'zone_id', $default_active_zone, 'absint' );
		$active_zone = ! empty( $active_zone_id ) ? $this->zone_gateway->get_zone( $active_zone_id ) : array();
		if ( ! empty( $active_zone ) )
			$title = __( 'Edit Zone', 'zoninator' );

		$message = $this->_get_message( $this->_get_get_var( 'message', '', 'urldecode' ) );
		$error = $this->_get_get_var( 'error', '', 'urldecode' );

		?>
		<div class="wrap zoninator-page">
			<div id="icon-edit-pages" class="icon32"><br /></div>
			<h2>
				<?php echo esc_html( $title ); ?>
				<?php if( $this->permissions->current_user_can_add_zones() ) :
					$new_link = $this->_get_zone_page_url( array( 'action' => 'new' ) ); ?>
					<?php if( $active_zone_id ) : ?>
						<a href="<?php echo esc_url( $new_link ); ?>" class="add-new-h2 zone-button-add-new"><?php esc_html_e( 'Add New', 'zoninator' ); ?></a>
					<?php else : ?>
						<span class="nav-tab nav-tab-active zone-tab zone-tab-active"><?php esc_html_e( 'Add New', 'zoninator' ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</h2>

			<?php if( $message ) : ?>
				<div id="zone-message" class="updated below-h2">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>
			<?php if( $error ) : ?>
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

	function admin_page_zone_tabs( $zones, $active_zone_id = 0 ) {
//		$new_link = $this->_get_zone_page_url( array( 'action' => 'new' ) );
		?>
		<div class="nav-tabs-container zone-tabs-container">
			<div class="nav-tabs-nav-wrapper zone-tabs-nav-wrapper">
				<div class="nav-tabs-wrapper zone-tabs-wrapper">
					<div class="nav-tabs zone-tabs">
						<?php foreach( $zones as $zone ) : ?>
							<?php $zone_id = $this->zone_gateway->get_zone_id( $zone ); ?>
							<?php $zone_link = $this->_get_zone_page_url( array( 'action' => 'edit', 'zone_id' => $zone_id ) ); ?>

							<?php if( $active_zone_id && $zone_id == $active_zone_id ) : ?>
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

	function admin_page_zone_edit( $zone = null ) {
		$zone_id = $this->_get_value_or_default( 'term_id', $zone, 0, 'absint' );
		$zone_name = $this->_get_value_or_default( 'name', $zone );
		$zone_slug = $this->_get_value_or_default( 'slug', $zone, '', array( $this, 'get_unformatted_zone_slug' ) );
		$zone_description = $this->_get_value_or_default( 'description', $zone );

		$zone_posts = $zone_id ? $this->zone_gateway->get_zone_posts( $zone ) : array();

		$zone_locked = $this->zone_gateway->is_zone_locked( $zone_id );

		$delete_link = $this->_get_zone_page_url( array( 'action' => 'delete', 'zone_id' => $zone_id ) );
		$delete_link = wp_nonce_url( $delete_link, $this->_get_nonce_key( 'delete' ) );
		?>
		<div id="zone-edit-wrapper">
			<?php if( ( $zone_id == 0 && $this->permissions->current_user_can_add_zones() ) || ( $zone_id != 0 && $this->permissions->current_user_can_manage_zones() ) ) : ?>
				<?php if( $zone_locked ) : ?>
					<?php $locking_user = get_userdata( $zone_locked ); ?>
					<div class="updated below-h2">
						<p><?php echo sprintf( $this->_get_message( 'error-zone-lock' ), sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $locking_user->user_email ), esc_html( $locking_user->display_name ) ) ); ?></p>
					</div>
					<input type="hidden" id="zone-locked" name="zone-locked" value="1" />
				<?php endif; ?>
				<div class="col-wrap zone-col zone-info-col">
					<div class="form-wrap zone-form zone-info-form">

						<?php if ( $this->permissions->current_user_can_edit_zones( $zone_id ) && ! $zone_locked ) : ?>

							<form id="zone-info" method="post">

								<?php do_action( 'zoninator_pre_zone_fields', $zone ); ?>

								<div class="form-field zone-field">
									<label for="zone-name"><?php esc_html_e( 'Name', 'zoninator' ); ?></label>
									<input type="text" id="zone-name" name="name" value="<?php echo esc_attr( $zone_name ); ?>" />
								</div>

								<?php if( $zone_id ) : ?>
								<div class="form-field zone-field">
									<label for="zone-slug"><?php esc_html_e( 'Slug', 'zoninator' ); ?></label>
									<span><?php echo esc_attr( $zone_slug ); ?></span>
									<input type="hidden" id="zone-slug" name="slug" value="<?php echo esc_attr( $zone_slug ); ?>" />
								</div>
								<?php endif; ?>

								<div class="form-field zone-field">
									<label for="zone-description"><?php esc_html_e( 'Description', 'zoninator' ); ?></label>
									<textarea id="zone-description" name="description"><?php echo esc_html( $zone_description ); ?></textarea>
								</div>

								<?php do_action( 'zoninator_post_zone_fields', $zone ); ?>

								<?php if( $zone_id ) : ?>
									<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ) ?>" />
									<?php wp_nonce_field( $this->_get_nonce_key( 'update' ) ); ?>
								<?php else : ?>
									<?php wp_nonce_field( $this->_get_nonce_key( 'insert' ) ); ?>
								<?php endif; ?>

								<div class="submit-field submitbox">
									<input type="submit" value="<?php esc_attr_e('Save', 'zoninator'); ?>" name="submit" class="button-primary" />

									<?php if( $zone_id ) : ?>
										<a href="<?php echo $delete_link ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this zone?', 'zoninator' ) ); ?>')"><?php esc_html_e('Delete', 'zoninator') ?></a>
									<?php endif; ?>
								</div>

								<input type="hidden" name="action" value="<?php echo $zone_id ? 'update' : 'insert'; ?>">
								<input type="hidden" name="page" value="<?php echo Zoninator_Constants::KEY; ?>">

							</form>
						<?php else : ?>
							<div id="zone-info-readonly" class="readonly">
								<?php do_action( 'zoninator_pre_zone_readonly', $zone ); ?>

								<div class="form-field zone-field">
									<label for="zone-name"><?php esc_html_e( 'Name', 'zoninator' ); ?></label>
									<span><?php echo esc_attr( $zone_name ); ?></span>
								</div>

								<!--
								<div class="form-field zone-field">
									<label for="zone-slug"><?php esc_html_e( 'Slug', 'zoninator' ); ?></label>
									<span><?php echo esc_attr( $zone_slug ); ?></span>
								</div>
								-->

								<div class="form-field zone-field">
									<label for="zone-description"><?php esc_html_e( 'Description', 'zoninator' ); ?></label>
									<span><?php echo esc_html( $zone_description ); ?></span>
								</div>

								<input type="hidden" name="zone_id" id="zone_id" value="<?php echo esc_attr( $zone_id ) ?>" />

								<?php do_action( 'zoninator_post_zone_readonly', $zone ); ?>
							</div>
						<?php endif; ?>

						<?php // Ideally, we should seperate nonces for each action. But this will do for simplicity. ?>
						<?php wp_nonce_field( $this->_get_nonce_key( Zoninator_Constants::ZONE_AJAX_NONCE_ACTION ), $this->_get_nonce_key( Zoninator_Constants::ZONE_AJAX_NONCE_ACTION ), false ); ?>
					</div>

				</div>

				<div class="col-wrap zone-col zone-posts-col">
					<div class="zone-posts-wrapper <?php echo ! $this->permissions->current_user_can_manage_zones() || $zone_locked ? 'readonly' : ''; ?>">
						<?php if( $zone_id ) : ?>
							<h3><?php esc_html_e( 'Zone Content', 'zoninator' ); ?></h3>

							<?php $this->_renderer->zone_advanced_search_filters(); ?>

							<?php $this->zone_admin_recent_posts_dropdown( $zone_id ); ?>

							<?php $this->_renderer->zone_admin_search_form(); ?>

							<div class="zone-posts-list">
								<?php foreach( $zone_posts as $post ) : ?>
									<?php $this->_renderer->admin_page_zone_post( $post, $zone ); ?>
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

	function zone_advanced_search_cat_filter() {
		$current_cat = $this->_get_post_var( 'zone_advanced_filter_taxonomy', '', 'absint' );
		?>
		<label for="zone_advanced_filter_taxonomy"><?php esc_html_e( 'Filter:', 'zoninator' ); ?></label>
		<?php
		wp_dropdown_categories( apply_filters( 'zoninator_advanced_filter_category', array(
			'show_option_all' =>  __( 'Show all Categories', 'zoninator' ),
			'selected' => $current_cat,
			'name' => 'zone_advanced_filter_taxonomy',
			'id' => 'zone_advanced_filter_taxonomy',
			'hide_if_empty' => true,
		) ) );
	}

	function zone_advanced_search_date_filter() {
		$current_date = $this->_get_post_var( 'zone_advanced_filter_date', '', 'striptags' );
		$date_filters = apply_filters( 'zoninator_advanced_filter_date', array( 'all', 'today', 'yesterday') );
		?>
		<select name="zone_advanced_filter_date" id="zone_advanced_filter_date">
			<?php
			// Convert string dates into actual dates
			foreach( $date_filters as $date ) :
				$timestamp = strtotime( $date );
				$output = ( $timestamp ) ? date( 'Y-m-d', $timestamp ) : 0;
				echo sprintf( '<option value="%s" %s>%s</option>', esc_attr( $output ), selected( $output, $current_date, false ), esc_html( $date ) );
			endforeach;
			?>
		</select>
		<?php
	}

	function zone_admin_recent_posts_dropdown( $zone_id ) {

		$limit = $this->zone_gateway->posts_per_page;
		$post_types = $this->zone_gateway->get_supported_post_types();
		$zone_posts = $this->zone_gateway->get_zone_posts( $zone_id );
		$zone_post_ids = wp_list_pluck( $zone_posts, 'ID' );

		$args = apply_filters( 'zoninator_recent_posts_args', array(
			'posts_per_page' => $limit,
			'order' => 'DESC',
			'orderby' => 'post_date',
			'post_type' => $post_types,
			'ignore_sticky_posts' => true,
			'post_status' => array( 'publish', 'future' ),
			'post__not_in' => $zone_post_ids,
		) );



		$recent_posts = get_posts( $args );
		?>
		<div class="zone-search-wrapper">
			<label for="zone-post-search-latest"><?php esc_html_e( 'Add Recent Content', 'zoninator' );?></label><br />
			<select name="search-posts" id="zone-post-latest">
				<option value=""><?php esc_html_e( 'Choose a post', 'zoninator' ); ?></option>
				<?php
				foreach ( $recent_posts as $post ) :
					echo sprintf( '<option value="%d">%s</option>', $post->ID, esc_html( get_the_title( $post->ID ) . ' (' . $post->post_status . ')' ) );
				endforeach;
				wp_reset_postdata();
				?>
			</select>
		</div>
		<?php
	}

	function is_zoninator_page() {
		global $current_screen;

		if( function_exists( 'get_current_screen' ) )
			$screen = get_current_screen();

		if( empty( $screen ) ) {
			return ! empty( $_REQUEST['page'] ) && sanitize_key( $_REQUEST['page'] ) == Zoninator_Constants::KEY;
		} else {
			return ! empty( $screen->id ) && strstr( $screen->id, Zoninator_Constants::KEY );
		}
	}

	// TODO: implement in front-end
	function ajax_move_zone_post( $from_zone, $to_zone, $post_id ) {
		$from_zone_id = $this->_get_post_var( 'from_zone_id', 0, 'absint' );
		$to_zone_id = $this->_get_post_var( 'to_zone_id', 0, 'absint' );

		$this->verify_nonce( Zoninator_Constants::ZONE_AJAX_NONCE_ACTION );

		// TODO: validate both zones exist, post exists

		// Add to new zone
		$this->zone_gateway->add_zone_posts( $to_zone_id, $post_id, true );

		// Remove from old zone
		$this->zone_gateway->remove_zone_posts( $from_zone_id, $post_id );
	}

	function verify_nonce( $action ) {
		$action = $this->_get_nonce_key( $action );
		$nonce = $this->_get_request_var( $action );

		if( empty( $nonce ) ) {
			$nonce = $this->_get_request_var( '_wpnonce' );
		}

		if( ! wp_verify_nonce( $nonce, $action ) ) {
			$this->_unauthorized_access();
		}
	}

	function verify_access( $action = '', $zone_id = null ) {
		if( ! $this->permissions->check( $action, $zone_id ) ) {
			$this->_unauthorized_access();
		}
	}

	function _unauthorized_access() {
		wp_die( __( 'Sorry, you\'re not supposed to do that...', 'zoninator' ) );
	}

	function _fill_zone_details( $zone ) {
		if( ! empty( $zone->zoninator_parsed ) && $zone->zoninator_parsed )
			return $zone;

		$details = array();

		if( ! empty( $zone->description ) )
			$details = maybe_unserialize( $zone->description );

		$details = wp_parse_args( $details, $this->zone_detail_defaults );

		foreach( $details as $detail_key => $detail_value ) {
			$zone->$detail_key = $detail_value;
		}

		$zone->zoninator_parsed = true;

		return $zone;
	}

	function do_zoninator_feeds() {

		global $wp_query;

		$query_var = get_query_var( Zoninator_Constants::ZONE_TAXONOMY );

		if ( ! empty( $query_var ) ) {
			$zone_slug = get_query_var( Zoninator_Constants::ZONE_TAXONOMY );

			$results = $this->zone_gateway->get_zone_feed( $zone_slug );

			if ( is_wp_error( $results ) ) {
				$this->send_user_error( $results->get_error_message() );
			}

			$this->json_return( $results );
		}

		return;

	}

	/**
	 * Encode some data and echo it (possibly without cached headers)
	 *
	 * @param array $data
	 * @return bool
	 */
	private function json_return( $data ) {

		if ( $data == NULL )
			return false;

		header( 'Content-Type: application/json' );
		echo json_encode( $data );
		exit();
	}

	private static function send_user_error( $message ) {
		self::status_header_with_message( 406, $message );
		exit();
	}

	/**
	* Modify the header and description in the global array
	*
	* @global array $wp_header_to_desc
	* @param int $status
	* @param string $message
	*/
	private static function status_header_with_message( $status, $message ) {
		global $wp_header_to_desc;

		$status = absint( $status );
		$official_message = isset( $wp_header_to_desc[$status] ) ? $wp_header_to_desc[$status] : '';
		$wp_header_to_desc[$status] = $message;

		status_header( $status );

		$wp_header_to_desc[$status] = $official_message;
	}

	private function _get_message( $message_id, $encode = false ) {
		$message = '';

		if( ! empty( $this->zone_messages[$message_id] ) )
			$message = $this->zone_messages[$message_id];

		if( $encode )
			$message = urlencode( $message );

		return $message;
	}

	private function _get_nonce_key( $action ) {
		return sprintf( '%s-%s', Zoninator_Constants::NONCE_PREFIX, $action );
	}

	private function _get_zone_page_url( $args = array() ) {
		$url = menu_page_url( Zoninator_Constants::KEY, false );

		foreach( $args as $arg_key => $arg_value ) {
			$url = add_query_arg( $arg_key, $arg_value, $url );
		}

		return $url;
	}

	private function _get_value_or_default( $var, $object, $default = '', $sanitize_callback = '' ) {
		if( is_object( $object ) )
			$value = ! empty( $object->$var ) ? $object->$var : $default;
		elseif( is_array( $object ) )
			$value = ! empty( $object[$var] ) ? $object[$var] : $default;
		else
			$value = $default;

		if( is_callable( $sanitize_callback ) ) {
			if( is_array( $value ) )
				$value = array_map( $sanitize_callback, $value );
			else
				$value = call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	private function _get_request_var( $var, $default = '', $sanitize_callback = '' ) {
		return $this->_get_value_or_default( $var, $_REQUEST, $default, $sanitize_callback );
	}

	private function _get_get_var( $var, $default = '', $sanitize_callback = '' ) {
		return $this->_get_value_or_default( $var, $_GET, $default, $sanitize_callback );
	}

	private function _get_post_var( $var, $default = '', $sanitize_callback = '' ) {
		return $this->_get_value_or_default( $var, $_POST, $default, $sanitize_callback );
	}
}

global $zoninator;
$zoninator = new Zoninator;

endif;
