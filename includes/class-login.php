<?php
/**
 * Login Form.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Login {

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
     * Store the ID of the redirect_to field.
     *
     * @var int
     */
    public $redirect_to_field_id;


    /**
     * Store the ID of the remember_me field.
     *
     * @var int
     */
    public $remember_me_field_id;


    /**
     * Store the ID of the contact page.
     *
     * @var int
     */
    public $contact_page_id;


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Do not initialize in admin area.
        if ( is_admin() ) {
            return;
        }

        // The form and page ids
        $this->form_id = isset( $plugin_settings[ 'login_form' ] ) ? absint( $plugin_settings[ 'login_form' ] ) : 0;
        $this->page_id = isset( $plugin_settings[ 'login_page' ] ) ? absint( $plugin_settings[ 'login_page' ] ) : 0;
        if ( $this->form_id <= 0 || $this->page_id <= 0 ) {
            return; // No form or login page set, do not initialize.
        }

        // The field ids
        $this->get_field_ids();

        // Let's add links below the submit button
        $this->contact_page_id = isset( $plugin_settings[ 'contact_page' ] ) ? absint( $plugin_settings[ 'contact_page' ] ) : 0;
        add_filter( 'gform_submit_button_' . $this->form_id, [ $this, 'add_links_below_button' ], 10, 2 );

        // Run the hooks
        add_filter( 'gform_validation_' . $this->form_id, [ $this, 'validate_login_fields' ] );
        add_action( 'gform_pre_submission_' . $this->form_id, [ $this, 'remove_trailing_spaces' ] );
        add_action( 'gform_after_submission_' . $this->form_id, [ $this, 'sign_the_user_in' ], 10, 2 );
        add_filter( 'gform_confirmation_' . $this->form_id, [ $this, 'confirmation' ], 10, 4 );

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
                    } elseif ( $field->type === 'hidden' && $field->inputName === 'redirect_to' ) {
                        $this->redirect_to_field_id = $field->id;
                    } elseif ( ( $field->type === 'checkbox' || $field->type === 'consent' ) && $field->inputName === 'remember_me' ) {
                        $this->remember_me_field_id = $field->id;
                    }
                }
            }
        }
    } // End get_field_ids()


    /**
     * Add links below the login form submit button.
     *
     * @param string $button
     * @param array $form
     * @return string
     */
    public function add_links_below_button( $button, $form ) {
        $reset_url = wp_lostpassword_url();
        $links     = sprintf(
            '<div class="gfadvtools-login-help-links">
                <a href="%s">%s</a>',
            esc_url( $reset_url ),
            esc_html__( 'Lost your password?', 'gf-tools' )
        );

        if ( ! empty( $this->contact_page_id ) ) {
            $contact_url = add_query_arg(
                [
                    'subject' => 'technical-issue',
                ],
                get_permalink( $this->contact_page_id )
            );
            $links .= sprintf(
                ' | <a href="%s">%s</a>',
                esc_url( $contact_url ),
                esc_html__( 'Trouble logging in?', 'gf-tools' )
            );
        }

        $links .= '</div>';

        $links = apply_filters( 'gfadvtools_login_help_links', $links, $form );

        return $button . $links;
    } // End add_links_below_button()


    /**
     * Perform combined email and password validation during form submission.
     *
     * @param array $validation_result
     * @return array
     */
    public function validate_login_fields( $validation_result ) {
        $form = $validation_result[ 'form' ];

        // Field IDs defined as class properties
        $email_field_id    = $this->email_field_id;
        $password_field_id = $this->password_field_id;

        // Initialize validity flags
        $is_valid_email    = true;
        $is_valid_password = true;

        // Get submitted values
        $email    = rgpost( "input_{$email_field_id}" );
        $password = rgpost( "input_{$password_field_id}" );

        // Get user by email
        $user = get_user_by( 'email', $email );

        // Validate email
        if ( !$user || !isset( $user->ID ) ) {
            $is_valid_email = false;
        }

        // Validate password only if email passed
        if ( $is_valid_email && !wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            $is_valid_password = false;
        }

        // Loop through fields and apply validation messages
        foreach ( $form[ 'fields' ] as &$field ) {
            if ( $field->id == $email_field_id && !$is_valid_email ) {
                $field->failed_validation  = true;
                $field->validation_message = __( 'We couldn’t find an account with that email address.', 'gf-tools' );
            }

            if ( $field->id == $password_field_id && !$is_valid_password ) {
                $field->failed_validation  = true;
                $field->validation_message = __( 'The password doesn’t match our records. Please check and try again.', 'gf-tools' );
            }
        }

        // Set validation result
        $validation_result[ 'form' ] = $form;
        $validation_result[ 'is_valid' ] = $is_valid_email && $is_valid_password;

        return $validation_result;
    } // End validate_login_fields()


    /**
     * Remove trailing spaces.
     *
     * @param array $form
     */
    public function remove_trailing_spaces( $form ) {
        foreach ( $form[ 'fields' ] as &$field ) {
            $input_key = 'input_' . $field->id;

            if ( isset( $_POST[ $input_key ] ) ) {
                $value = wp_unslash( $_POST[ $input_key ] );

                if ( $field->type === 'password' || $field->type === 'email' ) {
                    $_POST[ $input_key ] = rtrim( $value );
                }
            }
        }
    } // End remove_trailing_spaces()


    /**
     * Sign the user in
     *
     * @param array $entry
     * @param array $form
     * @return void
     */
    public function sign_the_user_in( $entry, $form ) {
        // Sanitize inputs
        $email = sanitize_email( rgar( $entry, $this->email_field_id ) );
        $pass  = rgar( $entry, $this->password_field_id ); // password raw

        if ( empty( $email ) || empty( $pass ) ) {
            return; // or handle error
        }

        // Get username by email or fallback to email as username
        $user = get_user_by( 'email', $email );
        $username = $user ? $user->user_login : $email;

        // Get remember me value from the entry, cast to boolean
        $remember = !empty( rgar( $entry, $this->remember_me_field_id ) );

        $creds = [
            'user_login'    => $username,
            'user_password' => $pass,
            'remember'      => $remember,
        ];

        $signon = wp_signon( $creds, is_ssl() );
        if ( is_wp_error( $signon ) ) {
            return;
        }

        // Successful login: set current user and auth cookie
        wp_set_current_user( $signon->ID );
        wp_set_auth_cookie( $signon->ID );
    } // End sign_the_user_in()

    
    /**
     * Handle the confirmation after form submission.
     *
     * @param string|array $confirmation
     * @param object  $form
     * @param object  $entry
     * @param bool   $ajax
     * @return string|array
     */
    public function confirmation( $confirmation, $form, $entry, $ajax ) {
        $redirect_to = null;
        $access_admin = false;

        // Priority 1: Get raw URL from query or entry
        $raw_url = '';
        if ( isset( $_GET['redirect_to'] ) ) {
            $raw_url = sanitize_url( wp_unslash( $_GET['redirect_to'] ) );
        } elseif ( $this->redirect_to_field_id ) {
            $raw_url = sanitize_url( rgar( $entry, $this->redirect_to_field_id ) );
        }

        if ( !empty( $raw_url ) ) {
            $redirect_to = urldecode( $raw_url );
        }

        // Get user for potential fallback and filtering
        $user_email = sanitize_email( rgar( $entry, $this->email_field_id ) );
        $user = $user_email ? get_user_by( 'email', $user_email ) : null;

        if ( $user ) {
            $access_admin = user_can( $user, 'read' ) && user_can( $user, 'access_admin' );

            // Priority 2: fallback only if redirect still not set
            if ( !$redirect_to ) {
                $redirect_to = $access_admin ? admin_url() : home_url();
            }

            // Apply final filter
            $redirect_to = apply_filters( 'gfat_user_landing_page', $redirect_to, $user, $access_admin );
        }

        // If still no redirect, fallback to login page
        if ( !$redirect_to && $this->page_id ) {
            $redirect_to = get_permalink( $this->page_id );
        }

        if ( $redirect_to ) {
            $confirmation = [ 'redirect' => esc_url_raw( $redirect_to ) ];
        }

        return $confirmation;
    } // End confirmation()

}