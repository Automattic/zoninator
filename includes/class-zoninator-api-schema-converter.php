<?php

class Zoninator_Api_Schema_Converter {
	/**
	 * As Schema
	 *
	 * @param Zoninator_REST_Model $model_definition Def.
	 * @return mixed
	 */
	public function as_schema( $model_definition ) {
		$fields = $model_definition->get_fields();
		$properties = array();
		$required = array();
		foreach ( $fields as $field_declaration ) {
			/**
			 * Our declaration
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			$properties[ $field_declaration->get_data_transfer_name() ] = $field_declaration->as_item_schema_property();
			if ( $field_declaration->is_required() ) {
				$required[] = $field_declaration->get_data_transfer_name();
			}
		}
		$schema = array(
			'$schema' => 'http://json-schema.org/schema#',
			'title' => $model_definition->get_name(),
			'type' => 'object',
			'properties' => (array) apply_filters( 'rest_api_schema_properties', $properties, $model_definition ),
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * As Schema
	 *
	 * @param Zoninator_REST_Model $model_definition Def.
	 * @return array
	 */
	public function as_args( $model_definition ) {
		$fields = $model_definition->get_fields();
		$result = array();
		foreach ( $fields as $field_declaration ) {
			$type_schema = $field_declaration->get_type()->schema();
			/**
			 * Our declaration
			 *
			 * @var Zoninator_REST_Field_Declaration $field_declaration
			 */
			$arg = array(
				'description'       => $field_declaration->get_description(),
				'type'              => $type_schema['type'],
				'required'          => $field_declaration->is_required(),
			);

			if ( ! $field_declaration->is_required() ) {
				$arg['default'] = $field_declaration->get_default_value();
			}

			$result[ $field_declaration->get_data_transfer_name() ] = $arg;
		}
		return $result;
	}
}