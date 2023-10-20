<?php
/**
 * Classic Create, Read, Update, Delete Controller
 *
 * @package Zoninator_REST/Controller
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Zoninator_REST_Controller_CRUD
 */
class Zoninator_REST_Controller_CRUD extends Zoninator_REST_Controller_Model implements Zoninator_REST_Interfaces_Controller {

	/**
	 * Setup
	 */
	public function setup() {
		$this->add_route( '/' )
			->add_action( $this->action( 'index',  array( $this, 'get_items' ) ) )
			->add_action( $this->action( 'create', array( $this, 'create_item' ) ) );

		$this->add_route( '/(?P<id>\d+)' )
			->add_action( $this->action( 'show',  array( $this, 'get_item' ) ) )
			->add_action( $this->action( 'update',  array( $this, 'update_item' ) ) )
			->add_action( $this->action( 'delete', array( $this, 'delete_item' ) ) );
	}

	/**
	 * Get Items.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$item_id = isset( $request['id'] ) ? absint( $request['id'] ) : null;

		if ( null === $item_id ) {
			$models = $this->get_model_data_store()->get_entities();
			$data = $this->prepare_dto( $models );
			return $this->ok( $data );
		}

		$model = $this->model_prototype->get_data_store()->get_entity( $item_id );
		if ( empty( $model ) ) {
			return $this->not_found( __( 'Model not found' ) );
		}

		return $this->ok( $this->prepare_dto( $model ) );
	}

	/**
	 * Get Item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return $this->get_items( $request );
	}

	/**
	 * Create Item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		$is_update = false;
		return $this->create_or_update( $request, $is_update );
	}

	/**
	 * Update Item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		$is_update = true;
		return $this->create_or_update( $request, $is_update );
	}

	/**
	 * Create Or Update Item
	 *
	 * @param WP_REST_Request $request Request.
	 * @param bool            $is_update Is Update.
	 *
	 * @return WP_REST_Response
	 */
	protected function create_or_update( $request, $is_update = false ) {
		$model_to_update = null;
		if ( $is_update ) {
			$id = isset( $request['id'] ) ? absint( $request['id'] ) : null;
			if ( ! empty( $id ) ) {
				$model_to_update = $this->model_prototype->get_data_store()->get_entity( $id );
				if ( empty( $model_to_update ) ) {
					return $this->not_found( 'Model does not exist' );
				}
			}
		}

		if ( $is_update && $model_to_update ) {
			$model = $model_to_update->update_from_array( $request->get_params(), $is_update );
		} else {
			$model = $this->get_model_prototype()->new_from_array( $request->get_params() );
		}

		if ( is_wp_error( $model ) ) {
			$wp_err = $model;
			return $this->bad_request( $wp_err );
		}

		$validation = $model->validate();
		if ( is_wp_error( $validation ) ) {
			return $this->bad_request( $validation );
		}

		$id_or_error = $this->model_data_store->upsert( $model );

		if ( is_wp_error( $id_or_error ) ) {
			return $this->bad_request( $id_or_error );
		}

		$dto = $this->prepare_dto( array(
			'id' => absint( $id_or_error ),
		) );

		return $is_update ? $this->ok( $dto ) : $this->created( $dto );
	}

	/**
	 * Delete an Item
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : null;
		if ( empty( $id ) ) {
			return $this->bad_request( 'No Model ID provided' );
		}
		$model = $this->model_prototype->get_data_store()->get_entity( $id );
		if ( null === $model ) {
			return $this->not_found( 'Model does not exist' );
		}
		$result = $this->model_data_store->delete( $model );
		return $this->ok( $result );
	}

	/**
	 * Model To Dto
	 *
	 * @param Zoninator_REST_Interfaces_Model $model The Model.
	 * @return array
	 */
	protected function model_to_dto( $model ) {
		$result = parent::model_to_dto( $model );
		$result['_links'] = $this->add_links( $model );
		return $result;
	}

	/**
	 * Add Links
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @return array
	 */
	protected function add_links( $model ) {
		$base_url = rest_url() . $this->controller_bundle->get_prefix() . $this->base . '/';

		$result = array(
			'collection' => array(
				array(
					'href' => esc_url( $base_url ),
				),
			),
		);
		if ( $model->get_id() ) {
			$result['self'] = array(
				array(
					'href' => esc_url( $base_url . $model->get_id() ),
				),
			);
		}
		if ( $model->has( 'author' ) ) {
			$result['author'] = array(
				array(
					'href' => esc_url( rest_url() . 'wp/v2/users/' . $model->get( 'author' ) ),
				),
			);
		}
		return $result;
	}
}
