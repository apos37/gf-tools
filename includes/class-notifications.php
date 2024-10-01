<?php
/**
 * Notifications class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Notifications {

    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Disable Default Admin Notifications Automatically Generated for New Forms
        if ( isset( $plugin_settings[ 'default_admin_notifications' ] ) && $plugin_settings[ 'default_admin_notifications' ] == 1 ) {
            add_filter( 'gform_default_notification', '__return_false' );
        }

        // Format the TO Email Address Properly to Improve Spam Score
        if ( isset( $plugin_settings[ 'format_to' ] ) && $plugin_settings[ 'format_to' ] == 1 ) {
            add_filter( 'gform_format_email_to', '__return_true' );
        }

        // Admin email example
        add_filter( 'gform_notification_settings_fields', [ $this, 'admin_email_example' ], 10, 3 );

        // Allow {user:user_email} to be used in the send to email field
        add_filter( 'gform_is_valid_notification_to', [ $this, 'validate_to_email' ], 10, 4 );

        // Allow Text and Select Fields in "Send To" > "Select a Field" Dropdown
        add_filter( 'gform_email_fields_notification_admin', [ $this, 'add_field_types_to_email_list' ], 10, 2 );

        // JQuery
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End __construct()


    /**
     * Admin email example
     *
     * @param array $fields
     * @param object $notification
     * @param array $form
     * @return void
     */
    public function admin_email_example( $fields, $notification, $form ) {
        foreach ( $fields[0][ 'fields' ] as &$field ) {
            if ( rgar( $field, 'name' ) !== 'from' ) {
                continue;
            }
     
            $field[ 'label' ] = __( 'From Email — <code>{admin_email}</code>:', 'gf-tools' ).' "'.get_bloginfo( 'admin_email' ).'"';
        }
        return $fields;
    } // End admin_email_example()


    /**
     * Allow {user:user_email} to be used in the send to email field
     *
     * @param boolean $is_valid
     * @param string $to_type
     * @param string $to_email
     * @param string $to_field
     * @return boolean
     */
    public function validate_to_email( $is_valid, $to_type, $to_email, $to_field ) {
        if ( $to_email == '{user:user_email}' ) {
            return true;
        }
     
        return $is_valid;
    } // End validate_to_email()


    /**
     * Notice for admins when disabling required forms
     *
     * @param array $field_list
     * @param array $form
     * @return string
     */
    public function add_field_types_to_email_list( $field_list, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'allow_text_fields_email' ] ) && $form_settings[ 'allow_text_fields_email' ] == 1 ) {
            foreach ( $form[ 'fields' ] as $field ) {
                if ( $field->type == 'text' || $field->type == 'select' ) {
                    $field_list[] = $field;
                }
            }
        }
        return $field_list;
    } // End add_field_types_to_email_list()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'toplevel_page_gf_edit_forms' ) {
            return;
        }

        // Shortcode generator for both notifications and confirmations
        if ( wp_script_is( 'gfadvtools_shortcode_generator', 'enqueued' ) ) {
            $form_id = isset( $_GET[ 'id' ] ) ? absint( $_GET[ 'id' ] ) : 0 ; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $form_id ) {
                if ( $form = GFAPI::get_form( $form_id ) ) {
                    
                    $fields = [];
                    foreach ( $form[ 'fields' ] as $field ) {
                        $fields[] = [
                            'id'    => $field->id,
                            'label' => $field->label
                        ];
                    }

                    $cid = isset( $_GET[ 'cid' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'cid' ] ) ) != ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $nid = isset( $_GET[ 'nid' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'nid' ] ) ) != ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                    wp_localize_script( 'gfadvtools_shortcode_generator', 'gfat_shortcode_generator', [
                        'fields'        => $fields,
                        'text'          => [
                            'btn'            => __( 'Conditional Shortcode', 'gf-tools' ),
                            'select_a_field' => __( 'Select a Field', 'gf-tools' ),
                            'is'             => __( 'is', 'gf-tools' ),
                            'isnot'          => __( 'is not', 'gf-tools' ),
                            'greater_than'   => __( 'greater than', 'gf-tools' ),
                            'less_than'      => __( 'less than', 'gf-tools' ),
                            'contains'       => __( 'contains', 'gf-tools' ),
                            'starts_with'    => __( 'starts with', 'gf-tools' ),
                            'ends_with'      => __( 'ends with', 'gf-tools' ),
                            'insert'         => __( 'Insert', 'gf-tools' ),
                            'content'        => __( 'Content you would like to conditionally display.', 'gf-tools' ),
                            'fill_out'       => __( 'Please fill out all fields.', 'gf-tools' ),
                        ],
                        'add_generator' => $cid || $nid
                    ] );
                }
            }
        }
    } // End enqueue_scripts()
}