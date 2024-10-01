<?php
/**
 * Entries class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Entries {

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

        // Prevent the IP Address from Being Saved
        add_filter( 'gform_ip_address', [ $this, 'omit_ip_address' ] );

        // Auto-Delete Previous Entries for Logged-In Users
        add_action( 'gform_after_submission', [ $this, 'delete_duplicates' ], 10, 2 );

        // Add entry meta
        add_filter( 'gform_entry_meta', [ $this, 'add_entry_meta' ], 10, 2 );
        add_action( 'gform_entry_created', [ $this, 'add_user_meta' ], 10, 2 );
        add_action( 'gform_entry_created', [ $this, 'add_associated_page_qs' ], 10, 2 );

	} // End __construct()


    /**
     * Prevent the IP Address from Being Saved
     *
     * @param string $ip
     * @return void
     */
    public function omit_ip_address( $ip ) {
        $plugin_settings = (new GF_Advanced_Tools)->get_plugin_settings();
        if ( isset( $plugin_settings[ 'prevent_ip' ] ) && $plugin_settings[ 'prevent_ip' ] == 1 ) {
            return '';
        }
    } // End omit_ip_address()


    /**
     * Auto-Delete Previous Entries for Logged-In Users
     *
     * @param array $entry
     * @param array $form
     * @return void
     */
    public function delete_duplicates( $entry, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'auto_delete_previous_entries' ] ) && $form_settings[ 'auto_delete_previous_entries' ] != '' ) {
            $enabled = sanitize_text_field( $form_settings[ 'auto_delete_previous_entries' ] );

            // If we're only deleting previous quiz entries when they passed and they did not pass, stop
            if ( $enabled == 'quiz-only' && isset( $entry[ 'gquiz_is_pass' ] ) && $entry[ 'gquiz_is_pass' ] != 1 ) {
                return;
            }

            $previous_entries = (new GF_Advanced_Tools_Helpers)->get_entries_for_user( $form[ 'id' ], $entry[ 'created_by' ] );
            if ( !empty( $previous_entries ) ) {

                foreach ( $previous_entries as $previous_entry ) {
                    if ( $previous_entry[ 'id' ] == $entry[ 'id' ] ) {
                        continue;
                    }

                    // For quiz only option, if the previous entry is not a quiz entry, skip it
                    if ( $enabled == 'quiz-only' && !isset( $previous_entry[ 'gquiz_is_pass' ] ) ) {
                        continue;
                    }
                    
                    GFAPI::delete_entry( $previous_entry[ 'id' ] );
                }
            }
        }
    } // End delete_duplicates()


    /**
     * Get the form settings user meta keys to add to an entry
     *
     * @param array $form
     * @return array
     */
    public function get_form_user_meta_keys_to_add( $form ) {
        $meta_keys = [];

        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'add_user_meta' ] ) && $form_settings[ 'add_user_meta' ] != 1 ) {
            $meta_keys_string = sanitize_text_field( $form_settings[ 'add_user_meta' ] );
            
            if ( strpos( $meta_keys_string, ',' ) !== false ) {
                $meta_keys = explode( ',', $meta_keys_string );
            } else {
                $meta_keys = [ $meta_keys_string ];
            }
            
            $meta_keys = array_map( 'trim', $meta_keys );
            $meta_keys = array_filter( $meta_keys );
        }

        return $meta_keys;
    } // End get_form_user_meta_keys_to_add()


    /**
     * Get the field user meta keys
     *
     * @param array $form
     * @return array
     */
    public function get_field_user_meta_keys_to_add( $form ) {
        $meta_keys = [];

        foreach ( $form[ 'fields' ] as $field ) {
            if ( isset( $field[ 'update_user_meta' ] ) && sanitize_key( $field[ 'update_user_meta' ] ) != '' ) {
                $meta_keys[ $field[ 'id' ] ] = trim( sanitize_key( $field[ 'update_user_meta' ] ) );
            }
        }

        return $meta_keys;
    } // End get_field_user_meta_keys_to_add()


    /**
     * Get the associated_page_qs
     *
     * @param array $form
     * @return array
     */
    public function get_associated_page_qs( $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
            return sanitize_text_field( $form_settings[ 'associated_page_qs' ] );
        }
        return false;
    } // End get_associated_page_qs()


    /**
     * Add entry meta
     *
     * @param array $entry_meta
     * @param integer $form_id
     * @return array
     */
    public function add_entry_meta( $entry_meta, $form_id ) {
        $form = GFAPI::get_form( $form_id );
        $form_meta_keys = $this->get_form_user_meta_keys_to_add( $form );
        $field_meta_keys = $this->get_field_user_meta_keys_to_add( $form );
        $meta_keys = array_merge( $form_meta_keys, $field_meta_keys );

        if ( !empty( $meta_keys ) ) {
            foreach ( $meta_keys as $meta_key ) {
                $entry_meta[ $meta_key ] = [
                    'label'                      => $meta_key,
                    'is_numeric'                 => false,
                    'update_entry_meta_callback' => [ $this, 'default_entry_meta' ],
                    'is_default_column'          => false
                ];
            }
        }

        $entry_meta[ 'connected_post_id' ] = [
            'label'                      => __( 'Connected Post ID', 'gf-tools' ),
            'is_numeric'                 => false,
            'update_entry_meta_callback' => [ $this, 'default_entry_meta' ],
            'is_default_column'          => false
        ];

        return $entry_meta;
    } // End add_entry_meta()

    
    /**
     * Default value upon form submission or editing an entry
     *
     * @param [ type ] $key
     * @param [ type ] $entry
     * @param [ type ] $form
     * @return void
     */
    public function default_entry_meta( $key, $entry, $form ) {
        return '';
    } // End default_entry_meta()


    /**
     * Add user meta to the entry
     *
     * @param array $entry
     * @param array $form
     * @return void
     */
    public function add_user_meta( $entry, $form ) {
        $user_id = $entry[ 'created_by' ];
        if ( !$user_id ) {
            return;
        }

        // Form settings
        $form_meta_keys = $this->get_form_user_meta_keys_to_add( $form );
        if ( !empty( $form_meta_keys ) ) {
            $user = get_userdata( $user_id );

            foreach ( $form_meta_keys as $form_meta_key ) {
                $form_meta_value = isset( $user->$form_meta_key ) ? sanitize_text_field( $user->$form_meta_key ) : '';
                GFAPI::update_entry_field( $entry[ 'id' ], $form_meta_key, $form_meta_value );
            }
        }

        // Field settings
        $field_meta_keys = $this->get_field_user_meta_keys_to_add( $form );
        if ( !empty( $field_meta_keys ) ) {

            foreach ( $field_meta_keys as $field_id => $field_meta_key ) {
                $field_meta_value = isset( $entry[ $field_id ] ) ? sanitize_text_field( $entry[ $field_id ] ) : '';
                update_user_meta( $user_id, $field_meta_key, $field_meta_value );
            }
        }
    } // End add_user_meta()


    /**
     * Add associated_page_qs
     * 
     * @param array $entry
     * @param array $form
     * @return void
     */
    public function add_associated_page_qs( $entry, $form ) {
        $associated_page_qs = $this->get_associated_page_qs( $form );
        if ( is_numeric( $associated_page_qs ) ) {
            $connected_post_id = $associated_page_qs;
        } elseif ( isset( $_GET[ $associated_page_qs ] ) && absint( $_GET[ $associated_page_qs ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $connected_post_id = absint( $_GET[ $associated_page_qs ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        } else {
            $connected_post_id = false;
        }

        GFAPI::update_entry_field( $entry[ 'id' ], 'connected_post_id', $connected_post_id );
    } // End add_associated_page_qs()
}