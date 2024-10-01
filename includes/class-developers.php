<?php
/**
 * Developers class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Developers {

    /**
     * Store the settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings;
    public $form_settings;

    
    /**
     * Form ID
     *
     * @var string
     */
    public $form_id;


    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_debug_this';


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings, $form_id ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
        $this->form_settings = isset( $form_settings ) ? $form_settings : [];
        $this->form_id = isset( $form_id ) ? $form_id : false;

        // Add "Debug Form/Entry" Links to Forms and Entries
        if ( isset( $plugin_settings[ 'view_object_links' ] ) && $plugin_settings[ 'view_object_links' ] == 1 ) {
            add_filter( 'gform_toolbar_menu', [ $this, 'toolbar' ], 10, 2 );

            // Ajax
            add_action( 'wp_ajax_get_object_array', [ $this, 'ajax_get_object_array' ] );
            add_action( 'wp_ajax_nopriv_get_object_array', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );

            // JQuery
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        }

        // Write All Gravity Forms Log Messages to System Debug Log
        if ( isset( $plugin_settings[ 'use_debug_log' ] ) && $plugin_settings[ 'use_debug_log' ] == 1 ) {
            add_filter( 'gform_logging_message', [ $this, 'use_debug_log' ], 10, 5 );
        }

        // Add Your Own Custom Fields to the Form Settings Page
        add_filter( 'gform_form_settings_fields', [ $this, 'custom_form_settings' ], 10, 2 );

	} // End __construct()


    /**
     * Add "Debug Form/Entry" Links to Forms
     *
     * @param array $position
     * @param int $form_id
     * @return array
     */
    public function toolbar( $menu_items, $form_id ) {
        $entry_id = isset( $_GET[ 'lid' ] ) ? absint( $_GET[ 'lid' ] ) : false; // phpcs:ignore
        if ( $entry_id ) {
            $menu_items[ 'gfat_debug_entry' ] = [
                'label'       => __( 'Entry ID: ', 'gf-tools' ).$entry_id,
                'title'       => __( 'Click to view entry object', 'gf-tools' ),
                'url'         => '#',
                'menu_class'  => 'gfat_debug_entry_link',
                'capabilities'=> [ 'gravityforms_edit_forms' ],
                'priority'    => 2
            ];
        }

        $menu_items[ 'gfat_debug_form' ] = [
            'label'       => __( 'Form ID: ', 'gf-tools' ).$form_id,
            'title'       => __( 'Click to view form object', 'gf-tools' ),
            'url'         => '#',
            'menu_class'  => 'gfat_debug_form_link',
            'capabilities'=> [ 'gravityforms_edit_forms' ],
            'priority'    => 1
        ];     
        return $menu_items;
    } // End toolbar()


    /**
     * Write All Gravity Forms Log Messages to System Debug Log
     *
     * @param string $message
     * @param [type] $message_type
     * @param [type] $plugin_setting
     * @param [type] $log
     * @param [type] $GFLogging
     * @return false
     */
    public function use_debug_log( $message, $message_type, $plugin_setting, $log, $GFLogging ) {
        error_log( $message );
        return false;
    } // End use_debug_log()


    /**
     * Get the array data
     *
     * @return void
     */
    public function ajax_get_object_array() {
        // Verify nonce
        if ( isset( $_REQUEST[ 'nonce' ] ) && !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
    
        // Get the IDs
        $type = isset( $_REQUEST[ 'type' ] ) ? sanitize_key( $_REQUEST[ 'type' ] ) : false;
        $id = isset( $_REQUEST[ 'id' ] ) ? absint( $_REQUEST[ 'id' ] ) : false;

        // Validate
        if ( $type && ( $type == 'form' || $type == 'entry' ) && $id ) {

            if ( $type == 'form' && $form = GFAPI::get_form( $id ) ) {

                $field_table = '<table class="gfat-debug-table">
                    <tr>
                        <th>Field ID</th>
                        <th>Field Label</th>
                        <th>Field Type</th>
                        <th>Visibility</th>
                    </tr>';

                    foreach ( $form[ 'fields' ] as $field ) {
                        if ( $field->visibility == 'hidden' || $field->type == 'hidden' ) {
                            $incl_hidden = 'Hidden';
                        } else {
                            $incl_hidden = '';
                        }

                        $field_table .= '<tr>
                            <td>'.$field->id.'</td>
                            <td>'.$field->label.'</td>
                            <td>'.$field->type.'</td>
                            <td>'.$incl_hidden.'</td>
                        </tr>';
                    }

                $field_table .= '</table>';
                $result[ 'form_id' ] = $id;
                $result[ 'data' ] = $field_table.'<br><br><h2>Form Object</h2>'.print_r( $form, true );
                $result[ 'type' ] = 'success';

            } elseif ( $type == 'entry' && $entry = GFAPI::get_entry( $id ) ) {
                $form_id = $entry[ 'form_id' ];
                $form = GFAPI::get_form( $form_id );

                $field_table = '<table class="gfat-debug-table">
                    <tr>
                        <th>Field ID</th>
                        <th>Field Label</th>
                        <th>Field Type</th>
                        <th>Visibility</th>
                    </tr>';

                    foreach ( $form[ 'fields' ] as $field ) {
                        if ( $field->visibility == 'hidden' || $field->type == 'hidden' ) {
                            $incl_hidden = 'Hidden';
                        } else {
                            $incl_hidden = '';
                        }

                        $field_table .= '<tr>
                            <td>'.$field->id.'</td>
                            <td>'.$field->label.'</td>
                            <td>'.$field->type.'</td>
                            <td>'.$incl_hidden.'</td>
                        </tr>';
                    }

                $field_table .= '</table>';

                $result[ 'form_id' ] = $form_id;
                $result[ 'data' ] = $field_table.'<br><br><h2>Entry Object</h2>'.print_r( $entry, true );
                $result[ 'type' ] = 'success';

            } else {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'Oopsie daisy! Could not fetch object.';
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No type, incorrect type, or no ID.';
        }
        
        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_change_status()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'toplevel_page_gf_edit_forms' && $hook !== 'forms_page_gf_entries' ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_debugging', 'enqueued' ) ) {
            $entry_id = isset( $_GET[ 'lid' ] ) ? absint( $_GET[ 'lid' ] ) : false; // phpcs:ignore
            wp_localize_script( 'gfadvtools_debugging', 'gfat_debugging', [
                'text'     => [
                    'something_went_wrong' => __( 'Uh oh! Something went wrong. ', 'gf-tools' ),
                    'form_saved'           => __( 'Data is updated when the form is saved.', 'gf-tools' ),
                    'form_id'              => __( 'Form ID', 'gf-tools' ),
                ],
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( $this->nonce ),
                'form_id'  => $this->form_id,
                'entry_id' => $entry_id
            ] );
        }
    } // End enqueue_scripts()


    /**
     * Add Your Own Custom Fields to the Form Settings Page 
     *
     * @param array $fields
     * @param array $form
     * @return array
     */
    public function custom_form_settings( $fields, $form ) {       
        $custom_fields = isset( $this->plugin_settings[ 'custom_form_settings' ] ) ? $this->plugin_settings[ 'custom_form_settings' ] : false;

        // Remove fields with empty values
        if ( !empty( $custom_fields ) ) {
            foreach ( $custom_fields as $key => &$custom_field ) {
                $label = sanitize_text_field( $custom_field[ 'label' ] );
                $meta_key = sanitize_key( $custom_field[ 'meta_key' ] );
                if ( !$label || !$meta_key ) {
                    unset( $custom_fields[ $key ] );
                }
            }
        }

        // Add them
        if ( !empty( $custom_fields ) ) {

            // Add section only if have fields
            $fields[ 'gfat_custom_fields' ] = [
                'title'  => __( 'Advanced Tools', 'gf-tools'),
                'fields' => []
            ];

            // Add the fields
            foreach ( $custom_fields as $field ) {
                $field_type = sanitize_key( $field[ 'field_type' ] );         
                $fields[ 'gfat_custom_fields' ][ 'fields' ][] = [
                    'type'  => ( $field_type == 'checkbox' ) ? 'toggle' : $field_type, 
                    'name'  => sanitize_key( $field[ 'meta_key' ] ),
                    'label' => sanitize_text_field( $field[ 'label' ] )
                ];
            }
        }
        // dpr( $fields, null, true );
        
        return $fields;
    } // End custom_form_settings()
}