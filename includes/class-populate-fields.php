<?php
/**
 * Populate fields class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Populate_Fields {

    /**
     * Store the plugin settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings = [];


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];

        // Field values
        add_filter( 'gform_field_value_previous_value', [ $this, 'previous_value' ], 10, 2 );
        add_filter( 'gform_field_value', [ $this, 'connection' ], 10, 3 );
        add_filter( 'gform_field_value', [ $this, 'query_string' ], 10, 3 );
        add_filter( 'gform_field_value', [ $this, 'cookies_and_sessions' ], 10, 3 );

        // Populate quiz answers for admins only
        add_filter( 'gform_pre_render', [$this, 'auto_fill_quiz_answers' ] );
        add_filter( 'gform_pre_validation', [$this, 'auto_fill_quiz_answers' ] );
        add_filter( 'gform_pre_submission_filter', [$this, 'auto_fill_quiz_answers' ] );
        add_filter( 'gform_admin_pre_render', [$this, 'auto_fill_quiz_answers' ] );
        add_filter( 'gform_get_form_filter', [ $this, 'auto_fill_quiz_answers_notice' ], 10, 2 );

        // Populate timezone fields
        add_filter( 'gform_pre_render', [ $this, 'populate_timezones' ] );
        add_filter( 'gform_pre_validation', [ $this, 'populate_timezones' ] );
        add_filter( 'gform_pre_submission_filter', [ $this, 'populate_timezones' ] );
        add_filter( 'gform_admin_pre_render', [ $this, 'populate_timezones' ] );

        // Populate users
        add_filter( 'gform_pre_render', [ $this, 'populate_users' ] );
        add_filter( 'gform_pre_validation', [ $this, 'populate_users' ] );
        add_filter( 'gform_pre_submission_filter', [ $this, 'populate_users' ] );
        add_filter( 'gform_admin_pre_render', [ $this, 'populate_users' ] );
        
	} // End __construct()


    /**
     * Populate previous entry value
     *
     * @param string $value
     * @param object $field
     * @return string
     */
    public function previous_value( $value, $field ) {
        $entries = GFAPI::get_entries(
            $field->formId,
            [
                'field_filters' => [
                    [
                        'key'   => 'created_by',
                        'value' => get_current_user_id(),
                    ],
                ],
            ],
            [],
            [ 'page_size' => 1 ]
        );
     
        return rgars( $entries, '0/' . $field->id );
    } // End previous_value()


    /**
     * Populate connected page data
     *
     * @param string $value
     * @param object $field
     * @param string $name
     * @return string
     */
    public function connection( $value, $field, $name ) {
        if ( (new GF_Advanced_Tools_Helpers)->str_starts_with( $name, 'connection_' ) ) {
            $meta_key = str_replace( 'connection_', '', $name );
            $form_id = $field->formId;
            if ( $form = GFAPI::get_form( $form_id ) ) {
                if ( $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form ) ) {
                    if ( isset( $form_settings[ 'associated_page_qs' ] ) ) {
                        $associated_page_qs = $form_settings[ 'associated_page_qs' ];
                        if ( is_numeric( $associated_page_qs ) ) {
                            $post_id = absint( $associated_page_qs );
                        } else {
                            $query_string = sanitize_text_field( $associated_page_qs );
                            if ( isset( $_GET[ $query_string ] ) && absint( $_GET[ $query_string ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                $post_id = absint( $_GET[ $query_string ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            }
                        }
                        if ( $post_id ) {
                            if ( $post = get_post( $post_id ) ) {
                                if ( isset( $post->$meta_key ) ) {
                                    return sanitize_text_field( $post->$meta_key );
                                }
                            }
                        }
                    }
                }
            }
        }
        return $value;
    } // End connection()


    /**
     * Populate post or user value if ID is found in the query string
     *
     * @param string $value
     * @param object $field
     * @param string $name
     * @return string
     */
    public function query_string( $value, $field, $name ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Get post meta from post_id in query string
        if ( $HELPERS->str_starts_with( $name, 'qs_post_' ) ) {
            $meta_key = str_replace( 'qs_post_', '', $name );

            if ( isset( $_GET[ 'post_id' ] ) && absint( $_GET[ 'post_id' ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $post_id = absint( $_GET[ 'post_id' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $post = get_post( $post_id ) ) {
                    if ( isset( $post->$meta_key ) ) {
                        return sanitize_text_field( $post->$meta_key );
                    }
                }
            }
        }

        // Get user meta from user_id in query string
        if ( $HELPERS->str_starts_with( $name, 'qs_user_' ) ) {
            $meta_key = str_replace( 'qs_user_', '', $name );

            if ( isset( $_GET[ 'user_id' ] ) && absint( $_GET[ 'user_id' ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $user_id = absint( $_GET[ 'user_id' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( $user = get_userdata( $user_id ) ) {
                    if ( isset( $user->$meta_key ) ) {
                        return sanitize_text_field( $user->$meta_key );
                    }
                }
            }
        }

        return $value;
    } // End query_string()


    /**
     * Populate cookie and session data
     *
     * @param string $value
     * @param object $field
     * @param string $name
     * @return string
     */
    public function cookies_and_sessions( $value, $field, $name ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();
        
        // Cookies
        if ( $HELPERS->str_starts_with( $name, 'cookie_' ) ) {
            $flavor = str_replace( 'cookie_', '', $name );
            if ( isset( $_COOKIE[ $flavor ] ) ) {
                return sanitize_text_field( wp_unslash( $_COOKIE[ $flavor ] ) );
            }
        }

        // Sessions
        if ( $HELPERS->str_starts_with( $name, 'session_' ) ) {
            $key = str_replace( 'session_', '', $name );
            if ( !isset( $_SESSION ) ) {
                session_start();
            }
            if ( isset( $_SESSION[ $key ] ) ) {
                return sanitize_text_field( wp_unslash( $_SESSION[ $key ] ) );
            }
        }

        return $value;
    } // End cookies_and_sessions()


    /**
     * Pre-populate quiz answers for admins only
     *
     * @param array $form
     * @return array
     */
    public function auto_fill_quiz_answers( $form ) {
        if ( is_plugin_active( 'gravityformsquiz/quiz.php' ) ) {
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
            if ( isset( $form_settings[ 'auto_fill_quiz_answers' ] ) && $form_settings[ 'auto_fill_quiz_answers' ] == 1 ) {
                if ( current_user_can( 'administrator' ) ) {

                    $field_types = [
                        'checkbox',
                        'radio',
                        'select'
                    ];

                    foreach( $form[ 'fields' ] as &$field )  {
                        if ( $field->type != 'quiz' ) {
                            continue;
                        }

                        $choices = $field->choices;
                        
                        foreach ( $choices as $key => &$choice ) {
                            if ( !in_array( $field->inputType, $field_types ) ) {
                                continue;
                            }

                            if ( isset( $choice[ 'gquizIsCorrect' ] ) && $choice[ 'gquizIsCorrect' ] == 1 ) {
                                $choice[ 'isSelected' ] = true;
                            }
                        }

                        $field->choices = $choices;
                    }
                }
            }
        }
        return $form;
    } // End auto_fill_quiz_answers()


    /**
     * Notice for admins when populating quiz answers
     *
     * @param string $form_string
     * @param array $form
     * @return string
     */
    public function auto_fill_quiz_answers_notice( $form_string, $form ) {
        if ( is_plugin_active( 'gravityformsquiz/quiz.php' ) ) {
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
            if ( isset( $form_settings[ 'auto_fill_quiz_answers' ] ) && $form_settings[ 'auto_fill_quiz_answers' ] == 1 ) {
                if ( current_user_can( 'administrator' ) ) {
                    $form_string = '<div class="gfadvtools-notice auto-fill-quiz-answers">
                        <strong>'.__( 'Quiz answers have been auto-filled for admins only.', 'gf-tools' ).'</strong> '.__( 'You can disable this in your form\'s Advanced Tools settings.', 'gf-tools' ).'
                    </div>'.$form_string;
                }
            }
        }
        return $form_string;
    } // End auto_fill_quiz_answers_notice()


    /**
     * Gravity Forms Populate timezones
     * USAGE: On form select field, enable "Allow field to be populated dynamically"
     * Add 'timezones' as Parameter Name
     *
     * @param array $form
     * @return array
     */
    public function populate_timezones( $form ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();

        foreach ( $form[ 'fields' ] as &$field ) {
            if ( $field->type == 'select' && $field->inputName == 'timezones' ) {

                $timezones = DateTimeZone::listIdentifiers( DateTimeZone::ALL );

                $choices[] = [ 'text' => '', 'value' => '' ];

                foreach ( $timezones as $timezone ) {               
                    $time = $HELPERS->convert_date_to_wp_timezone( null, 'g:i A', $timezone );
                    $choices[] = [ 'text' => $timezone.' ('.$time.')', 'value' => $timezone ];
                }

                $field->choices = $choices;
            }
        }
        return $form;
    } // End populate_timezones()


    /**
     * Populate users
     * USAGE: On form select field, enable "Allow field to be populated dynamically"
     * Add 'users' or 'users_[role]' as Parameter Name
     *
     * @param array $form
     * @return array
     */
    public function populate_users( $form ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();

        foreach ( $form[ 'fields' ] as &$field ) {
            $input_name = $field->inputName;

            if ( $field->type == 'select' && $HELPERS->str_starts_with( $input_name, 'users' ) ) {

                $choices = [];

                // By role only
                if ( $HELPERS->str_starts_with( $input_name, 'users_' ) ) {
                    $role = str_replace( 'users_', '', $input_name );
                    $args = [
                        'role'    => $role,
                        'orderby' => 'display_name',
                        'order'   => 'ASC',
                        'fields'  => [ 'ID', 'display_name' ],
                    ];
                    $users = get_users( $args );

                // All users
                } else {
                    $args = [
                        'orderby' => 'display_name',
                        'order'   => 'ASC',
                        'fields'  => [ 'ID', 'display_name' ],
                    ];
                    $users = get_users($args);
                }

                // Add an empty choice option
                $choices[] = [ 'text' => '', 'value' => '' ];

                // Populate choices with users
                foreach ( $users as $user ) {
                    $choices[] = [ 'text' => $user->display_name, 'value' => $user->ID ];
                }

                $field->choices = $choices;
            }
        }
        return $form;
    } // End populate_users()
}