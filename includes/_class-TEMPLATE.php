<?php
/**
 * Description of class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_CLASS_NAME {

    /**
     * Store the plugin settings here for the rest of the stuff
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
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings = false, $form_id = false ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
        $this->form_settings = isset( $form_settings ) ? $form_settings : []; 
        $this->form_id = isset( $form_id ) ? $form_id : false;

        // Example
        if ( isset( $plugin_settings[ 'setting_name' ] ) && $plugin_settings[ 'setting_name' ] == 1 ) {
            // add_filter( 'gform_settings_display_license_details', '__return_false' );
        }

	} // End __construct()


    /**
     * This is an example of using the plugin settings value inside the function
     *
     * @param int|float $position
     * @return int|float
     */
    public function example( $param ) {
        if ( $value = $this->plugin_settings[ 'example' ] ) {
            return $value;
        }
        return $param;
    } // End example()
}