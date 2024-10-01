<?php
/**
 * Reports class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Reports {

    /**
     * Post type
     * 
     * @var string
     */ 
    public $post_type = 'gfat-reports';


    /**
     * Store the plugin settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings;


    /**
     * Nonces
     *
     * @var string
     */
    public $nonce_for_saving = 'gfat-save-report';
    public $nonce_for_preview = 'gfat-preview-report';
    public $nonce_for_export = 'gfat-export-report';


    /**
     * Meta keys
     *
     * @var string
     */
    public $meta_key_form_id = 'form_id';                   // int
    public $meta_key_order = 'entry_order';                 // string
    public $meta_key_orderby = 'entry_orderby';             // string
    public $meta_key_fields_page = 'fields_page';           // array
    public $meta_key_fields_export = 'fields_export';       // array
    public $meta_key_per_page = 'entries_per_page';         // int
    public $meta_key_export_btn_text = 'export_btn_text';   // string
    public $meta_key_date_format = 'date_format';           // string
    public $meta_key_roles = 'roles';                       // string
    public $meta_key_search_bar = 'search_bar';             // boolean
    public $meta_key_date_filter = 'date_filter';           // boolean
    public $meta_key_quarter_links = 'quarter_links';       // boolean
    public $meta_key_link_first_col = 'link_first_col';     // boolean


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];

        // Register the post type
        $this->register_post_type();

        // Fix the Manage link to show active
        add_filter( 'parent_file', [ $this, 'submenus' ] );

        // Redirect the admin list table page to our dashboard
        add_action( 'admin_head', [ $this, 'redirect_admin_table' ] );

        // Add an info box to the edit screen
        add_action( 'edit_form_after_title', [ $this, 'edit_screen_info_box' ] );

        // Add the meta box
        add_action( 'add_meta_boxes', [ $this, 'meta_boxes' ] );

        // Save the post data
        add_action( 'save_post', [ $this, 'save_post' ] );

        // Ajax
        add_action( 'wp_ajax_report_get_form_fields', [ $this, 'ajax_report_get_form_fields' ] );
        add_action( 'wp_ajax_nopriv_report_get_form_fields', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );

        // JQuery
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Front-end CSS
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        // Export
        if ( isset( $_POST[ 'gfat_report_entries_export' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->export();
        }

	} // End __construct()


    /**
     * Register the post type
     */
    public function register_post_type() {
        // Set the labels
        $labels = [
            'name'                  => _x( 'Reports', 'Post Type General Name', 'gf-tools' ),
            'singular_name'         => _x( 'Report', 'Post Type Singular Name', 'gf-tools' ),
            'menu_name'             => __( 'Reports', 'gf-tools' ),
            'name_admin_bar'        => __( 'Reports', 'gf-tools' ),
            'archives'              => __( 'Report Archives', 'gf-tools' ),
            'attributes'            => __( 'Report Attributes', 'gf-tools' ),
            'parent_item_colon'     => __( 'Parent Report:', 'gf-tools' ),
            'all_items'             => __( 'All Reports', 'gf-tools' ),
            'add_new_item'          => __( 'Add New Report', 'gf-tools' ),
            'add_new'               => __( 'Add New', 'gf-tools' ),
            'new_item'              => __( 'New Report', 'gf-tools' ),
            'edit_item'             => __( 'Edit Report', 'gf-tools' ),
            'update_item'           => __( 'Update Report', 'gf-tools' ),
            'view_item'             => __( 'View Report', 'gf-tools' ),
            'view_items'            => __( 'View Reports', 'gf-tools' ),
            'search_items'          => __( 'Search Reports', 'gf-tools' ),
            'not_found'             => __( 'Not found', 'gf-tools' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'gf-tools' ),
            'featured_image'        => __( 'Featured Image', 'gf-tools' ),
            'set_featured_image'    => __( 'Set featured image', 'gf-tools' ),
            'remove_featured_image' => __( 'Remove featured image', 'gf-tools' ),
            'use_featured_image'    => __( 'Use as featured image', 'gf-tools' ),
            'insert_into_item'      => __( 'Insert into report', 'gf-tools' ),
            'uploaded_to_this_item' => __( 'Uploaded to this report', 'gf-tools' ),
            'items_list'            => __( 'Report list', 'gf-tools' ),
            'items_list_navigation' => __( 'Report list navigation', 'gf-tools' ),
            'filter_items_list'     => __( 'Filter report list', 'gf-tools' ),
        ];
    
        // Set the CPT args
        $args = [
            'label'                 => __( 'Reports', 'gf-tools' ),
            'description'           => __( 'Reports', 'gf-tools' ),
            'labels'                => $labels,
            'supports'              => [],
            'taxonomies'            => [],
            'public'                => false,
            'show_ui'               => true,
            'show_in_menu'          => false,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'query_var'             => $this->post_type,
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'show_in_rest'          => false,
        ];
    
        // Register the CPT
        register_post_type( $this->post_type, $args );
    } // End register_post_type()


    /**
     * Fix the Manage link to show active
     *
     * @param string $parent_file
     * @return string
     */
    public function submenus( $parent_file ) {
        global $submenu_file, $current_screen;

        if ( $current_screen->post_type == $this->post_type ) {
            $submenu_file = 'gf-tools';
            $parent_file = 'gf_edit_forms';
        }
        
        return $parent_file;
    } // End submenus()


    /**
     * Redirect the admin list table
     *
     * @return void
     */
    public function redirect_admin_table() {
        $screen = get_current_screen();

        // Check if we're on the edit.php page for the custom post type
        if ( isset( $screen->post_type ) && $screen->post_type === $this->post_type && $screen->base === 'edit' ) {
            $redirect_url = gfadvtools_get_plugin_page_tab( 'reports' );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    } // End redirect_admin_table()


    /**
     * Add an info box to top of edit page
     *
     * @return void
     */    
    public function edit_screen_info_box() {
        global $post_type;
        if ( is_admin() && $this->post_type == $post_type ) {

            // Info box
            echo '<div class="gfat-info-box">
            '.wp_kses( __( '<strong>Instructions:</strong> Add a title for the report above, then choose a form and other options in the General Settings below. Under "Fields to Display on the Page," click on the fields you want to include in the report table. You may change the label once you select it by clicking on the "Edit" link in the field box. If you are including an export option, select the fields you want to export in the "Fields to Include in the CSV Export" section at the bottom. If you do not want an export option, simply do not select any fields. Once you are done, publish the report. Then copy the shortcode provided and paste it into the page you want to display your report on.', 'gf-tools' ), [ 'strong' => [], 'em' => [] ] ).'
        </div>';
        }
    } // End edit_screen_info_box()


    /**
     * Get the selected forms
     *
     * @param int $report_id
     * @return array
     */
    public function get_selected_form( $report_id ) {
        return absint( get_post_meta( $report_id, $this->meta_key_form_id, true ) );
    } // End get_selected_form()


    /**
     * Get the selected order
     *
     * @param int $report_id
     * @return array
     */
    public function get_selected_order( $report_id ) {
        $order = get_post_meta( $report_id, $this->meta_key_order, true );
        if ( $order ) {
            $order = array_map( 'sanitize_text_field', $order );
        } else {
            $order = [
                'page'   => 'DESC',
                'export' => 'DESC'
            ];
        }
        return $order;
    } // End get_selected_order() 


    /**
     * Get the selected orderby
     *
     * @param int $report_id
     * @return array
     */
    public function get_selected_orderby( $report_id ) {
        $orderby = get_post_meta( $report_id, $this->meta_key_orderby, true );
        if ( $orderby ) {
            $orderby = array_map( 'sanitize_text_field', $orderby );
        } else {
            $orderby = [
                'page'   => 'id',
                'export' => 'id'
            ];
        }
        return $orderby;
    } // End get_selected_orderby() 


    /**
     * Get selected fields
     *
     * @param string $type
     * @return array
     */
    public function get_selected_fields( $type, $report_id ) {
        $fields_meta_key = 'meta_key_fields_'.$type;
        $selected_fields = get_post_meta( $report_id, $this->$fields_meta_key, true );
        if ( !is_array( $selected_fields ) ) {
            $selected_fields = [];
        }

        $selected_fields = array_map( function( $fields ) {
            return array_map( 'sanitize_text_field', $fields );
        }, $selected_fields );

        return $selected_fields;
    } // End get_selected_fields()

    
    /**
     * Get the selected date format
     *
     * @param int $report_id
     * @return string
     */
    public function get_selected_date_format( $report_id ) {
        $value = sanitize_text_field( get_post_meta( $report_id, $this->meta_key_date_format, true ) );
        if ( !$value ) {
            $value = 'n/j/Y';
        }
        return $value;
    } // End get_selected_date_format() 


    /**
     * Get the entries per page
     *
     * @param int $report_id
     * @return int
     */
    public function get_entries_per_page( $report_id ) {
        $per_page = absint( get_post_meta( $report_id, $this->meta_key_per_page, true ) );
        $per_page = ( $per_page > 0 ) ? $per_page : 10; 
        return $per_page;
    } // End get_selected_entries_per_page() 


    /**
     * Get the export btn text
     *
     * @param int $report_id
     * @return string
     */
    public function get_export_btn_text( $report_id ) {
        $value = sanitize_text_field( get_post_meta( $report_id, $this->meta_key_export_btn_text, true ) );
        if ( !$value ) {
            $value = __( 'Export CSV', 'gf-tools' );
        }
        return $value;
    } // End get_export_btn_text() 


    /**
     * Get the roles
     *
     * @param int $report_id
     * @param boolean $return_array
     * @return string|array
     */
    public function get_roles( $report_id, $return_array = false ) {
        $value = sanitize_text_field( get_post_meta( $report_id, $this->meta_key_roles, true ) );
        if ( !$value ) {
            $value = 'administrator';
        }
        if ( $return_array ) {
            $value = explode( ', ', $value );
            $value = array_map( 'trim', $value );
            $value = array_map( 'sanitize_key', $value );
        }
        return $value;
    } // End get_selected_roles() 


    /**
     * Are we including search bar
     *
     * @param int $report_id
     * @return string
     */
    public function including_search_bar( $report_id ) {
        return filter_var( get_post_meta( $report_id, $this->meta_key_search_bar, true ), FILTER_VALIDATE_BOOLEAN );
    } // End including_search_bar() 


    /**
     * Are we including date filter
     *
     * @param int $report_id
     * @return string
     */
    public function including_date_filter( $report_id ) {
        return filter_var( get_post_meta( $report_id, $this->meta_key_date_filter, true ), FILTER_VALIDATE_BOOLEAN );
    } // End including_date_filter() 


    /**
     * Are we including quarter links
     *
     * @param int $report_id
     * @return string
     */
    public function including_quarter_links( $report_id ) {
        return filter_var( get_post_meta( $report_id, $this->meta_key_quarter_links, true ), FILTER_VALIDATE_BOOLEAN );
    } // End including_quarter_links() 


    /**
     * Are we including link on first column
     *
     * @param int $report_id
     * @return string
     */
    public function including_link_first_col( $report_id ) {
        return filter_var( get_post_meta( $report_id, $this->meta_key_link_first_col, true ), FILTER_VALIDATE_BOOLEAN );
    } // End including_link_first_col() 


    /**
     * Meta box
     *
     * @return void
     */
    public function meta_boxes() {
        // Only remove the editor on the correct post type
        if ( get_post_type() ===  $this->post_type ) {
            remove_post_type_support( $this->post_type, 'editor' );

            // General Settings
            add_meta_box( 
                'gfat_settings',
                __( 'General Settings', 'gf-tools' ),
                [ $this, 'meta_box_content_settings' ],
                $this->post_type,
                'advanced',
                'high'
            );

            // Page Fields
            add_meta_box( 
                'gfat_fields_page',
                __( 'Fields to Display on the Page', 'gf-tools' ),
                [ $this, 'meta_box_content_fields' ],
                $this->post_type,
                'advanced',
                'high'
            ); 

            // Table Preview
            add_meta_box( 
                'gfat_preview',
                __( 'Preview Report Table', 'gf-tools' ),
                [ $this, 'meta_box_content_preview' ],
                $this->post_type,
                'advanced',
                'high'
            ); 

            // Export Fields
            add_meta_box(
                'gfat_fields_export',
                __( 'Fields to Include in the CSV Export', 'gf-tools' ),
                [ $this, 'meta_box_content_fields' ],
                $this->post_type,
                'advanced',
                'high'
            );

            // Sidebar shortcode
            add_meta_box(
                'gfat_shortcode',
                __( 'Shortcode', 'gf-tools' ),
                [ $this, 'meta_box_content_shortcode' ],
                $this->post_type,
                'side',
                'default'
            );
        }
    } // End meta_boxes()


    /**
     * Meta box content for general settings
     *
     * @param object $post
     * @return void
     */
    public function meta_box_content_settings( $post ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( $this->nonce_for_saving, $this->nonce_for_saving );

        // The form
        $selected_form = $this->get_selected_form( $post->ID );

        echo '<div class="gfat-row">
            <div class="gfat-select-cont">
                <label for="gfat-form-input">'.esc_html__( 'Form', 'gf-tools' ).'</label>
                <select id="gfat-form-input" name="'.esc_attr( $this->meta_key_form_id ).'">
                    <option value="0">-- '.esc_html__( 'Select a Form', 'gf-tools' ).' --</option>';

                    // Get the forms
                    $forms = GFAPI::get_forms( true, false, 'title' );
                    if ( !empty( $forms ) ) {
                        foreach ( $forms as $form ) {

                            $is_selected = ( $form[ 'id' ] == $selected_form ) ? ' selected' : '';
                            echo '<option value="'.esc_attr( $form[ 'id' ] ).'"'.esc_attr( $is_selected ).'>'.esc_attr( $form[ 'title' ] ).'</option>';
                        }
                    }

                // End the form
                echo '</select>
            </div>
        </div>';
    
        // Order and orderby
        $order = $this->get_selected_order( $post->ID );
        $orderby = $this->get_selected_orderby( $post->ID );
        $selected_fields_page = $this->get_selected_fields( 'page', $post->ID );
        $selected_fields_export = $this->get_selected_fields( 'export', $post->ID );

        echo '<div class="gfat-row">
            <div class="gfat-select-cont">
                <label for="gfat-page-order-by">'.esc_html__( 'Order Page Entries By', 'gf-tools' ).'</label>
                <select id="gfat-page-order-by" name="page_orderby">';

                    if ( !empty( $selected_fields_page ) ) {
                        foreach ( $selected_fields_page as $form_id => $fields ) {
                            foreach ( $fields as $field_id => $label ) {
                                $is_selected = ( $field_id == $orderby[ 'page' ] ) ? ' selected' : '';
    
                                echo '<option value="'.esc_attr( $field_id ).'"'.esc_attr( $is_selected ).'>'.esc_attr( $label ).'</option>';   
                            }
                        }
                    } else {
                        echo '<option value="id">'.esc_html__( 'Entry ID', 'gf-tools' ).'</option>';
                    }
                    
                echo '</select>
            </div>';

            echo '<div class="gfat-select-cont">
                <label for="gfat-page-order">'.esc_html__( 'Order Page Entries', 'gf-tools' ).'</label>
                <select id="gfat-page-order" name="page_order">
                    <option value="ASC"'.esc_attr( isset( $order[ 'page' ] ) && 'ASC' == $order[ 'page' ] ? ' selected' : '' ).'>'.esc_html__( 'Ascending', 'gf-tools' ).'</option>
                    <option value="DESC"'.esc_attr( isset( $order[ 'page' ] ) && 'DESC' == $order[ 'page' ] ? ' selected' : '' ).'>'.esc_html__( 'Descending', 'gf-tools' ).'</option>
                </select>
            </div>
        </div>';

        echo '<div class="gfat-row">
            <div class="gfat-select-cont">
                <label for="gfat-export-order-by">'.esc_html__( 'Order Export Entries By', 'gf-tools' ).'</label>
                <select id="gfat-export-order-by" name="export_orderby">';

                    if ( !empty( $selected_fields_export ) ) {
                        foreach ( $selected_fields_export as $form_id => $fields ) {
                            foreach ( $fields as $field_id => $label ) {
                                $is_selected = ( $field_id == $orderby[ 'export' ] ) ? ' selected' : '';

                                echo '<option value="'.esc_attr( $field_id ).'"'.esc_attr( $is_selected ).'>'.esc_attr( $label ).'</option>';   
                            }
                        }
                    } else {
                        echo '<option value="id">'.esc_html__( 'Entry ID', 'gf-tools' ).'</option>';
                    }
                    
                echo '</select>
            </div>';

            echo '<div class="gfat-select-cont">
                <label for="gfat-export-order">'.esc_html__( 'Order Export Entries', 'gf-tools' ).'</label>
                <select id="gfat-export-order" name="export_order">
                    <option value="ASC"'.esc_attr( isset( $order[ 'export' ] ) && 'ASC' == $order[ 'export' ] ? ' selected' : '' ).'>'.esc_html__( 'Ascending', 'gf-tools' ).'</option>
                    <option value="DESC"'.esc_attr( isset( $order[ 'export' ] ) && 'DESC' == $order[ 'export' ] ? ' selected' : '' ).'>'.esc_html__( 'Descending', 'gf-tools' ).'</option>
                </select>
            </div>
        </div>';

        // Date format
        $selected_date_format = $this->get_selected_date_format( $post->ID );
        $date_formats = [
            'F j, Y g:ia',          // August 26, 2024 3:45pm
            'M j, Y g:ia',          // Aug 26, 2024 3:45pm
            'F j, Y g:ia T',        // August 26, 2024 3:45pm PDT
            'M j, Y g:ia T',        // Aug 26, 2024 3:45pm PDT
            'F j, Y',               // August 26, 2024
            'M j, Y',               // Aug 26, 2024
            'm/d/Y',                // 08/26/2024
            'n/j/Y',                // 8/26/2024
            'd/m/Y',                // 26/08/2024
            'j/n/Y',                // 26/8/2024
            'Y-m-d',                // 2024-08-26
            'd-m-Y',                // 26-08-2024
            'Y/m/d H:i:s',          // 2024/08/26 15:45:00
            'l, F j, Y',            // Sunday, August 26, 2024
        ];

        echo '<div class="gfat-row">
            <div class="gfat-select-cont">
                <label for="gfat-date-format-input">'.esc_html__( 'Date Format', 'gf-tools' ).'</label>
                <select id="gfat-date-format-input" name="'.esc_attr( $this->meta_key_date_format ).'">';

                    foreach ( $date_formats as $format ) {
                        $is_selected = ( $selected_date_format == $format ) ? ' selected' : '';
                        echo '<option value="'.esc_html( $format ).'"'.esc_attr( $is_selected ).'>'.esc_html( gmdate( $format ) ).'</option>';
                    }
                    
                echo '</select>
            </div>
        </div>';

        // Text fields
        $text_fields = [
            $this->meta_key_per_page => [
                'type'    => 'number', 
                'label'   => __( 'Entries Per Page', 'gf-tools' ), 
                'default' => 10,
            ],
            $this->meta_key_export_btn_text => [ 
                'type'    => 'text', 
                'label'   => __( 'Export Button Text', 'gf-tools' ), 
                'default' => __( 'Export CSV', 'gf-tools' )
            ],
            $this->meta_key_roles => [ 
                'type'    => 'text', 
                'label'   => __( 'Roles Required to View (include slugs separated by commas)', 'gf-tools' ), 
                'default' => 'administrator' 
            ],
        ];

        foreach ( $text_fields as $meta_key => $text_field ) {
            $fx = 'get_'.$meta_key;
            $value = $this->$fx( $post->ID );
            if ( !$value ) {
                $value = $text_field[ 'default' ];
            }

            echo '<div class="gfat-row">
                <div class="gfat-text-cont">
                    <label for="'.esc_attr( $meta_key ).'">'.esc_html( $text_field[ 'label' ] ).'</label>
                    <input type="'.esc_attr( $text_field[ 'type' ] ).'" id="'.esc_attr( $meta_key ).'" name="'.esc_attr( $meta_key ).'" value="'.esc_html( $value ).'">
                </div>
            </div>';
        }

        // Checkboxes
        $text_fields = [
            $this->meta_key_search_bar     => __( 'Include Search Bar', 'gf-tools' ),
            $this->meta_key_date_filter    => __( 'Include Date Filter', 'gf-tools' ),
            $this->meta_key_quarter_links  => __( 'Include Quarter Links', 'gf-tools' ),
            $this->meta_key_link_first_col => __( 'Include Link to Entry on First Column', 'gf-tools' ),
        ];

        foreach ( $text_fields as $meta_key => $label ) {
            $fx = 'including_'.$meta_key;
            $value = $this->$fx( $post->ID );

            echo '<div class="gfat-row">
                <div class="gfat-checkbox-cont">
                    <div>'.esc_html( $label ).'</div>
                    <input type="checkbox" id="'.esc_attr( $meta_key ).'" name="'.esc_attr( $meta_key ).'" value="1"'.esc_attr( $value ? ' checked' : '' ).'> <label for="'.esc_attr( $meta_key ).'">'.esc_html__( 'Yes', 'gf-tools' ).'</label>
                </div>
            </div>';
        }
    } // End meta_box_content_settings()


    /**
     * Meta box content for both fields boxes
     *
     * @param object $post
     * @param array $box
     * @return void
     */
    public function meta_box_content_fields( $post, $box ) {
        // Add a nonce field so we can check for it later.
        wp_nonce_field( $this->nonce_for_saving, $this->nonce_for_saving );

        // Type
        $meta_box_id = $box[ 'id' ];
        $type = str_replace( 'gfat_fields_', '', $meta_box_id );
    
        // Get the current values
        $selected_form = $this->get_selected_form( $post->ID );
        $selected_fields = $this->get_selected_fields( $type, $post->ID );

        // Defaults
        $form_meta = [
            'id'           => __( 'Form ID', 'gf-tools' ),
            'title'        => __( 'Form Title', 'gf-tools' ),
        ];
        if ( isset( $this->plugin_settings[ 'custom_form_settings' ] ) ) {
            $my_form_settings = $this->plugin_settings[ 'custom_form_settings' ];
            if ( !empty( $my_form_settings ) ) {
                foreach ( $my_form_settings as $setting ) {
                    $form_meta[ $setting[ 'meta_key' ] ] = $setting[ 'label' ];
                }
            }
        }

        // Add a select all field
        echo '<div class="gfat-checkbox-cont">
            <input type="checkbox" id="gfat-select-all-'.esc_attr( $type ).'" class="gfat-select-all" data-type="'.esc_attr( $type ).'">
            <label for="gfat-select-all-'.esc_attr( $type ).'">'.esc_html__( 'Select All', 'gf-tools' ).'</label>
        </div>';

        // Form Meta
        echo '<div id="gfat-form-section-0" class="gfat-form-section" data-form-id="0">
            <h3>'.esc_html__( 'Form Meta', 'gf-tools' ).'</h3>
            <div class="gfat-form-cont">';

                // Iter the fields
                foreach ( $form_meta as $field_id => $label ) {

                    // Is it checked
                    $is_selected = isset( $selected_fields[ '0' ] ) && in_array( $field_id, array_keys( $selected_fields[ '0' ] ) );
                    if ( $is_selected ) {
                        $label = sanitize_text_field( $selected_fields[ '0' ][ $field_id ] );
                    }

                    // Add a checkbox for the field
                    $this->meta_box_field_checkbox( $type, 0, $field_id, $label, $is_selected );
                }

            // End the form and section
            echo '</div>
        </div>';

        // Other Forms
        if ( $selected_form ) {
            if ( $form = GFAPI::get_form( $selected_form ) ) {

                // Selected fields
                $fields = isset( $selected_fields[ $selected_form ] ) ? $selected_fields[ $selected_form ] : [];

                // Title
                echo '<div id="gfat-form-section-'.esc_attr( $selected_form ).'" class="gfat-form-section" data-form-id="'.esc_attr( $selected_form ).'">
                    <h3>'.esc_attr( $form[ 'title' ] ).' Form</h3>
                    <div class="gfat-form-cont">';

                        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
                        $field_data = [];

                        // Entry meta
                        $entry_meta = [
                            'id'           => __( 'Entry ID', 'gf-tools' ),
                            'date_created' => __( 'Date Created', 'gf-tools' ),
                            'created_by'   => __( 'Created By', 'gf-tools' ),
                        ];
                        foreach ( $entry_meta as $key => $label ) {

                            $is_selected = in_array( $key, array_keys( $fields ) );
                            if ( $is_selected ) {
                                $label = sanitize_text_field( $fields[ $key ] );
                            }
                            
                            // Add a checkbox for the field
                            $this->meta_box_field_checkbox( $type, $selected_form, $key, $label, $is_selected );
                        }

                        // Our form fields
                        if ( isset( $form_settings[ 'mark_resolved' ] ) && $form_settings[ 'mark_resolved' ] == 1 ) {
                            $field_data = array_merge( [
                                [
                                    'id'    => 'resolved',
                                    'label' => __( 'Resolved', 'gf-tools' )
                                ],
                                [
                                    'id'    => 'resolved_date',
                                    'label' => __( 'Resolved Date', 'gf-tools' )
                                ],
                                [
                                    'id'    => 'resolved_by',
                                    'label' => __( 'Resolved By', 'gf-tools' )
                                ],
                            ], $field_data );
                        }
                        
                        if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
                            $field_data = array_merge( [
                                [
                                    'id'    => 'connected_post_id',
                                    'label' => __( 'Connected Post ID', 'gf-tools' )
                                ],
                                [
                                    'id'    => 'connected_post_title',
                                    'label' => __( 'Connected Post Title', 'gf-tools' )
                                ]
                            ], $field_data );
                        }

                        if ( !empty( $field_data ) ) {
                            foreach ( $field_data as $field ) {

                                $is_selected = in_array( $field[ 'id' ], array_keys( $fields ) );
                                if ( $is_selected ) {
                                    $label = sanitize_text_field( $fields[ $field[ 'id' ] ] );
                                } else {
                                    $label = $field[ 'label' ];
                                }
                                
                                // Add a checkbox for the field
                                $this->meta_box_field_checkbox( $type, $selected_form, $field[ 'id' ], $label, $is_selected );
                            }
                        }

                        // Iter the fields
                        foreach ( $form[ 'fields' ] as $field ) {
                            if ( $field->type == 'html' || $field->type == 'section' ) {
                                continue;
                            }

                            // Is it checked
                            $is_selected = in_array( $field->id, array_keys( $fields ) );
                            if ( $is_selected ) {
                                $label = sanitize_text_field( $fields[ $field->id ] );
                            } else {
                                $label = $field->label;
                            }

                            // Add a checkbox for the field
                            $this->meta_box_field_checkbox( $type, $selected_form, $field->id, $label, $is_selected );
                        }

                        // Custom meta
                        if ( isset( $form_settings ) ) {
                            if ( isset( $form_settings[ 'add_user_meta' ] ) && $form_settings[ 'add_user_meta' ] != 1 ) {
                                $meta_keys_string = sanitize_text_field( $form_settings[ 'add_user_meta' ] );
                                
                                if ( strpos( $meta_keys_string, ',' ) !== false ) {
                                    $meta_keys = explode( ',', $meta_keys_string );
                                } else {
                                    $meta_keys = [ $meta_keys_string ];
                                }
                                
                                $meta_keys = array_map( 'trim', $meta_keys );
                                $meta_keys = array_filter( $meta_keys );

                                foreach ( $meta_keys as $meta_key ) {

                                    $is_selected = in_array( $meta_key, array_keys( $fields ) );
                                    if ( $is_selected ) {
                                        $label = sanitize_text_field( $fields[ $meta_key ] );
                                    } else {
                                        $label = $meta_key;
                                    }
                                    
                                    // Add a checkbox for the field
                                    $this->meta_box_field_checkbox( $type, $selected_form, $meta_key, $label, $is_selected );
                                }
                            }
                        }

                    // End the form
                    echo '</div>
                </div>';
            }
        }
    } // End meta_box_content_fields()


    /**
     * Echo a meta box field checkbox
     *
     * @param string $type
     * @param int $form_id
     * @param int $field_id
     * @param string $label
     * @param boolean $is_selected
     * @return void
     */
    public function meta_box_field_checkbox( $type, $form_id, $field_id, $label, $is_selected ) {
        $checked = $is_selected ? ' checked' : '';
        $highlighted = $is_selected ? ' highlighted' : '';
        echo '<div class="gfat-field-cont'.esc_attr( $highlighted ).'">
            <input type="checkbox" id="'.esc_attr( $type.'-field-'.$form_id.'-'.$field_id ).'" class="'.esc_attr( $type ).'" name="'.esc_attr( $type.'_fields['.$form_id.']['.$field_id.']' ).'" value="'.esc_attr( $label ).'"'.esc_attr( $checked ).'>
            <label for="'.esc_attr( $type.'-field-'.$form_id.'-'.$field_id ).'">'.esc_attr( $label ).'</label>
            <a href="#" class="gfat-edit-label" aria-label="Edit Column Label" title="Edit Column Label" data-id="'.esc_attr( $type ).'-field-'.esc_attr( $form_id.'-'.$field_id ).'">[Edit]</a>
        </div>';
    } // End meta_box_field_checkbox()


    /**
     * Meta box content for preview
     *
     * @param object $post
     * @return void
     */
    public function meta_box_content_preview( $post ) {
        // Get the current values
        $selected_fields = $this->get_selected_fields( 'page', $post->ID );

        // Preview table
        echo '<table id="gfat-preview-table" class="gfat-report-table" data-cols="0">
            <thead>
                <tr>';
                
                if ( !empty( $selected_fields ) ) {
                    foreach ( $selected_fields as $form_id => $fields ) {
                        foreach ( $fields as $field_id => $label ) {
                            
                            $class = 'col-page-field-'.$form_id.'-'.$field_id;                            
                            echo '<th class="'.esc_attr( $class ).'">'.esc_html( $label ).'</th>';
                        }
                    }
                }
                
                echo '</tr>
            </thead>
            <tbody>';
            
                for ( $r = 0; $r < 4; $r++ ) {
                    echo '<tr>';

                    foreach ( $selected_fields as $form_id => $fields ) {
                        foreach ( $fields as $field_id => $label ) {
                            $class = 'col-page-field-'.$form_id.'-'.$field_id;
                            echo '<td class="'.esc_attr( $class ).'">Example</td>';
                        }
                    }

                    echo '</tr>';
                }

            echo '</tbody>
        </table>';
    } // End meta_box_content_preview()


    /**
     * Meta box content for preview
     *
     * @param object $post
     * @return void
     */
    public function meta_box_content_shortcode( $post ) {
        $code = '[gfat_report id="'.$post->ID.'"]';
        $code_box_html = [ 
            'div' => [
                'class' => [],
                'style' => [],
                'id'    => [],
            ],
            'pre' => [],
            'code' => [
                'class' => []
            ],
            'button' => [
                'class' => [],
                'style' => [], 
                'type'  => [],
            ],
            'br' => []
        ];
        echo wp_kses( (new GF_Advanced_Tools_Helpers)->display_code_box( $code ), $code_box_html );
    } // End meta_box_content_shortcode()


    /**
     * Save the post data
     *
     * @param int $post_id
     * @return void
     */
    public function save_post( $post_id ) {
        // Verify that the nonce is valid.
        if ( !isset( $_POST[ $this->nonce_for_saving ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->nonce_for_saving ] ) ), $this->nonce_for_saving ) ) {
            return;
        }
     
        // Verify can save
        if ( !(new GF_Advanced_Tools_Helpers)->can_save_post( $post_id, $this->post_type ) ) {
            return;
        }
     
        /* OK, it's safe for us to save the data now. */

        $form_id = isset( $_POST[ 'form_id' ] ) ? absint( $_POST[ 'form_id' ] ) : 0;
        update_post_meta( $post_id, $this->meta_key_form_id, $form_id );

        $page_orderby = isset( $_POST[ 'page_orderby' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'page_orderby' ] ) ) : 'date';
        $export_orderby = isset( $_POST[ 'export_orderby' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'export_orderby' ] ) ) : 'date';
        update_post_meta( $post_id, $this->meta_key_orderby, [ 'page' => $page_orderby, 'export' => $export_orderby ] );

        $page_order = isset( $_POST[ 'page_order' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'page_order' ] ) ) : 'ASC';
        $export_order = isset( $_POST[ 'export_order' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'export_order' ] ) ) : 'ASC';
        update_post_meta( $post_id, $this->meta_key_order, [ 'page' => $page_order, 'export' => $export_order ] );

        if ( isset( $_POST[ 'page_fields' ] ) ) {
            $page_fields = array_map( function( $fields ) {
                return array_map( 'sanitize_text_field', $fields );
            }, wp_unslash( $_POST[ 'page_fields' ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        } else {
            $page_fields = [];
        }
        update_post_meta( $post_id, $this->meta_key_fields_page, $page_fields );

        if ( isset( $_POST[ 'export_fields' ] ) ) {
            $export_fields = array_map( function( $fields ) {
                return array_map( 'sanitize_text_field', $fields );
            }, wp_unslash( $_POST[ 'export_fields' ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        } else {
            $export_fields = [];
        }
        update_post_meta( $post_id, $this->meta_key_fields_export, $export_fields );

        $entries_per_page = isset( $_POST[ $this->meta_key_per_page ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->meta_key_per_page ] ) ) : 10;
        update_post_meta( $post_id, $this->meta_key_per_page, $entries_per_page );

        $text_inputs = [
            $this->meta_key_export_btn_text,
            $this->meta_key_date_format,
            $this->meta_key_roles
        ];
        foreach ( $text_inputs as $text_input ) {
            $text_value = isset( $_POST[ $text_input ] ) ? sanitize_text_field( wp_unslash( $_POST[ $text_input ] ) ) : '';
            update_post_meta( $post_id, $text_input, $text_value );
        }

        $checkbox_inputs = [
            $this->meta_key_search_bar,
            $this->meta_key_date_filter,
            $this->meta_key_quarter_links,
            $this->meta_key_link_first_col
        ];
        foreach ( $checkbox_inputs as $checkbox_input ) {
            $checkbox_value = isset( $_POST[ $checkbox_input ] ) && $_POST[ $checkbox_input ] == 1 ? true : false;
            update_post_meta( $post_id, $checkbox_input, $checkbox_value );
        }
    } // End save_post()

    
    /**
     * Admin columns
     *
     * @param array $columns
     * @return array
     */
    // public function admin_columns( $columns ) {
    //     $columns[ 'form' ] = __( 'Form', 'gf-tools' );
    //     $columns[ 'report_id' ] = __( 'Report ID', 'gf-tools' );
    //     $columns[ 'shortcode' ] = __( 'Shortcode', 'gf-tools' );
    //     return $columns;
    // } // End admin_columns()


    /**
     * Admin column content
     *
     * @param string $column
     * @param int $post_id
     * @return void
     */
    // public function admin_column_content( $column, $post_id ) {
    //     // Forms
    //     if ( 'form' === $column ) {
    //         $form_id = $this->get_selected_form( $post_id );
    //         $form = GFAPI::get_form( $form_id );
               
    //         $form_url = add_query_arg( [
    //             'page'    => 'gf_edit_forms',
    //             'view'    => 'settings',
    //             'subview' => 'gf-tools',
    //             'id'      => $form_id
    //         ], admin_url( 'admin.php' ) );
            
    //         echo '<a href="'.esc_url( $form_url ).'">'.esc_attr( $form[ 'title' ] ).'</a>';

    //     // ID
    //     } elseif ( 'report_id' === $column ) {
    //         echo esc_attr( $post_id );
        
    //     // Shortcode
    //     } elseif ( 'shortcode' === $column ) {
    //         $code = htmlspecialchars( '[gfat_report id="'.$post_id.'"]', ENT_QUOTES, 'UTF-8' );
    //         echo '<code>'.esc_html( $code ).'</code>';
    //     }
    // } // End admin_column_content()


    /**
     * Edit screen: get form fields
     *
     * @return void
     */
    public function ajax_report_get_form_fields() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce_for_preview ) ) {
            exit( esc_html__( 'No naughty business please.', 'gf-tools' ) );
        }
    
        // Get the IDs
        $form_id = isset( $_REQUEST[ 'formID' ] ) ? absint( $_REQUEST[ 'formID' ] ) : false;

        // Call Helpers
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Make sure we have a source URL
        if ( $form_id ) {

            // Delete the entry
            if ( $form = GFAPI::get_form( $form_id ) ) {

                $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
                $field_data = [];

                // Regular form fields
                foreach ( $form[ 'fields' ] as $field ) {
                    if ( $field->type == 'html' || $field->type == 'section' ) {
                        continue;
                    }

                    $field_data [] = [
                        'id'    => $field->id,
                        'label' => $field->label
                    ];
                }

                // Our form fields
                if ( isset( $form_settings[ 'mark_resolved' ] ) && $form_settings[ 'mark_resolved' ] == 1 ) {
                    $field_data = array_merge( [
                        [
                            'id'    => 'resolved',
                            'label' => __( 'Resolved', 'gf-tools' )
                        ],
                        [
                            'id'    => 'resolved_date',
                            'label' => __( 'Resolved Date', 'gf-tools' )
                        ],
                        [
                            'id'    => 'resolved_by',
                            'label' => __( 'Resolved By', 'gf-tools' )
                        ],
                    ], $field_data );
                }
                
                if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
                    $field_data = array_merge( [
                        [
                            'id'    => 'connected_post_id',
                            'label' => __( 'Connected Post ID', 'gf-tools' )
                        ],
                        [
                            'id'    => 'connected_post_title',
                            'label' => __( 'Connected Post Title', 'gf-tools' )
                        ]
                    ], $field_data );
                }

                // Entry meta
                $field_data = array_merge( [
                    [
                        'id'    => 'id',
                        'label' => __( 'Entry ID', 'gf-tools' )
                    ],
                    [
                        'id'    => 'date_created',
                        'label' => __( 'Date Created', 'gf-tools' )
                    ],
                    [
                        'id'    => 'created_by',
                        'label' => __( 'Created By', 'gf-tools' )
                    ]
                ], $field_data );

                // Custom meta
                if ( isset( $form_settings ) ) {
                    foreach ( $form_settings as $name => $form_setting ) {
                        if ( $HELPERS->str_starts_with( $name, 'add_user_meta_' ) && $form_setting == 1 ) {
                            $field_id = str_replace( 'add_user_meta_', '', $name );
                            $field_data [] = [
                                'id'    => $field_id,
                                'label' => $field_id
                            ];
                        }
                    }
                }

                // Respond
                $result[ 'type' ] = 'success';
                $result[ 'title' ] = sanitize_text_field( $form[ 'title' ] );
                $result[ 'fields' ] = $field_data;
            } else {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'Form '.$form_id.' does not exist.';
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No form ID found.';
        }
        
        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_report_get_form_fields()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'post-new.php' && get_post_type() !== $this->post_type ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_reports_back', 'enqueued' ) ) {
            wp_localize_script( 'gfadvtools_reports_back', 'gfat_reports_back', [
                'ajaxurl'           => admin_url( 'admin-ajax.php' ),
                'nonce_for_preview' => wp_create_nonce( $this->nonce_for_preview )
            ] );
        }
    } // End enqueue_scripts()
    

    /**
     * Enqueue CSS on front-end
     *
     * @return void
     */
    public function enqueue_styles() {
        if ( is_admin() ) {
            return;
        }

        wp_enqueue_style( 'gfadvtools_reports', GFADVTOOLS_PLUGIN_DIR.'includes/css/reports.css', [], time() );
    } // End enqueue_styles()

    
    /**
     * The export form
     *
     * @param array $args
     * @return array
     */
    public function export_form( $args ) {
        $fields = wp_json_encode( $args[ 'fields' ] );
        $fields = rawurlencode( $fields );

        $search_criteria = wp_json_encode( $args[ 'search_criteria' ] );
        $search_criteria = rawurlencode( $search_criteria );
        
        return '<div class="gfat-report-export">
            <form method="post" action="">
                '.wp_nonce_field( $this->nonce_for_export, $this->nonce_for_export, true, false ).'
                <input type="hidden" name="report_id" value="'.$args[ 'report_id' ].'">
                <input type="hidden" name="form_id" value="'.$args[ 'form_id' ].'">
                <input type="hidden" name="fields" value="'.$fields.'">
                <input type="hidden" name="search_criteria" value="'.$search_criteria.'">
                <input type="hidden" name="orderby" value="'.$args[ 'orderby' ].'">
                <input type="hidden" name="order" value="'.$args[ 'order' ].'">
                <input type="hidden" name="total_count" value="'.$args[ 'total_count' ].'">
                <input type="submit" name="gfat_report_entries_export" class="gfat-button button button-secondary" value="'.$args[ 'button_text' ].'" />
            </form>
        </div>';
    } // End export_form()


    /**
     * Export csv
     *
     * @return void
     */
    public function export() {
        if ( !isset( $_POST[ $this->nonce_for_export ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->nonce_for_export ] ) ), $this->nonce_for_export ) ) {
            die( esc_html__( 'No funny business.', 'gf-tools' ) );
        }

        // Catch
        $report_id = isset( $_POST[ 'report_id' ] ) ? absint( $_POST[ 'report_id' ] ) : false;

        $selected_form_id = isset( $_POST[ 'form_id' ] ) ? absint( $_POST[ 'form_id' ] ) : false;        

        if ( isset( $_POST[ 'fields' ] ) ) {
            $fields_json = sanitize_text_field( rawurldecode( wp_unslash( $_POST[ 'fields' ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $selected_fields = json_decode( $fields_json, true );
            $selected_fields = array_map( function( $s ) {
                return array_map( 'sanitize_text_field', $s );
            }, $selected_fields );
        } else {
            $selected_fields = false;
        }
        
        if ( isset( $_POST[ 'search_criteria' ] ) ) {
            $search_criteria_json = sanitize_text_field( rawurldecode( wp_unslash( $_POST[ 'search_criteria' ] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $search_criteria = json_decode( $search_criteria_json, true );
            $search_criteria = filter_var_array( $search_criteria, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        } else {
            $search_criteria = false;
        }

        $orderby = isset( $_POST[ 'orderby' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'orderby' ] ) ) : false;

        $order = isset( $_POST[ 'order' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'order' ] ) ) : false;

        $total_count = isset( $_POST[ 'total_count' ] ) ? absint( $_POST[ 'total_count' ] ) : false;

        if ( !$report_id || !$selected_form_id || !$selected_fields || !$search_criteria || !$orderby || !$order || !$total_count ) {
            die( esc_html__( 'Oops! Something went wrong. Missing data in $_POST request.', 'gf-tools' ) );
        }

        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Fetch
        $selected_form = GFAPI::get_form( $selected_form_id );
        if ( !$selected_form ) {
            die( esc_html__( 'Oops! Something went wrong. Form could not be found.', 'gf-tools' ) );
        }

        $date_format = sanitize_text_field( $this->get_selected_date_format( $report_id ) );

        // Filename
        $title = sanitize_text_field( $selected_form[ 'title' ] );
        $title = ucwords( str_replace( '_', ' ', $title ) );
        $title = str_replace( [ '&#8217;', '\'', '.', ',' ], '' , $title );
        $title = str_replace( ' ', '_', $title );

        $max_title_length = 50;
        if ( strlen( $title ) > $max_title_length ) {
            $title = substr( $title, 0, $max_title_length );
        }
        
        $currentDate = $HELPERS->convert_date_to_wp_timezone( null, 'Y_m_d-H_i_s' );

        $filename = $title.'_'.$currentDate.'.csv';

        // Split the field ids and labels
        $field_id_data = [];
        $header_row = [];
        foreach ( $selected_fields as $form_id => $fields ) {
            foreach ( $fields as $field_id => $label ) {
                $field_id_data[] = [
                    'id'           => $field_id,
                    'is_form_meta' => ( $form_id == 0 )
                ];
                $header_row[] = $label;
            }
        }

        // dpr( $_POST );
        // exit();

        // Create an empty array for the rows
        $data_rows = [];

        // Get all entries
        $sorting = [ 'key' => $orderby, 'direction' => $order ];
        $paging = [ 'offset' => 0, 'page_size' => $total_count ];
        $entries = GFAPI::get_entries( $selected_form_id, $search_criteria, $sorting, $paging );

        foreach ( $entries as $entry ) {
            $row = [];
            foreach ( $field_id_data as $field_data ) {
                $field_id = $field_data[ 'id' ];

                if ( $field_data[ 'is_form_meta' ] ) {
                    $value = sanitize_text_field( $selected_form[ $field_id ] );
                    if ( $field_id == 'date_created' ) {
                        $value = gmdate( $date_format, strtotime( $value ) );
                    }

                } else {
                    $value = isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
                    $value = $HELPERS->filter_entry_value( $value, $field_id, $entry, $selected_form, $date_format, $HELPERS );
                }

                $row[] = $value;
            }
            $data_rows[] = $row;
        }

        // Export it
        $HELPERS->export_csv( $filename, $header_row, $data_rows );
    } // End export()
}