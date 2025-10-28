<?php
/**
 * Validations class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Validations {

    /**
	 * Constructor
	 */
	public function __construct() {

        // Custom Field Validations
        add_filter( 'gform_field_validation', [ $this, 'validate_fields' ], 10, 4 );

	} // End __construct()


    /**
     * Get the fields to validate
     *
     * @param array $form
     * @return array
     */
    public function get_fields_to_validate( $form ) {
        $fields = [];

        foreach ( $form[ 'fields' ] as $field ) {
            if ( isset( $field[ 'validate' ] ) && $field[ 'validate' ] != '' && isset( $field[ 'validate_message' ] ) && $field[ 'validate_message' ] != '' ) {
                $answers = array_map( 'trim', explode( ',', $field[ 'validate' ] ) );
                foreach ( $answers as $key => $answer ) {
                    $answer = sanitize_text_field( $answer );
                    if ( strpos( $answer, '{' ) !== false ) {
                        $answer = GFCommon::replace_variables( $answer, $form, 0 );
                    }
                    $answers[ $key ] = $answer;
                }

                $fields[ $field[ 'id' ] ] = [
                    'field'   => $field,
                    'answers' => $answers,
                    'message' => sanitize_text_field( $field[ 'validate_message' ] ),
                ];
            }
        }

        return $fields;
    } // End get_field_user_meta_keys_to_add()


    /**
     * Validate fields
     *
     * @param array $result
     * @param mixed $value
     * @param array $form
     * @param object $field
     * @return array
     */
    public function validate_fields( $result, $value, $form, $field ) {
        $fields = $this->get_fields_to_validate( $form );
        if ( empty( $fields ) ) {
            return $result;
        }
        
        // Example: Custom validation for a specific field
        if ( isset( $fields[ $field->id ] ) ) {
            $field_data = $fields[ $field->id ];
            if ( in_array( $value, $field_data[ 'answers' ] ) ) {
                $result[ 'is_valid' ] = true;
            } else {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ] = $field_data[ 'message' ];
            }
        }

        return $result;
    } // End validate_fields()
    
}