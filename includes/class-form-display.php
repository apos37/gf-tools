<?php
/**
 * Form display class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Form_Display {

    /**
     * Store the plugin settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings;


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];

        // Hide the form if not scheduled
        add_filter( 'gform_pre_render', [ $this, 'scheduled' ] );

        // Remove the submit button
        add_filter( 'gform_submit_button', [ $this, 'remove_submit_button' ], 10, 2 );

        // Change the submit input to a button
        add_filter( 'gform_next_button', [ $this, 'submit_input_to_button' ], 10, 2 );
        add_filter( 'gform_previous_button', [ $this, 'submit_input_to_button' ], 10, 2 );
        add_filter( 'gform_submit_button', [ $this, 'submit_input_to_button' ], 10, 2 );

        // Disable the required fields for admins only
        add_filter( 'gform_pre_render', [ $this, 'disable_required' ] );
        add_filter( 'gform_pre_validation', [ $this, 'disable_required' ] );
        add_filter( 'gform_get_form_filter', [ $this, 'disable_required_notice' ], 10, 2 );

        // Review page
        add_filter( 'gform_review_page', [ $this, 'review_page' ], 10, 3 );

        // Remove query strings from url if in settings
        add_filter( 'gform_get_form_filter', [ $this, 'remove_qs' ], 10, 2 );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End __construct()


    /**
     * Hide the form if it is not scheduled
     * If this no longer works, may also want to try: https://docs.gravityforms.com/gform_get_form_filter/
     *
     * @param array $form
     * @return array|false
     */
    public function scheduled( $form ) {
        $start_date = false;
        $start_time = false;
        $end_date = false;
        $end_time = false;

        $HELPERS = new GF_Advanced_Tools_Helpers();
        
        // Retrieve, sanitize and convert form settings
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'start_date' ] ) && $form_settings[ 'start_date' ] != '' ) {
            $start_date = sanitize_text_field( $form_settings[ 'start_date' ] );
        }
        if ( isset( $form_settings[ 'start_time' ] ) && $form_settings[ 'start_time' ] != '' ) {
            $start_time = sanitize_text_field( $form_settings[ 'start_time' ] );
        }
        if ( isset( $form_settings[ 'end_date' ] ) && $form_settings[ 'end_date' ] != '' ) {
            $end_date = sanitize_text_field( $form_settings[ 'end_date' ] );
        }
        if ( isset( $form_settings[ 'end_time' ] ) && $form_settings[ 'end_time' ] != '' ) {
            $end_time = sanitize_text_field( $form_settings[ 'end_time' ] );
        }
        
        if ( $start_date || $start_time || $end_date || $end_time ) {
            $local_date = $HELPERS->convert_date_to_wp_timezone( null, 'Y-m-d' );

            $start_datetime = false;
            $end_datetime = false;
            
            if ( $start_date && !$start_time ) {
                $start_time = '00:00:00';
            }
            if ( $end_date && !$end_time ) {
                $end_time = '23:59:59';
            }
            if ( $start_time && !$start_date ) {
                $start_date = $local_date;
            }
            if ( $end_time && !$end_date ) {
                $end_date = $local_date;
            }
            
            if ( $start_date && $start_time ) {
                $start_datetime = $start_date.' '.$start_time;
            }
            if ( $end_date && $end_time ) {
                $end_datetime = $end_date.' '.$end_time;
            }
            
            $current_datetime = $HELPERS->convert_date_to_wp_timezone( null, 'Y-m-d H:i:s' );
            
            $message_to_display = false;
            if ( $start_datetime && $end_datetime ) {
                if ( $current_datetime < $start_datetime ) {
                    $message_to_display = 'before';
                } elseif ( $current_datetime > $end_datetime ) {
                    $message_to_display = 'after';
                }
            } elseif ( $start_datetime ) {
                if ( $current_datetime < $start_datetime ) {
                    $message_to_display = 'before';
                }
            } elseif ( $end_datetime ) {
                if ( $current_datetime > $end_datetime ) {
                    $message_to_display = 'after';
                }
            }
            
            if ( $message_to_display ) {
                if ( isset( $form_settings[ $message_to_display.'_message' ] ) && $form_settings[ $message_to_display.'_message' ] != '' ) {
                    $my_message = sanitize_text_field( $form_settings[ $message_to_display.'_message' ] );
                    add_filter( 'gform_form_not_found_message', function( $message, $id ) use ( $my_message ) {
                        return $my_message;
                    }, 10, 2 );
                }
                return false;
            }
        }
        
        return $form;
    } // End scheduled()


    /**
     * Remove the submit button
     *
     * @param string $form_tag
     * @param array $form
     * @return string
     */
    public function remove_submit_button( $button, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'remove_submit_btn' ] ) && $form_settings[ 'remove_submit_btn' ] == 1 ) {
            return '';
        }
        return $button;
    } // End remove_submit_button()


    /**
     * Change the submit input to a button
     *
     * @param [type] $button
     * @param [type] $form
     * @return void
     */
    public function submit_input_to_button( $button, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'convert_submit_btn' ] ) && $form_settings[ 'convert_submit_btn' ] == 1 ) {
            $dom = new DOMDocument();
            $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $button );
            $input = $dom->getElementsByTagName( 'input' )->item(0);
            $new_button = $dom->createElement( 'button' );
            $new_button->appendChild( $dom->createTextNode( $input->getAttribute( 'value' ) ) );
            $input->removeAttribute( 'value' );
            foreach( $input->attributes as $attribute ) {
                $new_button->setAttribute( $attribute->name, $attribute->value );
            }
            $input->parentNode->replaceChild( $new_button, $input );
            return $dom->saveHtml( $new_button );
        }
        return $button;
    } // End submit_input_to_button()


    /**
     * Disable the required fields for admins only
     *
     * @param array $form
     * @return array
     */
    public function disable_required( $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'disable_required_fields' ] ) && $form_settings[ 'disable_required_fields' ] == 1 ) {
            if ( current_user_can( 'administrator' ) ) {
                foreach ( $form[ 'fields' ] as &$field ) {
                    $field->isRequired = false;
                }
            }
        }
        return $form;
    } // End disable_required()


    /**
     * Notice for admins when disabling required forms
     *
     * @param string $form_string
     * @param array $form
     * @return string
     */
    public function disable_required_notice( $form_string, $form ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'disable_required_fields' ] ) && $form_settings[ 'disable_required_fields' ] == 1 ) {
            if ( current_user_can( 'administrator' ) ) {
                $form_string = '<div class="gfadvtools-notice required-fields-disabled">
                    <strong>'.__( 'Required fields have been disabled for admins only.', 'gf-tools' ).'</strong> '.__( 'You can re-enable them in your form\'s Advanced Tools settings.', 'gf-tools' ).'
                </div>'.$form_string;
            }
        }
        return $form_string;
    } // End disable_required_notice()


    /**
     * Add a review page
     *
     * @param string $form_tag
     * @param array $form
     * @return string
     */
    public function review_page( $review_page, $form, $entry ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'enable_review_page' ] ) && $form_settings[ 'enable_review_page' ] == 1 ) {

            $review_page[ 'is_enabled' ] = true;

            if ( $entry ) {
                
                if ( isset( $form_settings[ 'review_page_content' ] ) && $form_settings[ 'review_page_content' ] != '' ) {
                    $content = wp_kses_post( $form_settings[ 'review_page_content' ] );
                } else {
                    $content = __( 'Please review your results before submitting.', 'gf-tools' ).'<br>{all_fields}';
                }
                $review_page[ 'content' ] = '<div class="gfadvtools-review-form">
                    '.GFCommon::replace_variables( $content, $form, $entry ).'
                </div>';
            }
        }
        return $review_page;
    } // End review_page()


    /**
     * Remove query string parameters from the URL
     *
     * @param string $form_string
     * @param array $form
     * @return string
     */
    public function remove_qs( $form_string, $form ) {
        // Settings
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( $form_settings && sanitize_text_field( $form_settings[ 'remove_qs' ] ) ) {
            
            $params = sanitize_text_field( $form_settings[ 'remove_qs' ] );
            $qs = preg_split( '/[\s,]+/', $params, -1, PREG_SPLIT_NO_EMPTY );
            $qs = array_map( 'trim', $qs );

            // Get the current title
            $page_title = get_the_title();

            // Helper
            $HELPERS = new GF_Advanced_Tools_Helpers();

            // Get the current url without the query string
            if ( !empty( $qs ) ) {
                $new_url = remove_query_arg( $qs, $HELPERS->get_current_url() );
            } else {
                $new_url = $HELPERS->get_current_url( false );
            }
        
            // Add the script
            $script = '<script id="gfat-remove-qs">
            if ( history.pushState ) {
                var url = window.location.href;
                var obj = { Title: "'.$page_title.'", Url: "'.$new_url.'" };
                window.history.pushState(obj, obj.Title, obj.Url);
            }
            </script>';
        
            // Add it after the form
            return $form_string.$script;
        }
        return $form_string;
    } // End remove_qs()


    /**
     * Enqueue scripts
     * Also used by class-shortcodes.php
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( is_admin() ) {
            return;
        }

        $handle = 'gfadvtools_form_display';
        // wp_register_script( $handle.'_script', GFADVTOOLS_PLUGIN_DIR.'includes/js/form-display.js', [ 'jquery' ] );
        // // wp_localize_script( $handle.'_script', $handle, [ 'ajaxurl' => admin_url( 'admin-ajax.php' ) ] );        
        // wp_enqueue_script( 'jquery' );
        // wp_enqueue_script( $handle.'_script' );

        wp_enqueue_style( $handle, GFADVTOOLS_PLUGIN_DIR.'includes/css/form-display.css', [], time() );
    } // End enqueue_scripts()
}