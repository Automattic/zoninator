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
 * Class Zoninator_REST_Model_Settings
 * Represents a single setting set
 */
class Zoninator_REST_Model_Settings extends Zoninator_REST_Model {

	/**
	 * Get Settings
	 *
	 * @throws Zoninator_REST_Exception Override this.
	 * @return array
	 */
	public function get_settings() {
		Zoninator_REST_Expect::should_override( __METHOD__ );
		return array();
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
	 * @return array
	 */
	public function declare_fields() {
		$env                = $this->get_environment();
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
	public function bool_to_bit( $value ) {
		return ( ! empty( $value ) && 'false' !== $value ) ? '1' : '';
	}

	/**
	 * Convert bit to bool
	 *
	 * @param mixed $value Val.
	 * @return bool
	 */
	public function bit_to_bool( $value ) {
		return ! empty( $value ) && '0' !== $value;
	}

	/**
	 * Get ID
	 *
	 * @return string
	 */
	public function get_id() {
		return strtolower( get_class( $this ) );
	}

	/**
	 * Set ID
	 *
	 * @param mixed $new_id New ID.
	 * @return Zoninator_REST_Interfaces_Model $this
	 */
	public function set_id( $new_id ) {
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
		} elseif ( is_numeric( $default_value ) ) {
			// try to guess numeric fields, although this is not perfect.
			$field_type = is_float( $default_value ) ? 'float' : 'integer';
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
}
