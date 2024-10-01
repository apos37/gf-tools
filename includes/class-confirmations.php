<?php
/**
 * Confirmations class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Confirmations {

    /**
	 * Constructor
	 */
	public function __construct() {

        //  Disable automatically scrolling to the confirmation text or validation message upon submission
        add_filter( 'gform_confirmation_anchor', [ $this, 'anchor' ], 10, 2 );

	} // End __construct()


    /**
     * Disable automatically scrolling to the confirmation text or validation message upon submission
     *
     * @param boolean|int $anchor
     * @param array $form
     * @return boolean
     */
    public function anchor( $anchor, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'disable_confirmations_anchor' ] ) && $form_settings[ 'disable_confirmations_anchor' ] == 1 ) {
            return false;
        }
    } // End anchor()
    
}