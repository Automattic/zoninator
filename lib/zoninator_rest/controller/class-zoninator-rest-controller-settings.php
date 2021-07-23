<?php
/**
 * Controller for handling settings
 *
 * @package Zoninator_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Controller_Settings
 */
class Zoninator_REST_Controller_Settings extends Zoninator_REST_Controller_Model {

	/**
	 * Setup
	 */
	public function setup() {
		$this->add_route()
			->add_action( $this->action( 'index', array( $this, 'get_items' ) ) )
			->add_action( $this->action( 'update', array( $this, 'create_item' ) ) );
	}

	/**
	 * Get Settings
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$model = $this->model_prototype->get_data_store()->get_entity( null );
		if ( empty( $model ) ) {
			return $this->not_found( __( 'Settings not found', 'zoninator' ) );
		}

		return $this->ok( $this->prepare_dto( $model ) );
	}

	/**
	 * Create or Update settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		return $this->create_or_update( $request );
	}

	/**
	 * Create or Update a Model
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	protected function create_or_update( $request ) {
		$is_update       = $request->get_method() !== 'POST';
		$model_to_update = $this->model_prototype->get_data_store()->get_entity( null );
		if ( empty( $model_to_update ) ) {
			return $this->not_found( 'Model does not exist' );
		}

		$model = $model_to_update->update_from_array( $request->get_params(), true );

		if ( is_wp_error( $model ) ) {
			return $this->bad_request( $model );
		}

		$validation = $model->validate();
		if ( is_wp_error( $validation ) ) {
			return $this->bad_request( $validation );
		}

		$id_or_error = $this->model_data_store->upsert( $model );

		if ( is_wp_error( $id_or_error ) ) {
			return $this->bad_request( $id_or_error );
		}

		$model = $this->model_prototype->get_data_store()->get_entity( null );
		$dto   = $this->prepare_dto( $model );

		return $is_update ? $this->ok( $dto ) : $this->created( $dto );
	}
}
