<?php
/**
 * Customize Gravity Forms.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Customize {

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

        // Position of the Gravity Forms’ “Forms” menu in the WordPress admin menu.
        add_filter( 'gform_menu_position', [ $this, 'menu_position' ] );

	} // End __construct()


    /**
     * Position of the Gravity Forms’ “Forms” menu in the WordPress admin menu.
     *
     * @param int|float $position
     * @return int|float
     */
    public function menu_position( $position ) {
        if ( isset( $this->plugin_settings[ 'menu_position' ] ) && $pos = absint( $this->plugin_settings[ 'menu_position' ] ) ) {
            return $pos;
        }
        return $position;
    } // End menu_position()
}