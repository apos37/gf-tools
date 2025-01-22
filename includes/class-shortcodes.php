<?php
/**
 * Shortcodes class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Shortcodes {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_shortcode_nonce';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Remove a query string parameter
        add_shortcode( 'gfat_remove_qs', [ $this, 'remove_qs' ] );

        // Entry submitted
        add_shortcode( 'gfat_entry_submitted', [ $this, 'entry_submitted' ] );
        add_shortcode( 'gfat_entry_not_submitted', [ $this, 'entry_not_submitted' ] );

        // Get query string value
        add_shortcode( 'gfat_qs_value', [ $this, 'qs_value' ] );

        // Display Form from ID in URL
        add_shortcode( 'gfat_form', [ $this, 'form' ] );

        // Display a Front-End Report for Any Form
        add_shortcode( 'gfat_report', [ $this, 'report' ] );

        // Export Entries on Front-End
        add_shortcode( 'gfat_export_entries', [ $this, 'export_entries' ] );

        // Export
        if ( isset( $_POST[ 'gfat_entries_export' ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->export();
        }

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End __construct()


    /**
     * Remove query strings from the url
     * USAGE: [gfat_remove_qs params=""]
     *
     * @param array $atts
     * @return string
     */
    public function remove_qs( $atts ) {
        if ( is_admin() ) {
            return;
        }

		$atts = shortcode_atts( [ 'params' => '' ], $atts );
		$params = sanitize_text_field( $atts[ 'params' ] );
		$qs = preg_split( '/[\s,]+/', $params, -1, PREG_SPLIT_NO_EMPTY );
		$qs = array_map( 'trim', $qs );

        $page_title = get_the_title();
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Check if any of the specified query strings are present in the URL
        $query_found = false;
        foreach ( $qs as $param ) {
            if ( isset( $_GET[$param] ) ) {
                $query_found = true;
                break;
            }
        }
        
        // Only run if the params are found
        if ( $query_found ) {
             
            $new_url = remove_query_arg( $qs );

            // Enqueue the script only when the shortcode is used
            if ( !is_admin() ) {
                wp_enqueue_script( 'gfadvtools_remove_qs', GFADVTOOLS_PLUGIN_DIR.'includes/js/remove-qs.js', [ 'jquery' ], time(), true );
            }
        
            // Localize the data for the script
            wp_localize_script( 'gfadvtools_remove_qs', 'gfadvtools_remove_qs', [
                'title' => $page_title,
                'url'   => $new_url
            ] );
        }        
	
		// Return it
		return '';
	} // End remove_qs()


    /**
     * Entry submitted
     * USAGE: [gfat_entry_submitted form_id="" date_format="F j, Y"][/gfat_entry_submitted]
     *
     * @param array $atts
     * @return string
     */
    public function entry_submitted( $atts, $content ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();
        $atts = shortcode_atts( [ 'form_id' => '', 'date_format' => 'F j, Y' ], $atts );

        // Get the form id
		$form_id = absint( $atts[ 'form_id' ] );
        if ( !$form_id ) {
            return $HELPERS->administrator_error_message( 'Form ID not provided.' );
        }

		// Validate the form
        $form = \GFAPI::get_form( $form_id );
        if ( !$form ) {
            return $HELPERS->administrator_error_message( 'Form does not exist.' );
        }

        // Check for entries
        $entries = $HELPERS->get_entries_for_user( $form_id );
        if ( empty( $entries ) ) {
            return '';
        }

        // Get the latest entry
        $most_recent_entry = $entries[0];
        
        // Get the date in case they use a merge tag for date
        if ( strpos( $content, '{date}' ) !== false ) {
            $date = $most_recent_entry[ 'date_created' ];
            $date = $HELPERS->convert_date_to_wp_timezone( $date, sanitize_text_field( $atts[ 'date_format' ] ) );
            $content = str_replace( '{date}', $date, $content );
        }

        // Return it
        return wp_kses_post( $content );
	} // End entry_submitted()


    /**
     * Entry not submitted
     * USAGE: [gfat_entry_not_submitted form_id=""][/gfat_entry_not_submitted]
     *
     * @param array $atts
     * @return string
     */
    public function entry_not_submitted( $atts, $content ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Get the form id
		$atts = shortcode_atts( [ 'form_id' => '' ], $atts );
		$form_id = absint( $atts[ 'form_id' ] );
        if ( !$form_id ) {
            return $HELPERS->administrator_error_message( 'Form ID not provided.' );
        }

		// Validate the form
        $form = \GFAPI::get_form( $form_id );
        if ( !$form ) {
            return $HELPERS->administrator_error_message( 'Form does not exist.' );
        }

        // Check for entries
        $entries = $HELPERS->get_entries_for_user( $form_id );
        if ( empty( $entries ) ) {
            return wp_kses_post( $content );
        }
		return '';
	} // End entry_not_submitted()


    /**
     * Get query string value
     * USAGE: [gfat_qs_value param=""]
     *
     * @param array $atts
     * @return string
     */
    public function qs_value( $atts ) {
        $atts = shortcode_atts( [ 'param' => '' ], $atts );
        $param = sanitize_text_field( $atts[ 'param' ] );
        if ( isset( $_GET[ $param ] ) ) {
            return esc_html( $_GET[ $param ] );
        }
        return;
	} // End qs_value()


    /**
     * Display Form from ID in URL
     * USAGE: [gfat_form param="form_id" forms="1, 2, 5 (Custom Form Title)"]
     *
     * @param array $atts
     * @return string
     */
    public function form( $atts ) {
        $atts = shortcode_atts( [ 
            'param' => 'form_id',
            'forms' => false,
        ], $atts );
        $param = sanitize_key( $atts[ 'param' ] );

        if ( $param != '' ) {

            // Check if the form ID is in the query string
            if ( isset( $_GET[ $param ] ) && absint( $_GET[ $param ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $form_id = absint( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                $gravity_form_atts = array_diff_key( $atts, array_flip( [ 'param' ] ) );
                $shortcode_atts = array_map( function( $value, $key ) {
                    return $key.'="'.esc_attr( $value ).'"';
                }, $gravity_form_atts, array_keys( $gravity_form_atts ) );
                $shortcode_atts_string = implode( ' ', $shortcode_atts );
            
                return do_shortcode( "[gravityform id=\"$form_id\" $shortcode_atts_string]" );

            // Or else make a list of forms provided
            } elseif ( $atts[ 'forms' ] ) {

                $current_url = (new GF_Advanced_Tools_Helpers)->get_current_url();

                $forms_list = [];

                if ( $forms_string = sanitize_text_field( $atts[ 'forms' ] ) ) {
                    $forms_entries = preg_split( '/,\s*(?![^()]*\))/', $forms_string );
                    
                    foreach ( $forms_entries as $entry ) {
                        
                        if ( preg_match( '/^(\d+)(?:\s*\(([^)]+)\))?$/', trim( $entry ), $matches ) ) {
                            $id = absint( $matches[1] );
                            $title = isset( $matches[2] ) ? sanitize_text_field( $matches[2] ) : false;
                            
                            if ( !$title ) {
                                if ( $form = GFAPI::get_form( $id ) ) {
                                    $title = sanitize_text_field( $form[ 'title' ] );
                                } else {
                                    continue;
                                }
                            }
                           
                            $forms_list[] = [
                                'id'    => $id,
                                'title' => $title
                            ];
                        }
                    }
                }

                if ( !empty( $forms_list ) ) {

                    $list = '<ul class="gfat-default-forms-list">';
                        
                        foreach ( $forms_list as $li ) {
                            $url = add_query_arg( $param, $li[ 'id' ], $current_url );
                            $list .= '<li><a href="'.$url.'">'.$li[ 'title' ].'</a></li>';
                        }

                    $list .= '</li>';

                    return $list;
                }

            // Or else just say it ain't so
            } else {
                return __( 'Oops! We could not locate your form.', 'gf-tools' );
            }
        }
        return '';
    } // End form()


    /**
     * Display a Front-End Report for Any Form
     * USAGE: [gfat_report id="1" param="report_id" reports="1, 2, 5 (Custom Form Title)"]
     *
     * @param array $atts
     * @return string
     */
    public function report( $atts ) {
        $atts = shortcode_atts( [
            'id'      => 0,
            'param'   => 'report_id',
            'reports' => false,
        ], $atts );
        $report_id = absint( $atts[ 'id' ] );
        $param = sanitize_key( $atts[ 'param' ] );

        if ( !$report_id && $param != '' ) {
            if ( isset( $_GET[ $param ] ) && absint( $_GET[ $param ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $report_id = absint( $_GET[ $param ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }
        }

        $HELPERS = new GF_Advanced_Tools_Helpers();
        $current_url = $HELPERS->get_current_url( false );

        // We have a report ID
        if ( $report_id ) {
            if ( get_post( $report_id ) ) {

                $GFAT = new GF_Advanced_Tools();
                $REPORTS = new GF_Advanced_Tools_Reports( null );

                // Get the form and fields
                $selected_form_id = absint( $REPORTS->get_selected_form( $report_id ) );
                $selected_fields = $REPORTS->get_selected_fields( 'page', $report_id );

                if ( !$selected_form_id || empty( $selected_fields ) ) {
                    return __( 'Oops! We are missing report data.', 'gf-tools' );
                }

                $selected_form = GFAPI::get_form( $selected_form_id );
                if ( !$selected_form ) {
                    return __( 'Oops! The selected form no longer exists.', 'gf-tools' );
                }

                // Search
                $searching = '';
                $incl_search_bar = false;
                if ( $incl_search_bar = $REPORTS->including_search_bar( $report_id ) ) {
                    if ( isset( $_GET[ 'search' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ) != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $search = sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        /* translators: %s is the search term. */
                        $searching = sprintf( __( 'Searching for "%s"', 'gf-tools' ), $search ).' ';
                    } else {
                        $search = '';
                    }
                }

                // Dates
                $date_filter = false;
                $incl_date_filter = false;
                $incl_quarter_links = false;
                $date_format = sanitize_text_field( $REPORTS->get_selected_date_format( $report_id ) );

                if ( $incl_date_filter = $REPORTS->including_date_filter( $report_id ) ) {

                    if ( isset( $_GET[ 'start_date' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'start_date' ] ) ) != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $start = sanitize_text_field( wp_unslash( $_GET[ 'start_date' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $date_filter = true;
                    } else {
                        $start = gmdate( 'Y-m-d', strtotime( get_user_option( 'user_registered', 1 ) ) );
                    }

                    if ( isset( $_GET[ 'end_date' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'end_date' ] ) ) != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $end = sanitize_text_field( wp_unslash( $_GET[ 'end_date' ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $date_filter = true;
                    } else {
                        $end = gmdate( 'Y-m-d' );
                    }

                    if ( $date_filter ) {
                        if ( $searching != '' ) {
                            /* translators: %1$s is the formatted start date, %2$s is the formatted end date. */
                            $searching .= sprintf( __( 'between %1$s and %2$s.', 'gf-tools' ), 
                                gmdate( $date_format, strtotime( $start ) ), 
                                gmdate( $date_format, strtotime( $end ) ) 
                            );
                        } else {
                            /* translators: %1$s is the formatted start date, %2$s is the formatted end date. */
                            $searching = sprintf( __( 'Filtering between %1$s and %2$s.', 'gf-tools' ), 
                                gmdate( $date_format, strtotime( $start ) ), 
                                gmdate( $date_format, strtotime( $end ) ) 
                            );
                        }
                    }

                    $current_url_with_dates = add_query_arg( [
                        'start_date' => $start,
                        'end_date'   => $end,
                    ], $current_url );

                    // Quarter links
                    if ( $incl_quarter_links = $REPORTS->including_quarter_links( $report_id ) ) {
                        $plugin_settings = $GFAT->get_plugin_settings();
                        $ff = isset( $plugin_settings[ 'federal_fiscal' ] ) && $plugin_settings[ 'federal_fiscal' ] == 1;
                    }
                    
                } else {
                    $current_url_with_dates = $current_url;
                }
                
                // Start the container
                $results = '<div class="gfat-report">';
                
                    // Top Section
                    $results .= '<div class="gfat-report-top">';

                        if ( $incl_search_bar ) {
                            $results .= '<div class="gfat-search">
                                <form id="gfat-search-form" method="GET" action="'.$current_url.'">
                                    <input type="text" name="search" value="'.$search.'">
                                    <input type="submit" value="Search" class="gfat-button button button-primary">
                                </form>
                            </div>';
                        } else {
                            $results .= '<div class="gfat-search"></div>';
                        }

                        if ( $incl_date_filter ) {
                            $results .= '<div class="gfat-date-filter">';

                                if ( $incl_quarter_links ) {
                                    $results .= $HELPERS->quarter_links( $current_url, $ff );
                                }

                                $results .= '<div class="gfat-date-filter">
                                    <form id="gfat-date-filter-form" method="GET" action="'.$current_url.'">
                                        <div class="date-fields">
                                            <div class="start">
                                                <label for="start_date">'.__( 'Start', 'gf-tools' ).':</label>
                                                <input type="date" name="start_date" id="start_date" value="'.$start.'">
                                            </div>
                                            <div class="end">
                                                <label for="end_date">'.__( 'End', 'gf-tools' ).':</label>
                                                <input type="date" name="end_date" id="end_date" value="'.$end.'">
                                            </div>
                                            <input type="submit" value="'.__( 'Filter', 'gf-tools' ).'" class="gfat-button button button-primary"/>
                                        </div>
                                    </form>
                                </div>
                            </div>';
                        }

                    $results .= '</div>';

                    $search_criteria = [
                        'status' => 'active',
                    ];

                    if ( $incl_search_bar && $search ) {
                        $search_criteria[ 'field_filters' ] = [ 
                            'mode'  => 'any',
                            [ 'value' => $search ],
                        ];

                        if ( is_numeric( $search ) && $search > 0 ) {
                            $search_criteria[ 'field_filters' ][] = [
                                'key'   => 'id',
                                'value' => $search
                            ];
                        }
                    }

                    if ( $incl_date_filter && $date_filter ) {
                        $search_criteria[ 'start_date' ] = $start;
                        $search_criteria[ 'end_date' ] = $end;
                    }

                    $orderby_choices = $REPORTS->get_selected_orderby( $report_id );
                    $orderby = isset( $orderby_choices[ 'page' ] ) ? sanitize_text_field( $orderby_choices[ 'page' ] ) : 'date_created';
                    $order_choices = $REPORTS->get_selected_order( $report_id );
                    $order = isset( $order_choices[ 'page' ] ) ? sanitize_text_field( $order_choices[ 'page' ] ) : 'ASC';
                    $sorting = [ 'key' => $orderby, 'direction' => $order ];

                    $page_num = isset( $_GET[ 'page_num' ] ) ? absint( $_GET[ 'page_num' ] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                    if ( isset( $_GET[ 'per_page' ] ) && absint( $_GET[ 'per_page' ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $page_size = absint( $_GET[ 'per_page' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    } elseif ( $set_page_size = $REPORTS->get_entries_per_page( $report_id ) ) {
                        $page_size = absint( $set_page_size );
                    } else {
                        $page_size = 10;
                    }

                    if ( !$page_num ) {
                        $offset = 0;
                    } else {
                        $offset = ( $page_num - 1 ) * $page_size;
                    }

                    $paging = [ 'offset' => $offset, 'page_size' => $page_size ];

                    $total_count = 0;
                    $entries = GFAPI::get_entries( $selected_form_id, $search_criteria, $sorting, $paging, $total_count );

                    // Get the total number of pages
                    $total_pages = ceil( $total_count / $page_size );

                    if ( empty( $entries ) ) {
                        $results .= '<br><br>'.__( 'No entries found.', 'gf-tools' );
                        
                    } else {

                        // Export
                        $selected_fields_export = $REPORTS->get_selected_fields( 'export', $report_id );
                        if ( !empty( $selected_fields_export ) ) {
                            $export_btn_text = sanitize_text_field( $REPORTS->get_export_btn_text( $report_id ) );
                            if ( !$export_btn_text ) {
                                $export_btn_text = __( 'Export CSV', 'gf-tools' );
                            }

                            $orderby = isset( $orderby_choices[ 'export' ] ) ? sanitize_text_field( $orderby_choices[ 'export' ] ) : 'date_created';
                            $order = isset( $order_choices[ 'export' ] ) ? sanitize_text_field( $order_choices[ 'export' ] ) : 'ASC';

                            $args = [
                                'report_id'          => $report_id,
                                'form_id'            => $selected_form_id,
                                'fields'             => $selected_fields_export,
                                'search_criteria'    => $search_criteria,
                                'orderby'            => $orderby,
                                'order'              => $order,
                                'total_count'        => $total_count,
                                'button_text'        => $export_btn_text
                            ];
                            $incl_export = $REPORTS->export_form( $args );
                        } else {
                            $incl_export = '';
                        }

                        // Table info
                        $results .= '<div class="gfat-report-table-info">
                            <div class="gfat-report-counts">Total Entries: <strong>'.$total_count.'</strong></div>
                            <div class="searching">'.$searching.'</div>
                            '.$incl_export.'
                        </div>';

                        // Table
                        $selected_fields = $REPORTS->get_selected_fields( 'page', $report_id );
                        $link_first_col = $REPORTS->including_link_first_col( $report_id );

                        $results .= '<table id="gfat-preview-table" class="gfat-report-table" data-cols="0">
                            <thead>
                                <tr>';
                                
                                if ( !empty( $selected_fields ) ) {
                                    foreach ( $selected_fields as $form_id => $fields ) {
                                        foreach ( $fields as $field_id => $label ) {
                                            
                                            $class = 'col-page-field-'.$form_id.'-'.$field_id;                                        
                                            $results .= '<th class="'.esc_attr( $class ).'">'.$label.'</th>';
                                        }
                                    }
                                }
                                
                                $results .= '</tr>
                            </thead>
                            <tbody>';
                            
                                foreach ( $entries as $entry ) {

                                    $user_id = $entry[ 'created_by' ];

                                    $results .= '<tr>';

                                        $col_num = 0;

                                        foreach ( $selected_fields as $form_id => $fields ) {

                                            foreach ( $fields as $field_id => $label ) {

                                                // User meta
                                                if ( str_starts_with( $field_id, 'user-meta-' ) ) {
                                                    if ( $user_id ) {
                                                        $meta_key = substr( $field_id, strlen( 'user-meta-' ) );
                                                        $value = get_user_meta( $user_id, $meta_key, true );
                                                    } else {
                                                        $value = 'Logged-out user';
                                                    }

                                                // Else
                                                } else {

                                                    $class = 'col-page-field-'.$form_id.'-'.$field_id;

                                                    if ( $form_id == 0 ) {
                                                        $value = sanitize_text_field( $selected_form[ $field_id ] );
                                                        if ( $field_id == 'date_created' ) {
                                                            $value = gmdate( $date_format, strtotime( $value ) );
                                                        }

                                                    } else {
                                                        $value = isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
                                                        $value = $HELPERS->filter_entry_value( $value, $field_id, $entry, $selected_form, $date_format, $HELPERS, false );
                                                    }
                                                }

                                                // Link the first column
                                                $col_num++;
                                                if ( $link_first_col && $col_num === 1 ) {
                                                    $entry_url = add_query_arg( [
                                                        'page' => 'gf_entries',
                                                        'view' => 'entry',
                                                        'id'   => $selected_form_id,
                                                        'lid'  => $entry[ 'id' ]
                                                    ], admin_url( 'admin.php' ) );
                                                    $value = '<a href="'.$entry_url.'" target="_blank">'.$value.'</a>';
                                                }
                                                
                                                $results .= '<td class="'.esc_attr( $class ).'">'.$value.'</td>';
                                                
                                            }
                                        }

                                    $results .= '</tr>';
                                }

                            $results .= '</tbody>
                        </table>';

                        // Bottom section
                        $page_size_options = [ 10, 25, 50 ];
                        
                        $results .= '<div class="gfat-report-bottom">
                            <form id="gfat-per-page-form" method="get">
                                <label for="gfat-per-page">' . __( 'Entries per page', 'gf-tools' ) . ':</label>
                                <select id="gfat-per-page" name="per_page" onchange="window.location.href = \'' . esc_url( $current_url_with_dates ) . '&per_page=\' + this.value;">';

                                    if ( !in_array( $page_size, $page_size_options ) ) {
                                        $page_size_options[] = $page_size;
                                        sort( $page_size_options );
                                    }
                                    
                                    foreach ( $page_size_options as $option ) {
                                        $results .= '<option value="'.$option.'"' . ( $page_size == $option ? ' selected' : '' ) . '>'.$option.'</option>';
                                    }

                                $results .= '</select>
                            </form>
                            <div class="page-num">' . sprintf( 
                                    /* translators: %1$s is the current page number, %2$s is the total number of pages. */
                                    __( 'Page %1$s of %2$s', 'gf-tools' ), 
                                    $page_num, 
                                    $total_pages 
                                ) . '</div>';

                            // Pagination logic
                            if ( $total_pages > 1 ) {
                                $current_page = max( 1, $page_num );
                                $results .= '<div class="pagination">';
                                
                                // Previous page link
                                if ( $current_page > 1 ) {
                                    $previous_url = add_query_arg( [
                                        'per_page' => $page_size,
                                        'page_num' => $current_page - 1
                                    ], $current_url_with_dates );
                                    $results .= '<a href="'.$previous_url.'" class="gfat-button button button-secondary">'.__( 'Previous', 'gf-tools' ).'</a>';
                                }
                                
                                // Page numbers
                                if ( $total_pages <= 5 ) {
                                    // If there are 5 or fewer pages, show all pages
                                    $start_page = 1;
                                    $end_page = $total_pages;
                                } elseif ( $current_page > $total_pages - 5 ) {
                                    // If we're in the last 5 pages, show the last 5 pages
                                    $start_page = max( 1, $total_pages - 4 );
                                    $end_page = $total_pages;
                                } else {
                                    // Otherwise, show the next 5 pages
                                    $start_page = $current_page;
                                    $end_page = min( $start_page + 4, $total_pages );  // Calculate end page, no more than 5 pages ahead
                                }

                                for ( $i = $start_page; $i <= $end_page; $i++ ) {
                                    $page_num_url = add_query_arg( [
                                        'per_page' => $page_size,
                                        'page_num' => $i
                                    ], $current_url_with_dates );
                                    $results .= '<a href="'.$page_num_url.'" class="gfat-button button button-secondary'.( $i == $current_page ? ' current' : '' ).'">'.$i.'</a>';
                                }
                                
                                // Next page link
                                if ( $current_page < $total_pages ) {
                                    $next_url = add_query_arg( [
                                        'per_page' => $page_size,
                                        'page_num' => $current_page + 1
                                    ], $current_url_with_dates );
                                    $results .= '<a href="'.$next_url.'" class="gfat-button button button-secondary">'.__( 'Next', 'gf-tools' ).'</a>';
                                }
                                
                                $results .= '</div>';
                            }

                        $results .= '</div>';
                        
                    }

                $results .= '</div>';

                return $results;

            // Report not found
            } else {
                return __( 'Oops! We could not locate your report.', 'gf-tools' );
            }

        // List reports
        } elseif ( $atts[ 'reports' ] ) {

            $reports_list = [];

            if ( $reports_string = sanitize_text_field( $atts[ 'reports' ] ) ) {
                $reports_entries = preg_split( '/,\s*(?![^()]*\))/', $reports_string );
                
                foreach ( $reports_entries as $entry ) {
                    
                    if ( preg_match( '/^(\d+)(?:\s*\(([^)]+)\))?$/', trim( $entry ), $matches ) ) {
                        $id = absint( $matches[1] );
                        $title = isset( $matches[2] ) ? sanitize_text_field( $matches[2] ) : false;
                        
                        if ( !$title ) {
                            if ( $report = get_post( $id ) ) {
                                $title = sanitize_text_field( $report->post_title );
                            } else {
                                continue;
                            }
                        }
                        
                        $reports_list[] = [
                            'id'    => $id,
                            'title' => $title
                        ];
                    }
                }
            }

            if ( !empty( $reports_list ) ) {

                $list = '<ul class="gfat-default-reports-list">';
                    
                    foreach ( $reports_list as $li ) {
                        $url = add_query_arg( $param, $li[ 'id' ], $current_url );
                        $list .= '<li><a href="'.$url.'">'.$li[ 'title' ].'</a></li>';
                    }

                $list .= '</li>';

                return $list;
            }

        // Or else just say it ain't so
        } else {
            return __( 'Oops! We could not locate your report.', 'gf-tools' );
        }

        return '';
    } // End report()


    /**
     * Export entries on the front-end
     * USAGE: [gfat_export_entries id="1" combined="1, 2, 3"]
     *
     * @param array $atts
     * @return string
     */
    public function export_entries( $atts ) {
        // Vars
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Deny access
        $roles = [ 'administrator' ];

        $plugin_settings = (new GF_Advanced_Tools)->get_plugin_settings();
        if ( !empty( $plugin_settings ) ) {
            foreach ( $plugin_settings as $key => $is_selected ) {
                if ( $HELPERS->str_starts_with( $key, 'entries_export_' ) && $is_selected ) {
                    $roles[] = str_replace( 'entries_export_', '', $key );
                }
            }
        }

        if ( !$HELPERS->has_role( $roles ) ) {
            return __( 'Sorry, you do not have access to export form entries.', 'gf-tools' );
        }

        // Get the combined form ids or a single id from the shortcode
        $atts = shortcode_atts( [ 
            'combined' => false,
            'id'       => 0
        ], $atts );
        $combined = $atts[ 'combined' ] ? sanitize_text_field( $atts[ 'combined' ] ) : false;
        $id = absint( $atts[ 'id' ] );

        // Form ids
        $form_ids = [];
        if ( $combined ) {
            $form_ids = explode( ',', $combined );
            $form_ids = array_map( 'trim', $form_ids );
            $form_ids = array_map( 'absint', $form_ids );
        } elseif ( $id ) {
            $form_ids = [ $id ];
        }
        
        // Store the forms here
        $forms = [];
        if ( !empty( $form_ids ) ) {
            foreach ( $form_ids as $form_id ) {
                $form = GFAPI::get_form( $form_id );
                if ( $form ) {
                    $forms[ $form_id ] = $form;
                } else {
                    /* translators: %d is the ID of the form that was not found. */
                    return sprintf( __( "The form with ID '%d' does not exist.", 'gf-tools' ), $form_id );
                }
            }
        }

        // Verify nonce
        $nonce_verified = isset( $_REQUEST[ $this->nonce ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ $this->nonce ] ) ), $this->nonce );
    
        // Check for a form id in the query string
        if ( !$combined && !$id && $nonce_verified && isset( $_POST[ 'form_id' ] ) && absint( $_POST[ 'form_id' ] ) > 0 ) {
            $post_form_id = absint( $_POST[ 'form_id' ] );
            $post_form  = GFAPI::get_form( $post_form_id );
            if ( !$post_form ) {
                /* translators: %d is the ID of the form that was not found. */
                return sprintf( __( "The form with ID '%d' does not exist.", 'gf-tools' ), $post_form_id );
            }
            
            // Verify that this form has permission
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $post_form );
            if ( isset( $form_settings[ 'entries_export' ] ) && $form_settings[ 'entries_export' ] ) {
                $form_ids[] = $post_form_id;
                $forms[ $post_form_id ] = $post_form;
            }
        }

        // If we have a form
        if ( !empty( $form_ids ) ) {

            // Set the search criteria
            $search_criteria = [
                'status' => 'active',
            ];

            // Today
            $today = gmdate( 'Y-m-d' );

            // Store total count here
            $count = 0;

            // Get the first entry date
            $sorting = [ 'key' => 'date_created', 'direction' => 'ASC' ];
            $paging = [ 'offset' => 0, 'page_size' => 1 ];
            
            // Get the first entry
            $first_entry_date = $today;
            foreach ( $form_ids as $form_id ) {
                $total_count = 0;
                $get_entries = GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging, $total_count );
                if ( !empty( $get_entries ) ) {
                    $combined_first_entry = $get_entries[0];
                    if ( $combined_first_entry[ 'date_created' ] < $first_entry_date ) {
                        $first_entry_date = $combined_first_entry[ 'date_created' ];
                    }
                    $count += $total_count;
                }
            }
            
            // Add start description
            $start_desc = ' ('.__( 'first entry date', 'gf-tools' ).')';
            
            // Dates
            $start = gmdate( 'Y-m-d', strtotime( $first_entry_date ) );
            $end = $today;
            $start_display = gmdate( 'F j, Y', strtotime( $start ) );

            // Add the form title(s)
            if ( $combined ) {
                $form_titles = [];
                foreach ( $forms as $form ) {
                    $form_titles[] = $form[ 'title' ];
                }
                $results = '<h2>'.__( 'Combing Forms:', 'gf-tools' ).'</h2>
                <ul>';
                    foreach ( $form_titles as $form_title ) {
                        $results .= '<li><strong>'.$form_title.'</strong></li>';
                    }
                $results .= '</ul>';

            } else {
                $first_form = reset( $forms );
                $form_title = sanitize_text_field( $first_form[ 'title' ] );
                $results = '<h2>'.$form_title.'</h2>';
            }

            // Top message
            if ( $count > 0 ) {
                /* translators: %1$s is the combined string of start display and description. */
                $results .= '<div id="gfat-export-entries-count">'.sprintf( __( 'Total # of Entries Found from %1$s to Present:', 'gf-tools' ), $start_display.$start_desc ).' <span>'.$count.'</span></div><br>';
            } else {
                $results .= '<div>'.__( 'No entries found.', 'gf-tools' ).'</div><br>';
                return $results;
            }
            
            // Change form link
            $current_url = $HELPERS->get_current_url( false );
            if ( !$combined && !$id ) {
                $results .= '&#8592; <a href="'.$current_url.'">'.__( 'Change Form', 'gf-tools' ).'</a><br><br>';
            }
            
            // Only display the field choices if there are entries
            if ( $count > 0 ) {

                // Add instructions
                $results .= '<p>'.__( 'Select Form Fields to Export:', 'gf-tools' ).'</p>';

                // Start the form
                $results .= '<div id="gfat-export-entries-cont">
                    <form id="gfat-export-entries-form" method="post" action="">';

                        $results .= '<div class="form-fields-selectall">
                            <input type="checkbox" id="field-checkbox-selectall" class="field-checkboxes"> <label for="field-checkbox-selectall" class="field-labels" style="display: inline;"><strong>'.__( 'Select All', 'gf-tools' ).'</strong></label>
                        </div>';

                        $field_labels = [];

                        $results .= '<ul>';

                            // Form fields first
                            $fields_to_ignore = [ 'html', 'section' ];
                            foreach ( $forms as $form ) {
                                $form_id = $form[ 'id' ];

                                // Post the form id
                                $results .= '<input type="hidden" name="form_ids[]" value="'.$form_id.'">';

                                // Other entry meta
                                $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
                                
                                $entry_meta = [
                                    'id'           => __( 'Entry ID', 'gf-tools' ),
                                    'date_created' => __( 'Date Created', 'gf-tools' ),
                                    'created_by'   => __( 'Created By', 'gf-tools' ),
                                ];
                                foreach ( $entry_meta as $field_id => $field_label ) {

                                    if ( in_array( $field_label, $field_labels ) ) {
                                        continue;
                                    } else {
                                        $field_labels[] = $field_label;
                                    }

                                    $results .= '<li><input id="checkbox_'.$field_id.'" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'" data-label="'.$field_label.'"/> <label for="checkbox_'.$field_id.'" class="field-labels" style="display: inline;">'.$field_label.'</label></li>';

                                }

                                // Our form fields
                                $field_data = [];

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
                                        $field_id = $field[ 'id' ];
                                        $field_label = $field[ 'label' ];

                                        if ( in_array( $field_label, $field_labels ) ) {
                                            continue;
                                        } else {
                                            $field_labels[] = $field_label;
                                        }
        
                                        $results .= '<li><input id="checkbox_'.$field_id.'" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'" data-label="'.$field_label.'"/> <label for="checkbox_'.$field_id.'" class="field-labels" style="display: inline;">'.$field_label.'</label></li>';

                                    }
                                }

                                // Custom meta
                                if ( isset( $form_settings ) ) {
                                    foreach ( $form_settings as $name => $form_setting ) {
                                        if ( $HELPERS->str_starts_with( $name, 'add_user_meta_' ) && $form_setting == 1 ) {

                                            $field_id = str_replace( 'add_user_meta_', '', $name );
                                            $field_label = $field_id;

                                            if ( in_array( $field_label, $field_labels ) ) {
                                                continue;
                                            } else {
                                                $field_labels[] = $field_label;
                                            }

                                            $results .= '<li><input id="checkbox_'.$field_id.'" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'" data-label="'.$field_label.'"/> <label for="checkbox_'.$field_id.'" class="field-labels" style="display: inline;">'.$field_label.'</label></li>';
                                        }
                                    }
                                }

                                // Fields
                                foreach ( $form[ 'fields' ] as &$field )  {

                                    $type = $field->type;
                                    if ( in_array( $type, $fields_to_ignore ) ) {
                                        continue;
                                    }
    
                                    $field_id = $field->id;
    
                                    $field_label = $field->label;
                                    $field_label = str_replace( ',', '', $field_label );
                                    if ( strpos( $field_label, " (" ) != false ) {
                                        $field_label = substr( $field_label, 0, strpos( $field_label, " (" ) );
                                    }

                                    if ( in_array( $field_label, $field_labels ) ) {
                                        continue;
                                    } else {
                                        $field_labels[] = $field_label;
                                    }
    
                                    // Name
                                    if ( $type == 'name' ) {

                                        /* translators: %s is the label for the first checkbox. */
                                        $first_label = sprintf( __( 'First %s', 'gf-tools' ), 
                                            $field_label 
                                        );
                                        
                                        /* translators: %s is the label for the last checkbox. */
                                        $last_label = sprintf( __( 'Last %s', 'gf-tools' ), 
                                            $field_label 
                                        );
                                        
                                        $results .= '<li><input id="checkbox_'.$field_id.'.3" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'.3" data-label="'.esc_attr( $first_label ).'"/> <label for="checkbox_'.$field_id.'.3" class="field-labels" style="display: inline;">'.esc_html( $first_label ).'</label></li>';
                                        
                                        $results .= '<li><input id="checkbox_'.$field_id.'.6" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'.6" data-label="'.esc_attr( $last_label ).'"/> <label for="checkbox_'.$field_id.'.6" class="field-labels" style="display: inline;">'.esc_html( $last_label ).'</label></li>';
                                        
    
                                    // Surveys
                                    } elseif ( $type == 'survey' && $field->inputType != 'radio' ) {
    
                                        $survey_inputs = $field->inputs;
                                        foreach ( $survey_inputs as $survey_input ) {
    
                                            $survey_input_id = $survey_input[ 'id' ];
                                            $survey_label = $survey_input[ 'label' ];
                                            
                                            $results .= '<li><input id="checkbox_'.$survey_input_id.'" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$survey_input_id.'" data-label="'.$field_label.': '.$survey_label.'"/> <label for="checkbox_'.$survey_input_id.'" class="field-labels" style="display: inline;">'.$field_label.': '.$survey_label.'</label></li>';
                                        }
                                    
                                    // Otherwise just return the field value
                                    } else {
    
                                        $results .= '<li><input id="checkbox_'.$field_id.'" class="field-checkboxes" type="checkbox" name="fields['.$form_id.'][]" value="'.$field_id.'" data-label="'.$field_label.'"/> <label for="checkbox_'.$field_id.'" class="field-labels" style="display: inline;">'.$field_label.'</label></li>';
                                    }        
                                }
                            }

                        $results .= '</ul>';

                        // Date fields
                        $results .= '<div class="date-fields">
                            <div class="date-cont">
                                <label for="field-startdate" class="date-labels">'.__( 'Start Date', 'gf-tools' ).'</label>
                                <input type="date" id="field-startdate" class="field-dates" width="18rem" name="start_date" value="'.$start.'">
                            </div>
                            <div class="date-cont">
                                <label for="field-enddate" class="date-labels">'.__( 'End Date', 'gf-tools' ).'</label>
                                <input type="date" id="field-enddate" class="field-dates" width="18rem" name="end_date" value="'.$end.'">
                            </div>
                        </div>';

                        // Pass the title
                        $results .= '<input type="hidden" name="form_title" value="'.$form_title.'">';
                        
                        // Hidden inputs
                        $results .= wp_nonce_field( $this->nonce, $this->nonce, true, false ).
                        '<input type="hidden" name="count" value="'.$count.'">';

                        // Complete the form
                        $results .= '<input type="submit" name="gfat_entries_export" class="btn btn-export" value="'.__( 'Export CSV', 'gf-tools' ).'" />';

                    // End the form/div
                    $results .= '</form>
                    </div>';
            }

        // If no form id, then display form selection
        } else {

            $results = '<h1>'.__( 'Form Entry Export', 'gf-tools' ).'</h1>';

            $forms = GFAPI::get_forms();
            if ( !empty( $forms ) ) {

                $sorted_forms = [];
                foreach ( $forms as $key => &$form ) {

                    // Skip if not allowed
                    $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
                    if ( isset( $form_settings[ 'entries_export' ] ) && $form_settings[ 'entries_export' ] == 1 ) {
                        $sorted_forms[] = [
                            'title' => sanitize_text_field( $form['title'] ),
                            'id'    => absint( $form[ 'id' ] )
                        ];
                    }
                }

                if ( !empty( $sorted_forms ) ) {
                    sort( $sorted_forms );
                    
                    // Start the form selection
                    $results .= '<br><br><div class="#gfat-export-entries-cont">
                    <form id="gfat-export-entries-form-choice" method="post" action="">
                        '.wp_nonce_field( $this->nonce, $this->nonce, true, false ).'
                        <label for="select-form">'.__( 'Select a Form:', 'gf-tools' ).'</label>
                        <select id="select-form" name="form_id" onchange="this.form.submit();">
                            <option ></option>';

                            // Now add the options
                            foreach ( $sorted_forms as $sorted_form ) {
                                $results .= '<option value="'.$sorted_form[ 'id' ].'">'.$sorted_form[ 'title' ].'</option>';
                            }

                        $results .= '</select>
                        </form>
                    </div>';
                }
            }
        }
    
        // Return
        return $results;
    } // End export_entries()


    /**
     * Export csv
     *
     * @return void
     */
    public function export() {
        if ( !isset( $_POST[ $this->nonce ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST[ $this->nonce ] ) ), $this->nonce ) ) {
            die( esc_html__( 'No funny business.', 'gf-tools' ) );
        }

        // Catch
        // dpr( $_POST );
        $selected_form_ids = isset( $_POST[ 'form_ids' ] ) ? filter_var_array( wp_unslash( $_POST[ 'form_ids' ] ), FILTER_SANITIZE_NUMBER_INT ) : false; // phpcs:ignore 

        if ( isset( $_POST[ 'fields' ] ) ) {
            $selected_fields = filter_var_array( wp_unslash( $_POST[ 'fields' ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ); // phpcs:ignore 
        } else {
            $selected_fields = false;
        }

        $start = isset( $_POST[ 'start_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'start_date' ] ) ) : false;
        $end = isset( $_POST[ 'end_date' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'end_date' ] ) ) : false;
        $page_size = isset( $_POST[ 'count' ] ) ? absint( $_POST[ 'count' ] ) : 1;
        
        if ( !$selected_form_ids || !$start || !$end  ) {
            die( esc_html__( 'Oops! Something went wrong. Missing data in $_POST request.', 'gf-tools' ) );
        }

        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Forms
        $selected_forms = [];
        if ( !empty( $selected_form_ids ) ) {
            foreach ( $selected_form_ids as $selected_form_id ) {
                $form = GFAPI::get_form( $selected_form_id );
                if ( $form ) {
                    $selected_forms[ $selected_form_id ] = $form;
                } else {
                    /* translators: %d is the ID of the form that was not found. */
                    die( esc_html( sprintf( __( "The form with ID '%d' does not exist.", 'gf-tools' ), $selected_form_id ) ) );
                }
            }
        }
        
        $form_count = count( $selected_forms );

        // Date format
        $date_format = 'n/j/Y';

        // Filename
        if ( $form_count > 1 ) {
            $title = __( 'forms', 'gf-tools' ).'-'.implode( '-', $selected_form_ids );
        } else {
            $first_form = reset( $selected_forms );
            $title = sanitize_text_field( $first_form[ 'title' ] );
            $title = ucwords( str_replace( '_', ' ', $title ) );
            $title = str_replace( [ '&#8217;', '\'', '.', ',' ], '' , $title );
            $title = str_replace( ' ', '_', $title );
        }

        $max_title_length = 50;
        if ( strlen( $title ) > $max_title_length ) {
            $title = substr( $title, 0, $max_title_length );
        }
        
        $currentDate = $HELPERS->convert_date_to_wp_timezone( null, 'Y_m_d-H_i_s' );

        $filename = $title.'_'.$currentDate.'.csv';

        // Other labels
        $other_labels = [
            'id'                   => __( 'Entry ID', 'gf-tools' ),
            'date_created'         => __( 'Date Created', 'gf-tools' ),
            'created_by'           => __( 'Created By', 'gf-tools' ),
            'resolved'             => __( 'Resolved', 'gf-tools' ),
            'resolved_date'        => __( 'Resolved Date', 'gf-tools' ),
            'resolved_by'          => __( 'Resolved By', 'gf-tools' ),
            'connected_post_id'    => __( 'Connected Post ID', 'gf-tools' ),
            'connected_post_title' => __( 'Connected Post Title', 'gf-tools' )
        ];

        // Split the field ids and labels
        $header_row = [];

        // Include the form if more than one
        if ( $form_count > 1 ) {
            $header_row[] = __( 'Form', 'gf-tools' );
        }

        // Handle the header row
        $col_data = [];
        $custom_meta = [];
        foreach ( $selected_fields as $form_id => $form_fields ) {

            // Other entry meta
            $form = $selected_forms[ $form_id ];
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );

            // Custom meta
            if ( isset( $form_settings ) ) {
                foreach ( $form_settings as $name => $form_setting ) {
                    if ( $HELPERS->str_starts_with( $name, 'add_user_meta_' ) && $form_setting == 1 ) {

                        $field_id = str_replace( 'add_user_meta_', '', $name );
                        $custom_meta[] = $field_id;
                    }
                }
            }

            // Iter the fields
            foreach ( $form_fields as $field_id ) {

                // Custom meta first
                if ( in_array( $field_id, $custom_meta ) ) {
                    $header_row[] = $field_id;
                    $col_data[] = [
                        'form_id'  => $form_id,
                        'field_id' => $field_id,
                        'label'    => $field_id
                    ];

                // Other labels
                } elseif ( in_array( $field_id, array_keys( $other_labels ) ) ) {
                    $header_row[] = $other_labels[ $field_id ];
                    $col_data[] = [
                        'form_id'  => $form_id,
                        'field_id' => $field_id,
                        'label'    => $other_labels[ $field_id ]
                    ];

                // Form fields
                } else {
                    foreach ( $selected_forms[ $form_id ][ 'fields' ] as $field ) {
                        if ( $field->id != $field_id ) {
                            continue;
                        }
                        $header_row[] = $field->label;
                        $col_data[] = [
                            'form_id'  => $form_id,
                            'field_id' => $field_id,
                            'label'    => $field->label
                        ];
                    }
                }
            }
        }

        // dpr( $_POST );
        // dpr( $selected_forms );
        // dpr( $selected_fields );
        // dpr( $filename );
        // dpr( $header_row );
        // dpr( $col_data );
        // exit();

        // Get all entries
        $search_criteria = [
            'status'     => 'active',
            'start_date' => $start,
            'end_date'   => $end
        ];
        $sorting = [];
        $paging = [ 'offset' => 0, 'page_size' => $page_size ];

        $entries = [];
        foreach ( $selected_form_ids as $selected_form_id ) {
            $get_entries = GFAPI::get_entries( $selected_form_id, $search_criteria, $sorting, $paging );
            if ( !empty( $get_entries ) ) {
                $entries = array_merge( $entries, $get_entries );
            }
        }

        // Sort the array by date_created in descending order
        usort( $entries, function ( $a, $b ) {
            return strtotime( $b[ 'date_created' ] ) - strtotime( $a[ 'date_created' ] );
        } );

        // Create an empty array for the rows
        $data_rows = [];

        // Handle the entries        
        foreach ( $entries as $entry ) {

            $entry_form_id = $entry[ 'form_id' ];
            $row = [];

            if ( $form_count > 1 ) {
                $row[] = $selected_forms[ $entry_form_id ][ 'title' ];
            }

            // Look through the column data
            foreach ( $col_data as $col ) {
                $entry_field_id = false;

                // Use the field id if in this form or if shared field
                if ( $col[ 'form_id' ] == $entry_form_id || in_array( $col[ 'field_id' ], $custom_meta ) || in_array( $col[ 'field_id' ], array_keys( $other_labels ) ) ) {
                    $entry_field_id = $col[ 'field_id' ];

                // Otherwise we need to search for a label match
                } else {
                    
                    foreach ( $selected_forms[ $entry_form_id ][ 'fields' ] as $field ) {
                        if ( $field->label == $col[ 'label' ] ) {
                            $entry_field_id = $field->id;
                            break;
                        }
                    }
                }

                if ( $entry_field_id ) {
                    
                    $value = isset( $entry[ $entry_field_id ] ) ? sanitize_text_field( $entry[ $entry_field_id ] ) : '';
                    $value = $HELPERS->filter_entry_value( $value, $entry_field_id, $entry, $selected_forms[ $entry_form_id ], $date_format, $HELPERS );

                    $row[] = $value;
                } else {
                    $row[] = '';
                }
            }

            $data_rows[] = $row;
        }

        // dpr( $header_row );
        // dpr( $data_rows );
        // exit();

        // Export it as a csv
        $HELPERS->export_csv( $filename, $header_row, $data_rows );
    } // End export()


    /**
     * Enqueue scripts
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        if ( is_admin() ) {
            return;
        }

        $handle = 'gfadvtools_shortcodes';
        wp_register_script( $handle.'_script', GFADVTOOLS_PLUGIN_DIR.'includes/js/shortcodes.js', [ 'jquery' ], time(), true );
        wp_localize_script( $handle.'_script', $handle, [ 
            'check_a_box' => __( 'Please check at least one checkbox before submitting.', 'gf-tools' )
            // 'ajaxurl' => admin_url( 'admin-ajax.php' ) 
        ] );
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( $handle.'_script' );

        wp_enqueue_style( $handle, GFADVTOOLS_PLUGIN_DIR.'includes/css/shortcodes.css', [], time() );
    } // End enqueue_scripts()
    
}