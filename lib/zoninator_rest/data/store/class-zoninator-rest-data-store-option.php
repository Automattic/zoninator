<?php
/**
 * Data Store Abstract
 *
 * @package Zoninator_REST/Data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Zoninator_REST_Data_Store_Option
 */
class Zoninator_REST_Data_Store_Option extends Zoninator_REST_Data_Store_Abstract {

	/**
	 * Guard value to distinguish between get_option returning results or not
	 *
	 * @var stdClass
	 */
	private $does_not_exist_guard;

	/**
	 * Zoninator_REST_Data_Store_Option constructor.
	 *
	 * @param Zoninator_REST_Model $model_prototype Def.
	 * @param array    $args Args.
	 */
	public function __construct( $model_prototype, $args = array() ) {
		parent::__construct( $model_prototype, $args );
		$this->does_not_exist_guard = new stdClass();
	}

	/**
	 * Get Entities
	 *
	 * @param null|mixed $filter Filter.
	 * @return Zoninator_REST_Interfaces_Model
	 */
	public function get_entities( $filter = null ) {
		// there is only one option bag and one option bag global per data store.
		return $this->get_entity( null );
	}

	/**
	 * Get Entity
	 *
	 * @param int $id The id of the entity.
	 * @return Zoninator_REST_Interfaces_Model
	 */
	public function get_entity( $id ) {
		$field_declarations = $this->get_model_prototype()->get_fields();
		$raw_data = array();
		foreach ( $field_declarations as $field_declaration ) {
			/**
			 * Field Declaration
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			$option = get_option( $field_declaration->get_map_from(), $this->does_not_exist_guard );
			if ( $this->does_not_exist_guard !== $option ) {
				$raw_data[ $field_declaration->get_map_from() ] = $option;
			}
		}

		return $this->get_model_prototype()->create( $raw_data, array(
			'deserialize' => true,
		) );
	}

	/**
	 * Delete
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @param array               $args Args.
	 * @return mixed
	 */
	public function delete( $model, $args = array() ) {
		$options_to_delete = array_keys( $model->serialize() );
		foreach ( $options_to_delete as $option_to_delete ) {
			if ( false !== get_option( $option_to_delete, false ) ) {
				$result = delete_option( $option_to_delete );
				if ( false === $result ) {
					return new WP_Error( 'delete-option-failed' );
				}
			}
		}
		return true;
	}

	/**
	 * Update/Insert
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @return mixed
	 */
	public function upsert( $model ) {
		$fields_for_insert = $model->serialize();
		foreach ( $fields_for_insert as $option_name => $option_value ) {
			$previous_value = get_option( $option_name, $this->does_not_exist_guard );
			if ( $this->does_not_exist_guard !== $previous_value ) {
				update_option( $option_name, $option_value );
			} else {
				add_option( $option_name, $option_value );
			}
		}
		return true;
	}
}
