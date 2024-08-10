<?php
/**
 * Data Store For custom Post types
 *
 * @package Zoninator_REST/Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Data_Store_CustomPostType
 */
class Zoninator_REST_Data_Store_CustomPostType extends Zoninator_REST_Data_Store_Abstract {
	/**
	 * The post type name
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Mixtape_Data_Store_CustomPostType constructor.
	 *
	 * @param null|Zoninator_REST_Model_Definition $model_prototype Def.
	 * @param array                                $args Args.
	 */
	public function __construct( $model_prototype = null, $args = array() ) {
		$this->post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
		parent::__construct( $model_prototype, $args );
	}

	/**
	 * Get Entities
	 *
	 * @param null|mixed $filter Filter.
	 *
	 * @return Zoninator_REST_Model_Collection
	 */
	public function get_entities( $filter = null ) {
		$query      = new WP_Query(
			array(
				'post_type'   => $this->post_type,
				'post_status' => 'any',
			) 
		);
		$posts      = $query->get_posts();
		$collection = array();
		foreach ( $posts as $post ) {
			$collection[] = $this->create_from_post( $post );
		}
		return new Zoninator_REST_Model_Collection( $collection );
	}

	/**
	 * Get Entity
	 *
	 * @param int $id The id of the entity.
	 * @return Zoninator_REST_Model|null
	 */
	public function get_entity( $id ) {
		$post = get_post( absint( $id ) );
		if ( empty( $post ) || $post->post_type !== $this->post_type ) {
			return null;
		}

		return $this->create_from_post( $post );
	}

	/**
	 * Create from Post.
	 *
	 * @param WP_Post $post Post.
	 * @return Zoninator_REST_Model
	 * @throws Zoninator_REST_Exception If something goes wrong.
	 */
	private function create_from_post( $post ) {
		$field_declarations = $this->get_model_prototype()->get_fields();
		$raw_post_data      = $post->to_array();
		$raw_meta_data      = get_post_meta( $post->ID ); // assumes we are only ever adding one postmeta per key.

		$flattened_meta = array();
		foreach ( $raw_meta_data as $key => $value_arr ) {
			$flattened_meta[ $key ] = $value_arr[0];
		}
		$merged_data = array_merge( $raw_post_data, $flattened_meta );

		return $this->get_model_prototype()->create(
			$merged_data,
			array(
				'deserialize' => true,
			) 
		);
	}

	/**
	 * Delete
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @param array                           $args Args.
	 * @return mixed
	 */
	public function delete( $model, $args = array() ) {
		$id = $model->get_id();

		$args = wp_parse_args(
			$args,
			array(
				'force_delete' => false,
			) 
		);

		do_action( 'mixtape_data_store_delete_model_before', $model, $id );

		if ( $args['force_delete'] ) {
			$result = wp_delete_post( $model->get_id() );
			$model->set( 'id', 0 );
			do_action( 'mixtape_data_store_delete_model', $model, $id );
		} else {
			$result = wp_trash_post( $model->get_id() );
			$model->set( 'status', 'trash' );
			do_action( 'mixtape_data_store_trash_model', $model, $id );
		}

		if ( false === $result ) {
			do_action( 'mixtape_data_store_delete_model_fail', $model, $id );
			return new WP_Error( 'delete-failed', 'delete-failed' );
		}
		return $result;
	}

	/**
	 * Upsert
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 *
	 * @return mixed|WP_Error
	 */
	public function upsert( $model ) {
		$id          = $model->get_id();
		$updating    = ! empty( $id );
		$fields      = $model->serialize( Zoninator_REST_Field_Declaration::FIELD );
		$meta_fields = $model->serialize( Zoninator_REST_Field_Declaration::META );
		if ( ! isset( $fields['post_type'] ) ) {
			$fields['post_type'] = $this->post_type;
		}
		if ( isset( $fields['ID'] ) && empty( $fields['ID'] ) ) {
			// ID of 0 is not acceptable on CPTs, so remove it.
			unset( $fields['ID'] );
		}

		do_action( 'mixtape_data_store_model_upsert_before', $model );

		$id_or_error = wp_insert_post( $fields, true );
		if ( is_wp_error( $id_or_error ) ) {
			do_action( 'mixtape_data_store_model_upsert_error', $model );
			return $id_or_error;
		}
		$model->set( 'id', absint( $id_or_error ) );
		foreach ( $meta_fields as $meta_key => $meta_value ) {
			if ( $updating ) {
				$id_or_bool = update_post_meta( $id_or_error, $meta_key, $meta_value );
			} else {
				$id_or_bool = add_post_meta( $id_or_error, $meta_key, $meta_value );
			}

			if ( false === $id_or_bool ) {
				do_action( 'mixtape_data_store_model_upsert_error', $model );
				// Something was wrong with this update/create. TODO: Should we stop mid create/update?
				return new WP_Error(
					'mixtape-error-creating-meta',
					'There was an error updating/creating an entity field',
					array(
						'field_key'   => $meta_key,
						'field_value' => $meta_value,
					)
				);
			}
		}

		do_action( 'mixtape_data_store_model_upsert_after', $model );

		return absint( $id_or_error );
	}
}
