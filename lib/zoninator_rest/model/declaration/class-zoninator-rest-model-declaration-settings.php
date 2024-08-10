<?php
/**
 * Settings Model
 *
 * @package Zoninator_REST/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mixtape_Model_Declaration_Settings
 * Represents a single setting field
 */
class Zoninator_REST_Model_Declaration_Settings extends Zoninator_REST_Model_Declaration implements Zoninator_REST_Interfaces_Permissions_Provider {

	/**
	 * Get Settings
	 *
	 * @throws Zoninator_REST_Exception Override this.
	 */
	function get_settings() {
		Zoninator_REST_Expect::that( false, 'Override this' );
	}

	/**
	 * Default for Attribute. Override to change this behavior
	 *
	 * @param array  $field_data Data.
	 * @param string $attribute Attr.
	 * @return mixed
	 */
	protected function default_for_attribute( $field_data, $attribute ) {
		return null;
	}

	/**
	 * On Field Setup
	 *
	 * @param string                                   $field_name Name.
	 * @param Zoninator_REST_Field_Declaration_Builder $field_builder Builder.
	 * @param array                                    $field_data Data.
	 * @param Zoninator_REST_Environment               $env Env.
	 * @return void
	 */
	protected function on_field_setup( $field_name, $field_builder, $field_data, $env ) {
	}

	/**
	 * Declare Fields
	 *
	 * @param Zoninator_REST_Environment $env Def.
	 * @return array
	 */
	function declare_fields( $env ) {
		$settings_per_group = $this->get_settings();
		$fields             = array();

		foreach ( $settings_per_group as $group_data ) {
			$group_fields = $group_data[1];

			foreach ( $group_fields as $field_data ) {
				$field_builder = $this->field_declaration_builder_from_data( $env, $field_data );
				$fields[]      = $field_builder;
			}
		}
		return $fields;
	}

	/**
	 * Convert bool to bit
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	function bool_to_bit( $value ) {
		return ( ! empty( $value ) && 'false' !== $value ) ? '1' : '';
	}

	/**
	 * Convert bit to bool
	 *
	 * @param mixed $value Val.
	 * @return bool
	 */
	function bit_to_bool( $value ) {
		return ( ! empty( $value ) && '0' !== $value ) ? true : false;
	}

	/**
	 * Get ID
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @return string
	 */
	function get_id( $model ) {
		return strtolower( get_class( $this ) );
	}

	/**
	 * Set ID
	 *
	 * @param Zoninator_REST_Interfaces_Model $model Model.
	 * @param mixed                           $new_id New ID.
	 * @return Zoninator_REST_Interfaces_Model $this
	 */
	function set_id( $model, $new_id ) {
		return $this;
	}

	/**
	 * Build declarations from array
	 *
	 * @param Zoninator_REST_Environment $env Environment.
	 * @param array                      $field_data Data.
	 * @return Zoninator_REST_Field_Declaration_Builder
	 */
	private function field_declaration_builder_from_data( $env, $field_data ) {
		$field_name    = $field_data['name'];
		$field_builder = $env->field( $field_name );
		$default_value = $field_data['std'] ?? $this->default_for_attribute( $field_data, 'std' );
		$label         = $field_data['label'] ?? $field_name;
		$description   = $field_data['desc'] ?? $label;
		$setting_type  = $field_data['type'] ?? null;
		$choices       = isset( $field_data['options'] ) ? array_keys( $field_data['options'] ) : null;
		$field_type    = 'string';

		if ( 'checkbox' === $setting_type ) {
			$field_type = 'boolean';
			if ( $default_value ) {
				// convert our default value as well.
				$default_value = $this->bit_to_bool( $default_value );
			}
			$field_builder
				->with_serializer( array( $this, 'bool_to_bit' ) )
				->with_deserializer( array( $this, 'bit_to_bool' ) );
		} elseif ( 'select' === $setting_type ) {
			$field_type = 'string';
		} else {
			// try to guess numeric fields, although this is not perfect.
			if ( is_numeric( $default_value ) ) {
				$field_type = is_float( $default_value ) ? 'float' : 'integer';
			}
		}

		if ( $default_value ) {
			$field_builder->with_default( $default_value );
		}
		$field_builder
			->with_description( $description )
			->with_dto_name( $field_name )
			->with_type( $env->type( $field_type ) );
		if ( $choices ) {
			$field_builder->with_choices( $choices );
		}

		$this->on_field_setup( $field_name, $field_builder, $field_data, $env );
		return $field_builder;
	}

	/**
	 * Permissions Check
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $action Action.
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		return true;
	}
}
