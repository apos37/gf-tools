<?php
/**
 * Registration Form.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Registration {

    /**
     * Store the form ID for the login form.
     *
     * @var int
     */
    public $form_id;


    /**
     * Store the ID of the login page.
     *
     * @var int
     */
    public $page_id;


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {
        
        // Do not initialize in admin area.
        if ( is_admin() ) {
            return;
        }

        // If registration is not allowed from Settings > General, then stop
        if ( !get_option( 'users_can_register' ) ) {
            return;
        }

        // The field ids
        $this->form_id = isset( $plugin_settings[ 'registration_form' ] ) ? absint( $plugin_settings[ 'registration_form' ] ) : 0;
        $this->page_id = isset( $plugin_settings[ 'registration_page' ] ) ? absint( $plugin_settings[ 'registration_page' ] ) : 0;
        if ( $this->form_id <= 0 || $this->page_id <= 0 ) {
            return; // No form or login page set, do not initialize.
        }

        // Run the hooks
        add_filter( 'gform_user_registration_validation', [ $this, 'allow_email_as_username' ], 10, 3 );

	} // End __construct()


    /**
     * Allow email addresses as usernames in the registration form.
     *
     * @param array $form
     * @param array $feed
     * @param int $pagenum
     * @return array
     */
    public function allow_email_as_username( $form, $feed, $pagenum ) {
        if ( $form[ 'id' ] != $this->form_id ) {
            return $form;
        }

        $email_id = rgar( $feed[ 'meta' ], 'email' );

        foreach ( $form[ 'fields' ] as $k => &$field ) {
            if ( $field->id == $email_id ) {

                // If already failed validation, skip overriding it
                if ( ! empty( $field[ 'failed_validation' ] ) ) {
                    continue;
                }

                $email = strtolower( rgpost( 'input_' . $field->id ) );

                if ( username_exists( $email ) ) {
                    $field[ 'failed_validation' ]  = true;
                    $field[ 'validation_message' ] = __( 'The email address you entered is already in use. Please choose another.', 'gf-tools' );
                } else {
                    $field[ 'failed_validation' ]  = false;
                    $field[ 'validation_message' ] = '';
                }

                $form[ 'fields' ][ $k ] = $field;
                break;
            }
        }

        return $form;
    } // End allow_email_as_username()

}