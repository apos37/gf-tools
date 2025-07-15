<?php
/**
 * Password Reset Form.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Password_Reset {

    /**
     * Store the form ID for the form.
     *
     * @var int
     */
    public $form_id;


    /**
     * Store the ID of the page.
     *
     * @var int
     */
    public $page_id;


    /**
     * Store the ID of the action field.
     *
     * @var int
     */
    public $action_field_id;


    /**
     * Store the ID of the key field.
     *
     * @var int
     */
    public $key_field_id;


    /**
     * Store the ID of the login field.
     *
     * @var int
     */
    public $login_field_id;


    /**
     * Store the ID of the email field.
     *
     * @var int
     */
    public $email_field_id;


    /**
     * Store the ID of the password field.
     *
     * @var int
     */
    public $password_field_id;


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Do not initialize in admin area.
        if ( is_admin() ) {
            return;
        }

        // The field ids
        $this->form_id = isset( $plugin_settings[ 'password_reset_form' ] ) ? absint( $plugin_settings[ 'password_reset_form' ] ) : 0;
        $this->page_id = isset( $plugin_settings[ 'password_reset_page' ] ) ? absint( $plugin_settings[ 'password_reset_page' ] ) : 0;
        if ( $this->form_id <= 0 || $this->page_id <= 0 ) {
            return; // No form or login page set, do not initialize.
        }

        $this->get_field_ids();

        // Change the submit button depending on what screen we're on
        add_filter( 'gform_submit_button_' . $this->form_id, [ $this, 'form_submit_text' ], 10, 2 );

        // Validate the email field exists in the form
        add_filter( 'gform_validation_' . $this->form_id, [ $this, 'validate_email_exists' ] );

        // Handle the password reset form submission
        add_action( 'gform_after_submission_' . $this->form_id, [ $this, 'handle_password_reset' ], 10, 2 );

        // Replace merge tags for the reset URL
        add_filter( 'gform_replace_merge_tags', [ $this, 'replace_reset_url_merge_tag' ], 10, 3 );

	} // End __construct()


    /**
     * Get the field IDs for email and password fields.
     *
     * @return void
     */
    public function get_field_ids() {
        if ( $this->form_id > 0 ) {
            $form = GFAPI::get_form( $this->form_id );
            if ( is_array( $form[ 'fields' ] ) && count( $form[ 'fields' ] ) > 0 ) {
                foreach ( $form[ 'fields' ] as $field ) {
                    if ( $field->type === 'email' ) {
                        $this->email_field_id = $field->id;
                    } elseif ( $field->type === 'password' ) {
                        $this->password_field_id = $field->id;
                    } elseif ( $field->type === 'hidden' && $field->inputName === 'action' ) {
                        $this->action_field_id = $field->id;
                    } elseif ( $field->type === 'hidden' && $field->inputName === 'key' ) {
                        $this->key_field_id = $field->id;
                    } elseif ( $field->type === 'hidden' && $field->inputName === 'login' ) {
                        $this->login_field_id = $field->id;
                    }
                }
            }
        }
    } // End get_field_ids()


    /**
     * Reset form submit button text
     *
     * @param string $button
     * @param array $form
     * @return string
     */
    public function form_submit_text( $button, $form ) {
        $action = isset( $_GET[ 'action' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'action' ] ) ) : '';
        
        $label = in_array( $action, [ 'rp', 'resetpass' ], true )
            ? __( 'Set New Password', 'gf-tools' )
            : __( 'Request Password Reset', 'gf-tools' );

        $button = sprintf(
            '<button class="gform_button button" id="gform_submit_button_%d">%s</button>',
            $form[ 'id' ],
            esc_html( $label )
        );

        return $button;
    } // End form_submit_text()


    /**
     * Validate that the entered email exists.
     *
     * @param array $validation_result
     * @return array
     */
    public function validate_email_exists( $validation_result ) {
        $form   = $validation_result[ 'form' ];
        $action = sanitize_text_field( rgpost( 'input_' . $this->action_field_id ) );

        if ( !in_array( $action, [ 'rp', 'resetpass' ], true ) ) {

            $email = sanitize_email( rgpost( 'input_' . $this->email_field_id ) );
            $user  = get_user_by( 'email', $email );

            if ( !$user ) {
                foreach ( $form[ 'fields' ] as &$field ) {
                    if ( $field->id == $this->email_field_id ) {
                        $field->failed_validation  = true;
                        $field->validation_message = __( 'There is no account with that email address.', 'gf-tools' );
                        $validation_result[ 'is_valid' ] = false;
                        break;
                    }
                }
            }
        }

        $validation_result[ 'form' ] = $form;
        return $validation_result;
    } // End validate_email_exists()


    /**
     * Handle the password reset form submission.
     *
     * @param array $entry
     * @param array $form
     */
    public function handle_password_reset( $entry, $form ) {
        $action = sanitize_text_field( rgar( $entry, $this->action_field_id ) );

        // The reset password actions when they arrive from the link (rp, resetpass)
        if ( in_array( $action, [ 'rp', 'resetpass' ], true ) ) {
            $user_login = sanitize_user( rgar( $entry, $this->login_field_id ) );
            $key        = sanitize_text_field( rgar( $entry, $this->key_field_id ) );
            $password   = rgar( $entry, $this->password_field_id ); // Passwords are raw input

            $user = check_password_reset_key( $key, $user_login );
            if ( !is_wp_error( $user ) ) {
                reset_password( $user, $password );
            }
        }
    } // End handle_password_reset()


    /**
     * Replace custom merge tag with the password reset URL.
     *
     * @param string $text
     * @param array $form
     * @param array $entry
     * @return string
     */
    public function replace_reset_url_merge_tag( $text, $form, $entry ) {
        if ( strpos( $text, '{Password Reset URL}' ) === false ) {
            return $text;
        }

        $email = sanitize_email( rgar( $entry, $this->email_field_id ) );
        $user  = get_user_by( 'email', $email );

        if ( !$user ) {
            return str_replace( '{Password Reset URL}', '', $text );
        }

        $key  = get_password_reset_key( $user );
        $url  = add_query_arg( [
            'action' => 'rp',
            'key'    => $key,
            'login'  => rawurlencode( $user->user_login ),
        ], get_permalink( $this->page_id ) );

        return str_replace( '{Password Reset URL}', esc_url_raw( $url ), $text );
    } // End replace_reset_url_merge_tag()

}