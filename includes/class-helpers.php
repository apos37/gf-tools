<?php
/**
 * Helper functions
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Helpers {

    /**
     * Get available user meta keys
     *
     * @return array
     */
    public function get_available_user_meta_keys( $user_id = null, $incl_label = false, $prefix = '' ) {
        $choices = [];

        // Fetch all meta keys
        global $wpdb;
        if ( !is_null( $user_id ) ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                return []; // Post not found
            }
            
            $user_object_vars = get_object_vars( $user );
            $custom_meta_keys = array_keys( get_post_meta( $user_id ) );
            $meta_keys = array_merge( array_keys( $user_object_vars ), $custom_meta_keys );

        } else {

            // Implement caching for the meta keys
            $cache_key = 'user_meta_keys_all';
            $meta_keys = wp_cache_get( $cache_key );

            if ( false === $meta_keys ) {

                // phpcs:ignore
                $meta_keys = $wpdb->get_col( "
                    SELECT DISTINCT meta_key
                    FROM {$wpdb->usermeta}
                    WHERE user_id IS NOT NULL
                " );

                // Cache the result
                wp_cache_set( $cache_key, $meta_keys );
            }

            $user_object_vars = array_keys( get_object_vars( new WP_User() ) );
            $meta_keys = array_merge( $user_object_vars, $meta_keys );
        }

        // List of meta keys to ignore
        $ignore_list = apply_filters( 'gfadvtools_user_metakey_ignore_list', [
            'rich_editing', 'syntax_highlighting', 'comment_shortcuts', 'admin_color', 'use_ssl', 
            'show_admin_bar_front', 'dismissed_wp_pointers', 'show_welcome_panel', 'community-events-location', 
            'astra-sites-5-start-notice', 'ip_test', 'session_tokens', 'allcaps', 'cap_key', 'caps', 'data', 'filter'
        ] );
        $ignore_list = array_map( 'sanitize_text_field', $ignore_list );

        // Precompute patterns for efficiency
        $patterns = apply_filters( 'gfadvtools_user_metakey_ignore_patterns', [
            'starts_with' => [ 'ddtt_', 'wp_', '_yoast', 'wp_mail_smtp_', 'uagb-', 'tfa_', '_woocommerce_', 'jetpack_', 'wpcf7_', '_jpum_', 'manageedit-', 'message_', 'mh_', 'screen_', 'closedpostboxes_', 'meta-box-', 'edit_', 'nav_menu_', 'gform_', 'aiowps_', '_uag', '_gform', 'learndash_' ],
            'ends_with'   => [ '_help-docs', '_help-doc-imports', '_dashboard', '_post', '_page', '_forms_page_gf_entries' ],
            'contains'    => [ 'metabox' ]
        ] );
        foreach ( $patterns as $key => $values ) {
            $patterns[ $key ] = array_map( 'sanitize_text_field', $values );
        }

        // Iter
        foreach ( $meta_keys as $meta_key ) {

            if ( in_array( $meta_key, $ignore_list ) ) {
                continue;
            }

            $skip = false;

            foreach ( $patterns[ 'starts_with' ] as $key => $pf ) {
                if ( $this->str_starts_with( $meta_key, $pf ) ) {
                    $skip = true;
                    break;
                }
            }

            foreach ( $patterns[ 'ends_with' ] as $suffix ) {
                if ( $this->str_ends_with( $meta_key, $suffix ) ) {
                    $skip = true;
                    break;
                }
            }

            foreach ( $patterns[ 'contains' ] as $substr ) {
                if ( strpos( $meta_key, $substr ) !== false ) {
                    $skip = true;
                    break;
                }
            }

            if ( $skip ) {
                continue;
            }

            if ( $incl_label ) {
                $choices[] = [
                    'label' => $meta_key,
                    'name'  => $prefix.$meta_key,
                ];
            } else {
                $choices[] = $prefix.$meta_key;
            }
        }

        if ( $incl_label ) {
            usort( $choices, function( $a, $b ) {
                return strcmp( $a[ 'name' ], $b[ 'name' ] );
            } );
        } else {
            sort( $choices );
        }

        return $choices;
    } // End get_available_user_meta_keys()


    /**
     * Get available post meta keys
     *
     * @return array
     */
    public function get_available_post_meta_keys( $post_id = null, $incl_label = false ) {
        $choices = [];

        // Fetch all meta keys
        if ( !is_null( $post_id ) ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                return []; // Post not found
            }
            
            $post_object_vars = get_object_vars( $post );
            unset( $post_object_vars[ 'post_content' ] );
            $custom_meta_keys = array_keys( get_post_meta( $post_id ) );
            $meta_keys = array_merge( array_keys( $post_object_vars ), $custom_meta_keys );

        } else {

            // Implement caching for the meta keys
            $cache_key = 'post_meta_keys_all';
            $meta_keys = wp_cache_get( $cache_key );

            if ( false === $meta_keys ) {
            
                global $wpdb;

                // phpcs:ignore
                $meta_keys = $wpdb->get_col( "
                    SELECT DISTINCT meta_key
                    FROM {$wpdb->postmeta}
                    WHERE meta_key IS NOT NULL
                " );

                // Cache the result
                wp_cache_set( $cache_key, $meta_keys );
            }

            $post_object_vars = array_keys( get_object_vars( new WP_Post() ) );
            $meta_keys = array_merge( $post_object_vars, $meta_keys );
        }

        // List of meta keys to ignore
        $ignore_list = apply_filters( 'gfadvtools_post_metakey_ignore_list', [
            'source', 'slide_template', '_no_alt', '_dp_original', 'footnotes', 'guid'
        ] );
        $ignore_list = array_map( 'sanitize_text_field', $ignore_list );

        // Precompute patterns for efficiency
        $patterns = apply_filters( 'gfadvtools_post_metakey_ignore_patterns', [
            'starts_with' => [ '__wpdm', '_cornerstone', '_cs', '_da', '_edit_', '_jetpack', '_menu', '_oembed', '_search', '_publicize', '_thumbnail', '_ubermenu', '_wp_', '_wpa', '_wpcode', '_x_', '_yoast', 'cs_', 'eg_', 'file_', 'helpdocs_', 'media_', 'menu-', 'rf_', 'rs_', 'the_grid_item', 'wpem_', 'x_', 'content_', '_astra_sites', '_customize', '_uag', '_wxr', 'adv-header-', 'ast_', 'ast-', 'astra-', 'font-', 'fonts-', 'classic-editor-', 'site-', 'stick-', 'theme-', 'wpforms_' ],
            'ends_with'   => [ '-columns' ],
            'contains'    => [ '_css_' ]
        ] );
        foreach ( $patterns as $key => $values ) {
            $patterns[ $key ] = array_map( 'sanitize_text_field', $values );
        }

        // Iter
        foreach ( $meta_keys as $meta_key ) {

            if ( in_array( $meta_key, $ignore_list ) ) {
                continue;
            }

            $skip = false;

            foreach ( $patterns[ 'starts_with' ] as $key => $prefix ) {
                if ( $this->str_starts_with( $meta_key, $prefix ) ) {
                    $skip = true;
                    break;
                }
            }

            foreach ( $patterns[ 'ends_with' ] as $suffix ) {
                if ( $this->str_ends_with( $meta_key, $suffix ) ) {
                    $skip = true;
                    break;
                }
            }

            foreach ( $patterns[ 'contains' ] as $substr ) {
                if ( strpos( $meta_key, $substr ) !== false ) {
                    $skip = true;
                    break;
                }
            }

            if ( $skip ) {
                continue;
            }

            if ( $incl_label ) {
                $choices[] = [
                    'label' => $meta_key,
                    'name'  => $meta_key,
                ];
            } else {
                $choices[] = $meta_key;
            }
        }

        if ( $incl_label ) {
            usort( $choices, function( $a, $b ) {
                return strcmp( $a[ 'name' ], $b[ 'name' ] );
            } );
        } else {
            sort( $choices );
        }

        return $choices;
    } // End get_available_post_meta_keys()


    /**
     * Return entries for a specific user
     *
     * @param int $form_id
     * @param int $user_id
     * @param boolean $ids_only
     * @return array
     */
    public function get_entries_for_user( $form_id, $user_id, $ids_only = false ) {
        $search_criteria = [
            'status'        => 'active',
            'field_filters' => [
                'mode'      => 'any',
                [
                    'key'      => 'created_by',
                    'operator' => 'is',
                    'value'    => $user_id
                ]
            ]
        ];

        $entries = GFAPI::get_entries( $form_id, $search_criteria );
        if ( !empty( $entries ) ) {
            
            $data = [];
            foreach ( $entries as $entry ) {
                $data[] = $ids_only ? $entry[ 'id' ] : $entry;
            }

            return $data;
        }
        
        return [];
    } // End get_entries_for_user()


    /**
     * Quarter Links
     *
     * @param string $base_url
     * @param boolean $this_year_start_of_month
     * @param boolean $ff
     * @return string
     */
    public function quarter_links( $base_url, $ff = false ) {
        // Set up years
        $current_year = gmdate( 'Y' );
        $previous_year = gmdate( 'Y', strtotime( '-1 year' ) );
    
        // Store the links here
        $links = [
            '<a class="quarter-links all gfat-button button" href="' . $base_url . '">All</a>'
        ];

        // Get current
        $start_date = isset( $_GET[ 'start_date' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'start_date' ] ) ) : false;  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $end_date = isset( $_GET[ 'end_date' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'end_date' ] ) ) : false;  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    
        // Start the container
        $results = '<div id="quarter-links">';
    
            // Cycle through each quarter
            for ( $q = 1; $q < 5; $q++ ) {
        
                // Determine the start and end months for the quarter 
                if ( $ff ) {
                    // Federal fiscal year quarters
                    switch ( $q ) {
                        case 1:
                            $s = 10; $e = 12; // Q1: October - December
                            $year_offset = $previous_year;
                            break;
                        case 2:
                            $s = 1; $e = 3; // Q2: January - March
                            $year_offset = $current_year;
                            break;
                        case 3:
                            $s = 4; $e = 6; // Q3: April - June
                            $year_offset = $current_year;
                            break;
                        case 4:
                            $s = 7; $e = 9; // Q4: July - September
                            $year_offset = $current_year;
                            break;
                    }
                } else {
                    // Regular calendar quarters
                    switch ( $q ) {
                        case 1:
                            $s = 1; $e = 3; // Q1: January - March
                            $year_offset = $current_year;
                            break;
                        case 2:
                            $s = 4; $e = 6; // Q2: April - June
                            $year_offset = $current_year;
                            break;
                        case 3:
                            $s = 7; $e = 9; // Q3: July - September
                            $year_offset = $current_year;
                            break;
                        case 4:
                            $s = 10; $e = 12; // Q4: October - December
                            $year_offset = $current_year;
                            break;
                    }
                }
        
                // Determine the start and end dates for the quarter
                $start = gmdate( 'Y-m-d', strtotime( $year_offset . '-' . $s . '-01' ) );
                $end = gmdate( 'Y-m-d', strtotime( $year_offset . '-' . $e . '-01 +1 month -1 day' ) );
        
                // Determine class and year display
                if ( $year_offset == $current_year ) {
                    $class = ' this-year';
                    $year_display = $current_year;
                } else {
                    $class = ' last-year';
                    $year_display = $previous_year;
                }

                if ( $start_date == $start && $end_date == $end ) {
                    $class .= ' current';
                }
        
                // Make the link
                $url = add_query_arg( [
                    'start_date' => $start,
                    'end_date'   => $end
                ], $base_url );
                
                $links[] = '<a class="quarter-links gfat-button button' . $class . '" href="' . $url . '" title="' . gmdate( 'F j, Y', strtotime( $start ) ) . ' to ' . gmdate( 'F j, Y', strtotime( $end ) ) . '">Q' . $q . ' (' . $year_display . ')</a>';
            }
        
            // Implode the links with a separator
            $results .= implode( '', $links );
    
        // End the container
        $results .= '</div>';
    
        return $results;
    } // End quarter_links()
    
    
    /**
     * Handling ajax if they are not logged in
     *
     * @return void
     */
    public function ajax_must_login() {
        error_log( 'Attempt to use Ajax on nopriv.' );

        $request_uri = isset( $_SERVER[ 'REQUEST_URI' ] ) ? filter_var( wp_unslash( $_SERVER[ 'REQUEST_URI' ] ), FILTER_SANITIZE_URL ) : '';
        $redirect_url = wp_login_url( $request_uri );

        wp_send_json_error( [
            'redirect' => $redirect_url
        ] );
    } // End ajax_must_login()


    /**
     * Display a code box
     *
     * @param string $code
     * @return string
     */
    public function display_code_box( $code ) {
        // Convert
        $code = htmlspecialchars( $code, ENT_QUOTES, 'UTF-8' );

        $code = str_replace( "\\n", "<br>", $code );

        $results = '<div class="code-box">
            <pre>'.$code.'</pre>
            <button class="copy-button">Copy to Clipboard</button>
        </div>';

        return $results;
    } // End display_code_box()


    /**
     * Convert a date to the WP timezone
     *
     * @param string $date_string
     * @param string $format
     * @param string|null $timezone_string
     * @return string
     */
    public function convert_date_to_wp_timezone( $date_string = null, $format = 'Y-m-d H:i:s', $timezone_string = null ) {
        try {
            if ( is_null( $date_string ) ) {
                $date_string = gmdate( 'Y-m-d H:i:s' );
            }
            if ( is_null( $timezone_string ) ) {
                $timezone_string = wp_timezone_string();
            }
            
            $utc_timezone = new DateTimeZone( 'UTC' );
            $date = new DateTime( $date_string, $utc_timezone );
            $wp_timezone = new DateTimeZone( $timezone_string );
            $date->setTimezone( $wp_timezone );
    
            return $date->format( $format );

        } catch ( Exception $e ) {
            return $date_string;
        }

    } // End convert_date_to_wp_timezone()


    /**
     * Get current URL with query string
     *
     * @param boolean $params
     * @param boolean $domain
     * @return string
     */
    public function get_current_url( $params = true, $domain = true ) {
        if ( $domain === true ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $protocol = isset( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off' ? 'https' : 'http';
                $domain_without_protocol = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                $domain = $protocol.'://'.$domain_without_protocol;
            } else {
                // Handle case where HTTP_HOST is not set
                $domain = 'http://localhost';
            }
    
        } elseif ( $domain === 'only' ) {
            // Check if HTTP_HOST is set before using it
            if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
                $domain = sanitize_text_field( wp_unslash( $_SERVER[ 'HTTP_HOST' ] ) );
                return $domain;
            } else {
                return 'localhost';
            }
    
        } else {
            $domain = '';
        }
    
        $uri = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL );
        $full_url = $domain.$uri;
    
        if ( !$params ) {
            return strtok( $full_url, '?' );
        } else {
            return $full_url;
        }
    } // End get_current_url()
    

    /**
     * Check if it's safe to update custom fields on posts when saving
     *
     * @param int $post_id
     * @param string|array $the_post_type
     * @return bool
     */
    public function can_save_post( $post_id, $the_post_type ) {
        // Validate post type
        global $post_type;
        if ( ( is_array( $the_post_type ) && !in_array( $post_type, $the_post_type ) ) ||
             ( !is_array( $the_post_type ) && $the_post_type !== $post_type ) ) {
            return false;
        }
    
        // Common checks to prevent saving
        $post_status = get_post_status( $post_id );
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
             defined( 'DOING_AJAX' ) && DOING_AJAX ||
             $post_status === 'auto-draft' || $post_status === 'trash' ||
             wp_is_post_revision( $post_id ) ) {
            return false;
        }
    
        // All checks passed
        return true;
    } // End can_save_post()


    /**
     * Return GF Checkbox Values as "String"
     *
     * @param array|int $form
     * @param array $entry
     * @param int $field_id
     * @return string
     */
    public function checkbox_values( $form_or_id, $entry, $field_id ) {
        $field = GFAPI::get_field( $form_or_id, $field_id );
        return $field->get_value_export( $entry );
    } // End checkbox_values()


    /**
	 * Get the multiselect values
	 *
	 * @param string|false $value
	 * @return string
	 */
	public function get_list_values( $value ) {
		if ( $value && $value != '' ) {
			$value = implode( ', ', unserialize( $value ) );
			return esc_html( $value );
		}
		return '';
	} // End get_list_values()


	/**
	 * Get the multiselect values
	 *
	 * @param string|false $value
	 * @return string
	 */
	public function get_multiselect_values( $value ) {
		if ( $value && $value != '' ) {
			$value = implode( ', ', $this->to_array( $value ) );
			return esc_html( $value );
		}
		return '';
	} // End get_multiselect_values()


	/**
	 * Convert string array to array
	 *
	 * @param string $value
	 * @return array
	 */
	public function to_array( $value ) {
		if ( empty( $value ) ) {
			return array();
		} elseif ( is_array( $value ) ) {
			return $value;
		} elseif ( $value[0] !== '[' ) {
			return array_map( 'trim', explode( ',', $value ) );
		} else {
			$json = json_decode( $value, true );
			return $json == null ? array() : $json;
		}
	} // End to_array()

    
    /**
     * Create a multidemsional search function to get key of correct answer
     *
     * @param array $parents
     * @param array $searched
     * @return int|false
     */
    public function multidimensional_search( $parents, $searched ) {
        if ( empty( $searched ) || empty( $parents ) ) {
            return false;
        }

        foreach ( $parents as $key => $value ) {
            $exists = true;
            foreach ( $searched as $skey => $svalue ) {
                $exists = ( $exists && IsSet( $parents[ $key ][ $skey ] ) && $parents[ $key ][ $skey ] == $svalue );
            }
            if ( $exists ) { 
                return $key; 
            }
        }
    
        return false;
    } // End multidimensional_search()


    /**
     * Filter the report entry value
     *
     * @param string $value
     * @param int $field_id
     * @param int $entry_id
     * @param int $form_id
     * @param string $date_format
     * @return string
     */
    public function filter_entry_value( $value, $field_id, $entry, $form, $date_format = 'n/j/Y', $HELPERS = null, $export = true ) {
        // Form and field
        $field = false;
        foreach ( $form[ 'fields' ] as $form_field ) {
            if ( $form_field->id == $field_id ) {
                $field = $form_field;
                break;
            }
        }
        
        // Is a time field
        $is_date = false;

        // Fields
        if ( $field ) {

            // So we don't convert times to date format
            if ( $field->type == 'date' ) {
                $is_date = true;
            }

            // Consent fields
            if ( $field->type == 'consent' ) {
                if ( isset( $value ) && $value == 1 || ( isset( $entry[ $field_id.'.1' ] ) && $entry[ $field_id.'.1' ] == 1 ) ) {
                    $value = 'True';
                }

            // List field
            } elseif ( $field->type == 'list' || ( $field->type == 'post_custom_field' && $field->inputType == 'list' ) ) {
                if ( $value ) {
                    $data = unserialize( $value );
                    if ( is_array( $data ) ) {
                        $data_list = [];
                        $data_text = '';
                        foreach ( $data as $key => $row ) {
                            if ( !is_array( $row ) ) {
                                $data_list[] = $row;
                            } else {
                                $data_text .= $key.': ';
                                $data_text .= implode( ', ', $row );
                                $data_text .= $export ? "\n" : '<br>';
                            }
                        }

                        if ( !empty( $data_list ) ) {
                            $value = implode( ', ', $data_list );
                        } else {
                            $value = trim( $data_text );
                        }
                    }
                }

            // Everything else
            } else {
                $value = sanitize_text_field( $value );
                $value = $field->get_value_export( $entry );
            }

        // Check custom meta
        } elseif ( !$value ) {
            $value = gform_get_meta( $entry[ 'id' ], $field_id );
        }

        // Created by
        if ( $field_id == 'created_by' && $value > 0 ) {
            if ( $user = get_userdata( $value ) ) {
                $value = $user->display_name;
            } else {
                $value = __( 'Guest', 'gf-tools' );
            }
        }

        // Mark resolved
        if ( $field_id == 'resolved' && $value ) {
            $value = ucwords( str_replace( '_', ' ', $value ) );
        } elseif ( $field_id == 'resolved_by' && $value > 0 ) {
            if ( $user = get_userdata( $value ) ) {
                $value = $user->display_name;
            } else {
                $value = __( 'User ID ', 'gf-tools' ).$value;
            }

        // Connected post title
        } elseif ( $field_id == 'connected_post_title' ) {
            $connected_post_id = isset( $entry[ 'connected_post_id' ] ) ? $entry[ 'connected_post_id' ] : 0;
            if ( $connected_post_id ) {
                $value = get_the_title( $connected_post_id );
            }
        }

        // Format dates
        $date_fields = [
            'date_created',
            'resolved_date'
        ];
        if ( $value && ( in_array( $field_id, $date_fields ) || $is_date ) ) {
            $value = gmdate( $date_format, strtotime( $value ) );
        }

        // Allow others
        $value = wp_kses_post( apply_filters( 'gfadvtools_export_value', $value, $field_id, $entry, $form, $export ) );

        // String replace
        if ( $value ) {
            $value = str_replace( '–','-', $value );
        }

        return $value;
    } // End filter_entry_value()


    /**
     * Export CSV
     *
     * @param [type] $filename
     * @param [type] $header_row
     * @param [type] $data_rows
     * @param string $content_type
     * @return void
     */
    public function export_csv( $filename, $header_row, $data_rows, $content_type = 'text/csv; charset=UTF-8', $content_encoding = false ) {
        // Set the headers for the file download
        if ( $content_encoding ) {
            header( 'Content-Encoding: UTF-8' );
        }
        header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
        header( 'Content-Description: File Transfer' );
        header( 'Content-Transfer-Encoding: UTF-8' );
        header( 'Content-Type: ' . $content_type );
        header( "Content-Disposition: attachment; filename={$filename}" );
        header( 'Expires: 0' );
        header( 'Pragma: public' );
    
        // Output BOM
        echo esc_html( chr(0xEF) . chr(0xBB) . chr(0xBF) ); // Write BOM

        // Output header row
        if ( !empty( $header_row )) {
            echo wp_kses_post( implode( ',', array_map( [ $this, 'esc_csv' ], $header_row ) ) . PHP_EOL ); // Ensure CSV-safe values
        }

        // Output data rows
        if ( !empty( $data_rows ) ) {
            foreach ( $data_rows as $data_row ) {
                if ( is_array( $data_row ) ) {
                    echo wp_kses_post( implode( ',', array_map( [ $this, 'esc_csv' ], $data_row ) ) . PHP_EOL ); // Ensure CSV-safe values
                }
            }
        }

        // Ensure the script terminates after output
        exit;
    } // End export_csv()


    /**
     * Helper function to escape CSV values
     *
     * @param string $value
     * @return string
     */
    private function esc_csv( $value ) {
        // Escape CSV special characters
        return '"' . str_replace( '"', '""', $value ) . '"';
    } // End esc_csv()


    /**
     * Remove query strings from url without refresh
     *
     * @param null|string|array $qs
     * @param boolean $is_admin
     * @return void
     */
    public function remove_qs_without_refresh( $qs = null, $is_admin = true ) {
        // Get the current title
        $page_title = get_the_title();

        // Get the current url without the query string
        if ( !is_null( $qs ) ) {

            // Check if $qs is an array
            if ( !is_array( $qs ) ) {
                $qs = [ $qs ];
            }
            $new_url = remove_query_arg( $qs, $this->get_current_url() );

        } else {
            $new_url = $this->get_current_url( false );
        }

        // Write the script
        $args = [ 
            'title' => $page_title,
            'url'   => $new_url
        ];

        // Admin or not
        if ( $is_admin ) {
            $hook = 'admin_footer';
        } else {
            $hook = 'wp_footer';
        }

        // Enqueue the script only when the shortcode is used
        wp_enqueue_script( 'gfadvtools_remove_qs', GFADVTOOLS_PLUGIN_DIR.'includes/js/remove-qs.js', [ 'jquery' ], time(), true );

        // Add the script to the admin footer
        add_action( $hook, function() use ( $args ) {
            wp_localize_script( 'gfadvtools_remove_qs', 'gfadvtools_remove_qs', [
                'title' => $args[ 'title' ],
                'url'   => $args[ 'url' ]
            ] );
        } );

        // Return
        return;
    } // End remove_qs_without_refresh()


    /**
     * Check if the current user has a role
     *
     * @param string|array $role
     * @return bool
     */
    public function has_role( $role, $user_id = null ) {
        if ( is_null( $user_id ) ) {
            $user_id = get_current_user_id();
        }

        $user = get_userdata( $user_id );
        if ( $user && isset( $user->roles ) && is_array( $user->roles ) ) {

            $user_roles = $user->roles;

            if ( !is_array( $role ) ) {
                $role = [ $role ];
            }

            foreach ( $role as $r ) {
                if ( in_array( $r, $user_roles ) ) {
                    return true;
                }
            }
        }
        return false;    
    } // End has_role()


    /**
     * Get a list of our plugins
     *
     * @return void
     */
    public function apos37_plugin_links() {
        $results = '<h2>'.__( 'Try My Other Plugins', 'gf-tools' ).'</h2>
        <div class="apos37-plugins"><ul>';

            $plugins = [
                'gf-discord'           => 'Add-On for Discord and Gravity Forms',
                'gf-msteams'           => 'Add-On for Microsoft Teams and Gravity Forms',
                'admin-help-docs'      => 'Admin Help Docs',
                'broken-link-notifier' => 'Broken Link Notifier',
                'dev-debug-tools'      => 'Developer Debug Tools',
            ];

            $base_url = 'https://wordpress.org/plugins/';
            foreach ( $plugins as $slug => $name ) {
                $results .= '<li><a class="apos37-plugin" href="'.$base_url.$slug.'" target="_blank">'.$name.'</a></li>';
            }

        $results .= '</ul></div>';

        $allowed_html = [
            'h2'  => [],
            'div' => [
                'class'  => []
            ],
            'ul'  => [],
            'li'  => [],
            'a'   => [
                'href'   => [],
                'class'  => [],
                'target' => [],
                'style'  => []
            ]
        ];
        echo wp_kses( $results, $allowed_html );
    } // End apos37_plugin_links()


    /**
     * Check if a string starts with something
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public function str_starts_with( $haystack, $needle ) {
        return strpos( $haystack, $needle ) === 0;
    } // End str_starts_with()


    /**
     * Check if a string ends with something
     *
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public function str_ends_with( $haystack, $needle ) {
        return $needle !== '' && substr( $haystack, -strlen( $needle ) ) === (string)$needle;
    } // End str_ends_with()
}