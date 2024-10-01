<?php
/**
 * Forms table class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Forms_Table {

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

        // Track When and Who Created and Modified Forms
        if ( isset( $plugin_settings[ 'created_modified' ] ) && $plugin_settings[ 'created_modified' ] == 1 ) {
            add_action( 'gform_after_save_form', [ $this, 'log_form_saved' ], 10, 2 );
            add_filter( 'gform_form_list_columns', [ $this, 'forms_columns' ], 10, 1 );
            add_action( 'gform_form_list_column_log', [ $this, 'forms_column_content' ] );
        }

        // Add an Action Link to the Forms Table to Copy Form Shortcode to Clipboard
        if ( isset( $plugin_settings[ 'copy_shortcode_action' ] ) && $plugin_settings[ 'copy_shortcode_action' ] == 1 ) {
            add_action( 'gform_form_actions', [ $this, 'copy_shortcode_link' ], 10, 4 );
        }

        // Disable View Counter
        if ( isset( $plugin_settings[ 'disable_view_count' ] ) && $plugin_settings[ 'disable_view_count' ] == 1 ) {
            add_filter( 'gform_disable_view_counter', '__return_true' );
            add_filter( 'gform_form_list_columns', [ $this, 'remove_view_count_column' ], 10, 1 );
        }

        // Hide Conversion
        if ( isset( $plugin_settings[ 'hide_conversion' ] ) && $plugin_settings[ 'hide_conversion' ] == 1 ) {
            add_filter( 'gform_form_list_columns', [ $this, 'hide_conversion' ], 10, 1 );
        }

	} // End __construct()


    /**
     * Add who and when form was saved
     *
     * @param array $form
     * @param boolean $is_new
     * @return void
     */
    public function log_form_saved( $form, $is_new ) {
        // Get the current user
        $user_id = get_current_user_id();
        
        // Fields to update
        if ( $is_new ) {
            $form[ 'created_by' ] = $user_id;
            // date_created already exists in form object
        } else {
            $form[ 'modified_by' ] = $user_id;
            $form[ 'date_modified' ] = gmdate( 'Y-m-d H:i:s' );
        }

        // Gotta make it active or it will make it inactive by default
        $form[ 'is_active' ] = true;

        // Update the form meta
        GFAPI::update_form( $form );
    } // End log_form_saved()


    /**
     * Forms columns
     *
     * @param array $columns
     * @return array
     */
    public function forms_columns( $columns ) {
        $columns = array_merge( $columns, [
            'log' => esc_html__( 'Log', 'gf-tools' )
        ] );
        return $columns;
    } // End forms_columns()


    /**
     * Forms column content
     *
     * @param object $form
     * @return void
     */
    public function forms_column_content( $form ) {
        // Actions
        $actions = [];

        // Get the full form object
        $form = GFAPI::get_form( $form->id );
        
        // Created by
        if ( isset( $form[ 'created_by' ] ) ) {
            $created_by_id = absint( $form[ 'created_by' ] );
            $created_by_user = get_user_by( 'ID', $created_by_id );
            $incl_created_by = ' by '.$created_by_user->display_name;
        } else {
            $incl_created_by = '';
        }
        $actions[] = 'Created on '.gmdate( 'n/j/Y', strtotime( $form[ 'date_created' ] ) ).$incl_created_by;

        // Modified by
        if ( isset( $form[ 'date_modified' ] ) ) {
            if ( isset( $form[ 'modified_by' ] ) ) {
                $modified_by_id = absint( $form[ 'modified_by' ] );
                $modified_by_user = get_user_by( 'ID', $modified_by_id );
                $incl_modified_by = ' by '.$modified_by_user->display_name;
            } else {
                $incl_modified_by = '';
            }
            $actions[] = '<em>Last Modified on '.gmdate( 'n/j/Y', strtotime( $form[ 'date_modified' ] ) ).$incl_modified_by.'</em>';
        }

        // Return the actions
        if ( !empty( $actions ) ) {
            echo wp_kses( implode( '<br>', $actions ), [ 'br' => [], 'em' => [] ] );
        }
    } // End forms_column_content()


    /**
     * Copy shortcode action link
     *
     * @param array $actions
     * @param int $form_id
     * @return array
     */
    public function copy_shortcode_link( $actions, $form_id ) {
        $shortcode = '[gravityform id="'.$form_id.'" title="false" description="false"]';
        $actions[ 'copy_shortcode_link' ] = '<a class="copy-shortcode" href="#" data-shortcode="'.esc_attr( $shortcode ).'" title="'.esc_attr( $shortcode ).'">'.__( 'Copy Shortcode', 'gf-tools' ).'</a>';
        return $actions;
    } // End copy_shortcode_link()


    /**
     * Disable View Counter - remove form column
     *
     * @param array $columns
     * @return array
     */
    public function remove_view_count_column( $columns ) {
        unset( $columns[ 'view_count' ] );
        return $columns;
    } // End remove_view_count_column()


    /**
     * Hide conversion rate column
     *
     * @param array $columns
     * @return array
     */
    public function hide_conversion( $columns ) {
        unset( $columns[ 'conversion' ] );
        return $columns;
    } // End hide_conversion()
}