<?php
/**
 * Spam class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Spam {

    /**
     * Store the plugin settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings;


    /**
     * Table suffix
     *
     * @var string
     */
    private $table_suffix = 'gfat_spam_list';


    /**
     * API Namespace
     *
     * @var string
     */
    private $api_namespace = 'gf-tools/v';


    /**
     * API Version
     *
     * @var string
     */
	private $api_version = 1;


    /**
     * API Key Option
     *
     * @var string
     */
    private $api_key_option = 'gfat_spam_api_key';


    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_change_entry_status';


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings = null ) {

        // Update the plugin settings
        if ( is_null( $plugin_settings ) ) {
            $plugin_settings = (new GF_Advanced_Tools)->get_plugin_settings();
        }
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
        
        // Location
        $location = isset( $this->plugin_settings[ 'spam_filtering' ] ) ? sanitize_key( $this->plugin_settings[ 'spam_filtering' ] ) : false;

        if ( $location == 'host' ) {

            // Register the routes
            add_action( 'rest_api_init', [ $this, 'register_api_routes' ] );

            // Ajax
            add_action( 'wp_ajax_gfat_generate_api_key', [ $this, 'ajax_generate_api_key' ] );
            add_action( 'wp_ajax_nopriv_gfat_generate_api_key', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );

            // JQuery
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        }

        if ( $location ) {

            // Add a link in the toolbar
            add_filter( 'gform_toolbar_menu', [ $this, 'toolbar' ], 10, 2 );
        }

        if ( $location || ( isset( $plugin_settings[ 'disable_akismet' ] ) && $plugin_settings[ 'disable_akismet' ] != '' ) ) {

            // Block blacklisted items from being submitted in the first place
            add_filter( 'gform_field_validation', [ $this, 'block_blacklist' ], 10, 4 );
            
            // Allow items that are otherwise marked as spam, and double check blacklisted items if manually set to true
            add_filter( 'gform_entry_is_spam', [ $this, 'filter_spam' ], 9999, 3 ); 
        }

        // Block links
        if ( isset( $plugin_settings[ 'block_links' ] ) && $plugin_settings[ 'block_links' ] == 1 ) {
            add_filter( 'gform_field_validation', [ $this, 'block_links' ], 10, 4 );
        }

	} // End __construct()


    /**
     * This is an example of using the plugin settings value inside the function
     *
     * @param int|float $position
     * @return int|float
     */
    public function create_spam_list_table() {
        (new GF_Advanced_Tools_Helpers)->remove_qs_without_refresh( [ 'create_db', '_wpnonce' ] );

        // Verify that we need to
        if ( isset( $this->plugin_settings[ 'remote_spam_list' ] ) ) {
            $location = sanitize_key( $this->plugin_settings[ 'remote_spam_list' ] );
            if ( !$location || $location == 'client' ) {
                return false;
            }
        }
        if ( get_option( 'gfat_spam_list_table_created' ) ) {
            return false;
        }

        global $wpdb;
    
        // Define the table name with WordPress prefix
        $table_name = $wpdb->prefix.$this->table_suffix;

        // Define the character set and collation
        $charset_collate = $wpdb->get_charset_collate();

        // SQL statement to create the table
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            value VARCHAR(255) NOT NULL UNIQUE,
            type VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user BIGINT(20) NOT NULL,
            site VARCHAR(255)
        ) $charset_collate;";

        // Include the WordPress upgrade function
        require_once( GFADVTOOLS_ADMIN_INCLUDES_URL.'/upgrade.php' );

        // Add it
        $result = dbDelta( $sql );
        if ( $result ) {
            update_option( 'gfat_spam_list_table_created', time() );
        } else {
            $error = __( 'Advanced Tools for Gravity Forms: Error creating database table for spam list.', 'gf-tools' );
            error_log( $error );
            GFCommon::log_error( $error );
        }
        return $result;
    } // End create_spam_list_table()


    /**
     * Delete the spam list table
     *
     * @return void
     */
    public function delete_spam_list_table() {
        (new GF_Advanced_Tools_Helpers)->remove_qs_without_refresh( [ 'delete_db', '_wpnonce' ] );

        // Verify that we can
        if ( isset( $this->plugin_settings[ 'spam_filtering' ] ) ) {
            $location = sanitize_key( $this->plugin_settings[ 'spam_filtering' ] );
            if ( !$location || $location == 'client' ) {
                return false;
            }
        }
        if ( !get_option( 'gfat_spam_list_table_created' ) ) {
            return false;
        }

        global $wpdb;
    
        // Define the table name with WordPress prefix
        $table_name = $wpdb->prefix.$this->table_suffix;
    
        // Check if the table exists before attempting to drop it
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name ) { // phpcs:ignore
            
            // SQL statement to drop the table
            $sql = "DROP TABLE $table_name;"; // phpcs:ignore
    
            // Execute the SQL statement
            $result = $wpdb->query( $sql ); // phpcs:ignore
    
            if ( $result !== false ) {
                
                // Remove the option to indicate that the table was created
                delete_option( 'gfat_spam_list_table_created' );

                // Remove the option that we stored the api key in
                delete_option( 'gfat_spam_api_key' );

                // Invalidate cache related to spam list
                // wp_cache_delete( 'spam_list_data' );

                // Log it
                /* translators: %s is the name of the add-on indicating the source of the message. */
                $error = sprintf( __( '%s: Spam list table has been dropped per user request.', 'gf-tools' ), GFADVTOOLS_NAME );
                error_log( $error );
                GFCommon::log_error( $error );

                return true;

            } else {
                /* translators: %s is the name of the add-on indicating the source of the error. */
                $error = sprintf( __( '%s: Error dropping database table for spam list.', 'gf-tools' ), GFADVTOOLS_NAME );
                error_log( $error );
                GFCommon::log_error( $error );
            }

        } else {
            $result = false;
            /* translators: %s is the name of the add-on indicating the source of the message. */
            $error = sprintf( __( '%s: Spam list table does not exist, nothing to delete.', 'gf-tools' ), GFADVTOOLS_NAME );
            error_log( $error );
            GFCommon::log_error( $error );
        }
    
        return false;
    } // End delete_spam_list_table()
    

    /**
     * Add or update a local record
     *
     * @param string $value
     * @param string $type
     * @param string $action
     * @param int|null $user
     * @param string|null $site
     * @return array
     */
    public function add_or_update_local_record( $value, $type, $action, $user = null, $site = null ) {
        global $wpdb;
        $table_name = $wpdb->prefix.$this->table_suffix;
    
        $user = $user !== null ? $user : get_current_user_id();

        // Generate a unique cache key based on the value
        $value = sanitize_text_field( $value );
        // $cache_key = 'local_record_exists_' . md5( $value );
    
        // Check if the record already exists
        $existing = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore
            "SELECT COUNT(*) FROM $table_name WHERE value = %s", // phpcs:ignore
            $value
        ) );
    
        if ( $existing > 0 ) {

            // Prepare the update query with placeholders
            $update_query = "UPDATE $table_name SET
                `type` = %s,
                `action` = %s,
                `user` = %d,
                `site` = %s,
                `date` = %s
            WHERE `value` = %s";

            // Prepare the data and format
            $update_data = [
                $type,
                $action,
                $user,
                $site,
                gmdate( 'Y-m-d H:i:s' ),
                $value
            ];
            $prepare = $wpdb->prepare( $update_query, $update_data ); // phpcs:ignore

            // Execute the prepared query
            $result = $wpdb->query( $prepare ); // phpcs:ignore

            if ( $result === false ) {
                // Log the last SQL query and error if the update failed
                $error_message = $wpdb->last_error;
                $last_query = $wpdb->last_query;
                return [
                    'result' => 'error',
                    'msg'    => "Update failed: $error_message. Query: $last_query"
                ];

            } elseif ( $result === 0 ) {
                // If no rows were affected, it may mean no actual change
                return [
                    'result' => 'info',
                    'msg'    => 'no changes detected'
                ];
            }

            // Invalidate the cache after successful update
            // wp_cache_delete( $cache_key );

            return [ 'result' => 'success', 'msg' => 'updated' ];

        } else {

            // Check for missing parameters before adding new record
            if ( $type === null || $action === null || $user === null ) {
                return [
                    'result' => 'error',
                    'msg'    => 'missing parameters'
                ];
            }
    
            // Perform the insert
            $result = $wpdb->insert( // phpcs:ignore
                $table_name,
                [
                    'value'  => $value,
                    'type'   => $type,
                    'action' => $action,
                    'user'   => $user,
                    'site'   => $site,
                ],
                [ '%s', '%s', '%s', '%d', '%s' ]
            );
    
            // Invalidate cache if the insert was successful
            if ( $result !== false ) {
                // wp_cache_delete( $cache_key );
                return [ 'result' => 'success', 'msg' => 'added' ];
            } else {
                return [ 'result' => 'error', 'msg' => 'insert failed' ];
            }
        }
    } // End add_or_update_local_record()


    /**
     * Add or update records in bulk by importing them from a CSV in the import/export page.
     *
     * @param array $csv_data
     * @param int|null $user
     * @return boolean
     */
    public function add_or_update_bulk_local_records( $csv_data, $user = null, $chunk_size = 100 ) {
        $user = $user !== null ? $user : get_current_user_id();
        $site = null;

        // Chunk the CSV data into smaller arrays
        $chunks = array_chunk( $csv_data, $chunk_size );

        // Loop through each chunk of data
        foreach ( $chunks as $chunk ) {

            // Process each chunk by iterating through its rows
            foreach ( $chunk as $row ) {

                // Assuming the row contains the value and action
                $value = strtolower( sanitize_text_field( $row[0] ) );
                $action = sanitize_key( $row[1] );

                // Skip invalid rows (empty value or invalid action)
                if ( empty( $value ) || ( $action !== 'allow' && $action !== 'deny' ) ) {
                    continue;
                }

                // The type
                $type = $this->determine_type( $value );

                // Call the existing add_or_update_local_record() function for each row
                $result = $this->add_or_update_local_record( $value, $type, $action, $user, $site );

                // Check for error in individual record processing
                if ( $result[ 'result' ] === 'error' ) {
                    return false; // Exit early if an error occurs
                }
            }
        }

        return true; // Return true if everything went smoothly
    } // End add_or_update_bulk_local_records()

    
    /**
     * Remove a spam record
     *
     * @param string $value
     * @return boolean
     */
    public function remove_local_record( $value ) {
        global $wpdb;
        $table_name = $wpdb->prefix.$this->table_suffix;
    
        // Sanitize the value
        $sanitized_value = sanitize_text_field( $value );

        // Generate a unique cache key based on the value
        // $cache_key = 'local_record_exists_' . md5( $sanitized_value );

        // Perform the deletion
        $result = $wpdb->delete( // phpcs:ignore
            $table_name,
            [ 'value' => $sanitized_value ],
            [ '%s' ]
        );
    
        // Return the appropriate message based on the result
        if ( $result === false ) {
            // If deletion failed, return an error
            return [
                'result' => 'error',
                'msg'    => 'Deletion failed.'
            ];
        } elseif ( $result === 0 ) {
            // If no rows were affected, the record might not exist
            return [
                'result' => 'error',
                'msg'    => 'No record found to delete.'
            ];
        }

        // If deletion was successful, invalidate the cache
        // wp_cache_delete( $cache_key );

        // If deletion was successful
        return [
            'result' => 'success',
            'msg'    => 'removed'
        ];
    } // End remove_local_record()


    /**
     * Remove 'delete' query arg from pagination
     *
     * @param string $url
     * @return string
     */
    public function remove_delete_query_arg( $url ) {   
        return remove_query_arg( 'delete', $url );
    } // End remove_delete_query_arg()


    /**
     * Remove bulk local records
     *
     * @param array $values
     * @param integer $chunk_size
     * @return boolean
     */
    public function remove_bulk_local_records( $values, $chunk_size = 100 ) {
        // Chunk the array of values into smaller arrays
        $chunks = array_chunk( $values, $chunk_size );
    
        // Loop through each chunk of values
        foreach ( $chunks as $chunk ) {

            // Process each chunk by iterating through its values
            foreach ( $chunk as $value ) {

                // Skip empty values
                if ( empty( $value ) ) {
                    continue;
                }
    
                // Call the existing remove_local_record() function for each value
                $result = $this->remove_local_record( $value );
    
                // Check for error in individual record processing
                if ( $result[ 'result' ] === 'error' ) {
                    return false; // Exit early if an error occurs
                }
            }
        }
    
        return true; // Return true if everything went smoothly
    } // End remove_bulk_local_records() 
    
    
    /**
     * Check if a local record exists
     *
     * @param string $value
     * @return boolean
     */
    public function local_record_exists_by_value( $value ) {
        global $wpdb;
        $table_name = $wpdb->prefix.$this->table_suffix;

        // Generate a unique cache key based on the value
        // $cache_key = 'local_record_exists_' . md5( sanitize_text_field( $value ) );

        // Try to get cached result
        // $exists = wp_cache_get( $cache_key );

        // if ( $exists !== false ) {
        //     return $exists;
        // }
    
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE value = %s", // phpcs:ignore
            sanitize_text_field( $value )
        );
        $count = $wpdb->get_var( $query ); // phpcs:ignore

        // Determine if the record exists
        $exists = ( $count > 0 );

        // Cache the result
        // wp_cache_set( $cache_key, $exists );

        return $exists;
    } // End local_record_exists_by_value()


    /**
     * Get local records
     *
     * @param array $filters
     * @return void
     */
    public function get_local_records( $filters = [] ) {
        global $wpdb;
        $table_name = $wpdb->prefix.$this->table_suffix;

        // // Generate a unique cache key based on the filters
        // $cache_key = 'local_records_' . md5( serialize( $filters ) );

        // // Try to get cached results
        // $cached_results = wp_cache_get( $cache_key );

        // if ( $cached_results !== false ) {
        //     return $cached_results;
        // }
    
        // Start building the SQL queries
        $base_sql = "SELECT * FROM $table_name WHERE %d=%d";
        $count_sql = "SELECT COUNT(*) FROM $table_name WHERE %d=%d";
        
        // Prepare the filters and query parameters
        $query_params = [ 1, 1 ];
    
        // Loop through the filters to add conditions dynamically
        foreach ( $filters as $key => $value ) {
            if ( !empty( $value ) ) {
                switch ( $key ) {
                    case 'value':
                        $base_sql .= " AND value LIKE %s";
                        $count_sql .= " AND value LIKE %s";
                        $query_params[] = '%' . sanitize_text_field( $value ) . '%';
                        break;
                    case 'type':
                        $base_sql .= " AND type = %s";
                        $count_sql .= " AND type = %s";
                        $query_params[] = sanitize_text_field( $value );
                        break;
                    case 'action':
                        $base_sql .= " AND action = %s";
                        $count_sql .= " AND action = %s";
                        $query_params[] = sanitize_text_field( $value );
                        break;
                    case 'site':
                        $base_sql .= " AND site = %s";
                        $count_sql .= " AND site = %s";
                        $query_params[] = sanitize_text_field( $value );
                        break;
                    case 'user':
                        $base_sql .= " AND user = %d";
                        $count_sql .= " AND user = %d";
                        $query_params[] = intval( $value );
                        break;
                }
            }
        }

        $base_sql .= " ORDER BY value ASC";
    
        // Prepare and execute the count query
        $count_query = $wpdb->prepare( $count_sql, ...$query_params ); // phpcs:ignore
        $total_count = $wpdb->get_var( $count_query ); // phpcs:ignore
    
        // Pagination parameters
        $per_page = isset( $filters[ 'per_page' ] ) ? intval( $filters[ 'per_page' ] ) : 25;
        if ( $per_page > 0 ) {
            $paged = isset( $filters[ 'paged' ] ) ? max( 1, intval( $filters[ 'paged' ] ) ) : 1;
            $offset = ( $paged - 1 ) * $per_page;
            $paginated_sql = $base_sql . $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );
        } else {
            $paginated_sql = $base_sql;
        }
    
        // Prepare and execute the paginated query
        $query = $wpdb->prepare( $paginated_sql, ...$query_params ); // phpcs:ignore
        $results = $wpdb->get_results( $query ); // phpcs:ignore
    
        // Convert to array of arrays
        $results = array_map( function( $object ) {
            return (array) $object;
        }, $results);

        // Cache the results
        $cached_results = [
            'results' => $results,
            'count'   => $total_count
        ];
        // wp_cache_set( $cache_key, $cached_results );
    
        return $cached_results;
    } // End get_local_records()
    
    
    /**
     * Get local records
     *
     * @param array $filters
     * @return void
     */
    // public function get_local_records( $filters = [] ) {
    //     global $wpdb;
    //     $table_name = $wpdb->prefix.$this->table_suffix;
    
    //     // Start building the SQL queries
    //     $base_sql = "SELECT * FROM $table_name WHERE 1=1"; // phpcs:ignore
    //     $count_sql = "SELECT COUNT(*) FROM $table_name WHERE 1=1"; // phpcs:ignore
        
    //     // Prepare the filters and query parameters
    //     $query_params = [];
    //     $query_format = [];
    
    //     // Loop through the filters to add conditions dynamically
    //     foreach ( $filters as $key => $value ) {
    //         if ( !empty( $value ) ) {
    //             switch ( $key ) {
    //                 case 'value':
    //                     $base_sql .= " AND $key LIKE %s";
    //                     $count_sql .= " AND $key LIKE %s";
    //                     $query_params[] = '%' . sanitize_text_field($value) . '%';
    //                     $query_format[] = '%s';
    //                     break;
    //                 case 'type':
    //                 case 'action':
    //                 case 'site':
    //                     $base_sql .= " AND $key = %s";
    //                     $count_sql .= " AND $key = %s";
    //                     $query_params[] = sanitize_text_field( $value );
    //                     $query_format[] = '%s';
    //                     break;
    //                 case 'user':
    //                     $base_sql .= " AND $key = %d";
    //                     $count_sql .= " AND $key = %d";
    //                     $query_params[] = intval( $value );
    //                     $query_format[] = '%d';
    //                     break;
    //             }
    //         }
    //     }

    //     // Prepare and execute the count query
    //     $count_query = $wpdb->prepare( $count_sql, ...$query_params );
    //     $total_count = $wpdb->get_var( $count_query );

    //     // Pagination parameters
    //     $paged = isset( $filters[ 'paged' ] ) ? max( 1, intval( $filters[ 'paged' ] ) ) : 1;
    //     $per_page = isset( $filters[ 'per_page' ] ) ? intval( $filters[ 'per_page' ] ) : 25;

    //     // Calculate offset
    //     $offset = ( $paged - 1 ) * $per_page;

    //     // Add LIMIT and OFFSET to the SQL query
    //     $paginated_sql = $base_sql . $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );

    //     // Prepare and execute the paginated query
    //     $query = $wpdb->prepare( $paginated_sql, ...$query_params );
    //     $results = $wpdb->get_results( $query );

    //     // Convert to array of arrays
    //     $results = array_map( function( $object ) {
    //         return (array) $object;
    //     }, $results);

    //     return [
    //         'results' => $results,
    //         'count'   => $total_count
    //     ];
    // } // End get_local_records()


    /**
     * Get local site choices
     *
     * @return array
     */
    public function get_local_site_choices() {
        global $wpdb;
        $table_name = $wpdb->prefix.$this->table_suffix;

        // Try to get cached results
        // $cache_key = 'local_site_choices';
        // $sites = wp_cache_get( $cache_key );

        // Or else fetch
        // if ( $sites === false ) {
            $query = "
                SELECT DISTINCT site 
                FROM {$table_name} 
                WHERE site IS NOT NULL AND site != ''
            ";
    
            $results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore
        
            $sites = [ home_url() ];
        
            $sites = array_merge( $sites, array_column( $results, 'site' ) );

            // Cache the results
            // wp_cache_set( $cache_key, $sites );
        // }
    
        return $sites;
    } // End get_local_site_choices()    


    /**
     * Determine the type from the value
     *
     * @param string $value
     * @return string
     */
    public function determine_type( $value ) {
        $value = trim( $value );
        if ( filter_var( $value, FILTER_VALIDATE_EMAIL ) ) {
            return 'email';
        }
        if ( preg_match( '/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $value ) ) {
            return 'domain';
        }
        return 'keyword';
    } // End determine_type()


    /**
     * Register the routes
     *
     * @return void
     */
    public function register_api_routes() {
		$namespace = $this->api_namespace.$this->api_version;
		
        // Retrieve records
        register_rest_route( $namespace, '/list/', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'api_get' ],
            'permission_callback' => function ( WP_REST_Request $request ) {
                $api_key = $request->get_header( 'x-api-key' ); // Retrieve API key from header
                $api_data = get_option( $this->api_key_option ); // Retrieve stored API key
                if ( !empty ( $api_data ) && isset( $api_data[ 'key' ] ) ) { // Check if provided API key matches stored key
                    return $api_key === $api_data[ 'key' ];
                }
                return false;
            },
        ] );

        // Add a record
        register_rest_route( $namespace, '/add/', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'api_add' ],
            'permission_callback' => function ( WP_REST_Request $request ) {
                $api_key = $request->get_header( 'x-api-key' ); // Retrieve API key from header
                $api_data = get_option( $this->api_key_option ); // Retrieve stored API key
                if ( !empty ( $api_data ) && isset( $api_data[ 'key' ] ) ) { // Check if provided API key matches stored key
                    return $api_key === $api_data[ 'key' ];
                }
                return false;
            },
            'args' => [
                'value' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return !empty( $param );
                    }
                ],
                'type' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return in_array( $param, [ 'email', 'domain', 'keyword' ] );
                    }
                ],
                'action' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return in_array( $param, [ 'deny', 'allow' ] );
                    }
                ],
                'user' => [
                    'required' => false,
                    'validate_callback' => function( $param, $request, $key ) {
                        return is_numeric( $param );
                    }
                ],
                'site' => [
                    'required' => false,
                ],
            ],
        ] );

        // Remove a record
        register_rest_route( $namespace, '/remove/', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'api_remove' ],
            'permission_callback' => function ( WP_REST_Request $request ) {
                $api_key = $request->get_header( 'x-api-key' ); // Retrieve API key from header
                $api_data = get_option( $this->api_key_option ); // Retrieve stored API key
                if ( !empty ( $api_data ) && isset( $api_data[ 'key' ] ) ) { // Check if provided API key matches stored key
                    return $api_key === $api_data[ 'key' ];
                }
                return false;
            },
            'args' => [
                'value' => [
                    'required' => true,
                    'validate_callback' => function( $param, $request, $key ) {
                        return !empty( $param );
                    }
                ],
            ],
        ] );

        // Retrieve sites
        register_rest_route( $namespace, '/sites/', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'api_site_choices' ],
            'permission_callback' => function ( WP_REST_Request $request ) {
                $api_key = $request->get_header( 'x-api-key' ); // Retrieve API key from header
                $api_data = get_option( $this->api_key_option ); // Retrieve stored API key
                if ( !empty ( $api_data ) && isset( $api_data[ 'key' ] ) ) { // Check if provided API key matches stored key
                    return $api_key === $api_data[ 'key' ];
                }
                return false;
            },
        ] );
	} // End register_api_routes()


    /**
     * Get a remote list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_get( WP_REST_Request $request ) {
        $filters = [
            'value'  => $request->get_param( 'value' ),
            'type'   => $request->get_param( 'type' ),
            'action' => $request->get_param( 'action' ),
            'user'   => $request->get_param( 'user' ),
            'site'   => $request->get_param( 'site' ),
            'paged'  => $request->get_param( 'paged' ),
            'per_page'  => $request->get_param( 'per_page' ),
        ];
        $results = $this->get_local_records( $filters );
        return new WP_REST_Response( $results, 200 );
    } // End api_get()
    

    /**
     * Add to the remote list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_add( WP_REST_Request $request ) {
        $value = sanitize_text_field( $request->get_param( 'value' ) );
        $type = sanitize_text_field( $request->get_param( 'type' ) );
        $action = sanitize_text_field( $request->get_param( 'action' ) );
        $user = $request->get_param( 'user' );
        $site = sanitize_text_field($request->get_param( 'site' ) );
        $result = $this->add_or_update_local_record( $value, $type, $action, $user, $site );
        return new WP_REST_Response( $result, $result[ 'result' ] === 'success' ? 200 : 400 );
    } // End api_add()
    

    /**
     * Remove from the remote list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_remove( WP_REST_Request $request ) {
        $value = sanitize_text_field( $request->get_param( 'value' ) );
        $result = $this->remove_local_record( $value );
        return new WP_REST_Response( $result, $result[ 'result' ] === 'success' ? 200 : 400 );
    } // End api_remove()


    /**
     * Get a remote list
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function api_site_choices( WP_REST_Request $request ) {
        $site_choices = $this->get_local_site_choices();
        return new WP_REST_Response( $site_choices, 200 );
    } // End api_site_choices()


    /**
     * Generate an API key
     *
     * @return void
     */
    public function generate_api_key() {
        $api_key = wp_generate_password( 32, false, false );
        $api_key_data = [
            'key'       => $api_key,
            'timestamp' => time()
        ];
        update_option( $this->api_key_option, $api_key_data );
        return $api_key_data;
    } // End generate_api_key()


    /**
     * Generate API key from ajax
     *
     * @return void
     */
    public function ajax_generate_api_key() {
        // Verify nonce
        if ( isset( $_REQUEST[ 'nonce' ] ) && !wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( 'No naughty business please.' );
        }
    
        $api_key_data = $this->generate_api_key();
        $result[ 'type' ] = 'success';
        $result[ 'apiKey' ] = $api_key_data[ 'key' ];
        $result[ 'apiMsg' ] = __( 'New API Key: ', 'gf-tools' );
        
        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_generate_api_key()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'forms_page_gf_settings' ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_settings', 'enqueued' ) ) {
            wp_localize_script( 'gfadvtools_settings', 'gfat_spam', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( $this->nonce )
            ] );
        }
    } // End enqueue_scripts()


    /**
     * Validate that we are the client
     *
     * @return boolean
     */
    public function is_client() {
        if ( isset( $this->plugin_settings[ 'spam_filtering' ] ) && sanitize_key( $this->plugin_settings[ 'spam_filtering' ] ) == 'client' ) {
            return true;
        }
        return false;
    } // End is_client()


    /**
     * Get the host url and api
     *
     * @return array|false
     */
    private function get_host_and_api() {
        $host_site_url = isset( $this->plugin_settings[ 'spam_list_url' ] ) ? sanitize_text_field( $this->plugin_settings[ 'spam_list_url' ] ) : '';
        $api_key = isset( $this->plugin_settings[ 'api_spam_key' ] ) ? sanitize_text_field( $this->plugin_settings[ 'api_spam_key' ] ) : '';

        if ( !$host_site_url || !$api_key ) {
            return false;
        }

        if ( substr( $host_site_url, -1 ) !== '/' ) {
            $host_site_url .= '/';
        }

        return [
            'url' => $host_site_url,
            'key' => $api_key
        ];
    } // End get_host_and_api()


    /**
     * Get remote records
     *
     * @param array $filters
     * @return array
     */
    public function get_remote_records( $filters = [] ) {
        if ( !$this->is_client() ) {
            return false;
        }
        
        // Check and sanitize the remote site URL and API key
        if ( !$host_and_api_date = $this->get_host_and_api() ) {
            return false; // Ensure both URL and API key are set
        }

        $host_site_url = $host_and_api_date[ 'url' ];
        $api_key = $host_and_api_date[ 'key' ];

        // Build the URL with query parameters for filters
        $url = add_query_arg( $filters, $host_site_url . '/wp-json/'.$this->api_namespace.$this->api_version.'/list/' );

        // Send the GET request
        $response = wp_remote_get( $url, [
            'headers' => [
                'x-api-key' => $api_key
            ]
        ] );

        // Check for errors
        if ( is_wp_error( $response ) ) {
            return [
                'result' => 'error',
                'msg'    => $response->get_error_message()
            ];
        }

        // Decode the response body
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        return $data;
    } // End get_remote_records()


    /**
     * Add or update remote record
     *
     * @param string $value
     * @param string $type
     * @param string $action
     * @param int|null $user
     * @param string|null $site
     * @return array
     */
    public function add_or_update_remote_record( $value, $type, $action, $user = null, $site = null ) {
        if ( !$this->is_client() ) {
            return false; // Ensure this is a client site
        }
    
        // Check and sanitize the remote site URL and API key
        if ( !$host_and_api_date = $this->get_host_and_api() ) {
            return false; // Ensure both URL and API key are set
        }

        $host_site_url = $host_and_api_date[ 'url' ];
        $api_key = $host_and_api_date[ 'key' ];

        $user = $user !== null ? $user : get_current_user_id();
        $site = $site !== null ? $site : home_url();
    
        // Send the POST request to add or update the record
        $response = wp_remote_post( $host_site_url . '/wp-json/'.$this->api_namespace.$this->api_version.'/add/', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-api-key'    => $api_key
            ],
            'body' => [
                'value'  => sanitize_text_field( $value ),
                'type'   => sanitize_key( $type ),
                'action' => sanitize_key( $action ),
                'user'   => $user ? absint( $user ) : null,
                'site'   => sanitize_text_field( $site ),
            ]
        ]);
    
        // Check for errors
        if ( is_wp_error( $response ) ) {
            return [
                'result' => 'error',
                'msg'    => $response->get_error_message()
            ];
        }
    
        // Decode the response body
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );
    
        return $data;
    } // End add_or_update_remote_record()


    /**
     * Remove a remote record
     *
     * @param string $value
     * @return array
     */
    public function remove_remote_record( $value ) {
        (new GF_Advanced_Tools_Helpers)->remove_qs_without_refresh( [ 'delete', '_wpnonce' ] );

        if ( !$this->is_client() ) {
            return false; // Ensure this is a client site
        }
    
        // Check and sanitize the remote site URL and API key
        if ( !$host_and_api_date = $this->get_host_and_api() ) {
            return false; // Ensure both URL and API key are set
        }

        $host_site_url = $host_and_api_date[ 'url' ];
        $api_key = $host_and_api_date[ 'key' ];
    
        // Send the POST request to remove the record
        $response = wp_remote_post( $host_site_url . 'wp-json/'.$this->api_namespace.$this->api_version.'/remove/', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'x-api-key'    => $api_key
            ],
            'body' => [
                'value' => sanitize_text_field( $value ),
            ]
        ] );
        // dpr( $response );
    
        // Check for errors
        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 403 ) {
            $message = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
            return [
                'result' => 'error',
                'msg'    => $message
            ];
        }
    
        // Decode the response body
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );
    
        return $data;
    } // End remove_remote_record()   


    /**
     * Get remote site choices
     *
     * @return array
     */
    public function get_remote_site_choices() {
        if ( !$this->is_client() ) {
            return false;
        }

        // Check and sanitize the remote site URL and API key
        if ( !$host_and_api_date = $this->get_host_and_api() ) {
            return false; // Ensure both URL and API key are set
        }

        $host_site_url = $host_and_api_date[ 'url' ];
        $api_key = $host_and_api_date[ 'key' ];

        // Send a GET request to the remote endpoint
        $response = wp_remote_get( $host_site_url . '/wp-json/'.$this->api_namespace.$this->api_version.'/sites/', [
            'headers' => [
                'x-api-key' => $api_key
            ]
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'result' => 'error',
                'msg'    => $response->get_error_message()
            ];
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        $sites = [ home_url() ];
        if ( $data ) {
            $sites = array_merge( $sites, $data );
        }
        
        return $sites;
    } // End get_remote_site_choices()
    

    /**
     * Check for api errors
     *
     * @return string|false
     */
    public function check_for_api_errors( $response, $host_site_url ) {
        $results = '<div><p>';

        $incl_btn = true;

        // Could not resolve
        if ( isset( $response[ 'result' ] ) && $response[ 'result' ] == 'error' ) {
            /* translators: %s is the host site URL that caused the error. */
            $results .= sprintf( __( 'Uh oh! Something went wrong.', 'gf-tools' ), $host_site_url ).'<br><br>'.$response[ 'msg' ];
            $incl_btn = false;

        // Not a host
        } elseif ( isset( $response[ 'data' ]->status ) && $response[ 'data' ]->status == 404 ) {
            /* translators: %s is the host site URL that was entered by the user. */
            $results .= sprintf( __( 'Uh oh! The website "<strong>%s</strong>" you entered for the host URL is not set as "Host." Please go to your host site and set them as the "Host" under "Enable Enhanced Spam Filtering."', 'gf-tools' ), $host_site_url );
            $incl_btn = false;

        // Incorrect API Key
        } elseif ( isset( $response[ 'data' ]->status ) && $response[ 'data' ]->status == 401 ) {
            $results .= __( 'Uh oh! It appears you do not have access to the host site you have set up. Please make sure that your API Key matches the one you generated on your host site.', 'gf-tools' );

        // Good to go
        } else {
            return false;
        }

        $results .= '</p>';

        if ( $incl_btn ) {
            $results .= '<br><a href="'.GFADVTOOLS_SETTINGS_URL.'#entries" class="button button-primary">'.__( 'Update settings', 'gf-tools' ).'</a>';
        }

        $results .= '</div>';
        return $results;
    } // End check_for_api_errors()

    
    /**
     * Add Spam List to Entries toolbar
     *
     * @param array $position
     * @param int $form_id
     * @return array
     */
    public function toolbar( $menu_items, $form_id ) {
        if ( isset( $_GET[ 'page' ] ) && sanitize_key( $_GET[ 'page' ] ) == 'gf_entries' ) { // phpcs:ignore
            $menu_items[ 'gfat_spam_list' ] = [
                'label'       => __( 'Spam List', 'gf-tools' ),
                'title'       => __( 'Spam List', 'gf-tools' ),
                'url'         => gfadvtools_get_plugin_page_tab( 'spam_list' ),
                'menu_class'  => 'gfat_spam_list_link',
                'capabilities'=> [ 'gravityforms_edit_forms' ],
                'priority'    => 3
            ];
        }
        return $menu_items;
    } // End toolbar()


    /**
     * Block blacklist items
     *
     * @param array $result
     * @param mixed $value
     * @param array $form
     * @param object $field
     * @return array
     */
    public function block_blacklist( $result, $value, $form, $field ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'disable_spam_protection' ] ) && $form_settings[ 'disable_spam_protection' ] == 1 ) {
            return $result;
        }

        // Disable for logged-in members
        $plugin_settings = $this->plugin_settings;
        if ( isset( $plugin_settings[ 'disable_akismet' ] ) && $plugin_settings[ 'disable_akismet' ] != '' ) {
            if ( is_user_logged_in() ) {
                $who = $this->plugin_settings[ 'disable_akismet' ];
                if ( $who == 'admins' && current_user_can( 'administrator' ) ) {
                    return $result;
                } elseif ( $who == 'everyone' ) {
                    return $result;
                }
            }
        }

        // Get spam records based on client or local context
        if ( !$this->is_client() ) {
            $spam_records = $this->get_local_records( [
                'per_page' => -1
            ] );
        } else {
            $spam_records = $this->get_remote_records( [
                'per_page' => -1
            ] );
            $host_site_url = ( $this->is_client() && isset( $plugin_settings[ 'spam_list_url' ] ) ) ? sanitize_text_field( $plugin_settings[ 'spam_list_url' ] ) : false;
            if ( $has_access = $this->check_for_api_errors( $spam_records, $host_site_url ) ) {
                error_log( 'Attempt to get remote spam records during form validation failed. Skipping validation of spam records. '.$has_access );
                return $result;
            }
        }

        if ( empty( $spam_records ) ) {
            return $result;
        }
    
        // Create arrays to store spam record values by type for quick lookup
        $email_actions = [];
        $domain_actions = [];
        $keyword_actions = [];
        foreach ( $spam_records[ 'results' ] as $record ) {
            
            $record_action = $record[ 'action' ] === 'allow';
            if ( $record_action ) {
                continue;
            }

            $record_value = $record[ 'value' ];
            switch ( $record[ 'type' ] ) {
                case 'email':
                    $email_actions[ $record_value ] = $record_action;
                    break;
                case 'domain':
                    $domain_actions[ $record_value ] = $record_action;
                    break;
                case 'keyword':
                    $keyword_actions[ $record_value ] = $record_action;
                    break;
            }
        }
    
        // If the field is of type email, text, or textarea
        if ( in_array( $field->type, [ 'email', 'text', 'textarea' ] ) ) {

            // $field_id = $field->id;
            // $field_value = isset( $form[ $field_id ] ) ? $form[ $field_id ] : $value;
            if ( empty( $value ) ) {
                return $result;
            }
    
            // Sanitize email value for email fields
            if ( $field->type === 'email' ) {
                if ( is_array( $value ) ) {
                    $value = $value[0];
                }
                $is_email = filter_var( $value, FILTER_VALIDATE_EMAIL );
                if ( $is_email ) {
                    $domain = trim( substr( strrchr( $value, "@" ), 1 ) );
                } else {
                    $domain = $value;
                }
                
                // Check email and domain actions
                if ( $is_email && isset( $email_actions[ $value ] ) && !$email_actions[ $value ] ) {
                    $result[ 'is_valid' ] = false;
                    $result[ 'message' ] = "Email '{$value}' has been blacklisted.";
                    return $result;

                } elseif ( is_string( $domain ) && !empty( $domain ) && isset( $domain_actions[ $domain ] ) && !$domain_actions[ $domain ] ) {
                    $result[ 'is_valid' ] = false;
                    $result[ 'message' ] = "Domain '{$domain}' has been blacklisted.";
                    return $result;
                }

            } else {
                
                // Check text and textarea fields
                foreach ( $keyword_actions as $keyword => $allowed ) {
                    if ( preg_match( '/\b' . preg_quote( $keyword, '/' ) . '\b/i', $value ) && !$allowed ) {
                        $result[ 'is_valid' ] = false;
                        $result[ 'message' ] = "Keyword '{$keyword}' is not allowed.";
                        return $result;
                    }
                }

                foreach ( $domain_actions as $domain => $allowed ) {
                    if ( stripos( $value, $domain ) !== false && !$allowed ) {
                        $result[ 'is_valid' ] = false;
                        $result[ 'message' ] = "Domain '{$domain}' is not allowed.";
                        return $result;
                    }
                }

                foreach ( $email_actions as $email => $allowed ) {
                    if ( stripos( $value, $email ) !== false && !$allowed ) {
                        $result[ 'is_valid' ] = false;
                        $result[ 'message' ] = "Email '{$email}' is not allowed.";
                        return $result;
                    }
                }
            }
        }

        // $result[ 'is_valid' ] = false;

        return $result;
    } // End block_blacklist()


    /**
     * Filter the spam
     *
     * @param boolean $is_spam
     * @param array $form
     * @param array $entry
     * @return boolean
     */
    public function filter_spam( $is_spam, $form, $entry ) {
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'disable_spam_protection' ] ) && $form_settings[ 'disable_spam_protection' ] == 1 ) {
            return false;
        }

        // Disable for logged-in members
        $plugin_settings = $this->plugin_settings;
        if ( isset( $plugin_settings[ 'disable_akismet' ] ) && $plugin_settings[ 'disable_akismet' ] != '' ) {
            if ( $is_spam && is_user_logged_in() ) {
                $who = $this->plugin_settings[ 'disable_akismet' ];
                if ( $who == 'admins' && current_user_can( 'administrator' ) ) {
                    GFCommon::log_debug( __METHOD__ . '(): Entry marked as not spam for admin.' );
                    return false;
                } elseif ( $who == 'everyone' ) {
                    GFCommon::log_debug( __METHOD__ . '(): Entry marked as not spam for logged in user.' );
                    return false;
                }
            }
        }

        // Get our spam records
        if ( !$this->is_client() ) {
            $spam_records = $this->get_local_records();
        } else {
            $spam_records = $this->get_remote_records();
            $host_site_url = ( $this->is_client() && isset( $plugin_settings[ 'spam_list_url' ] ) ) ? sanitize_text_field( $plugin_settings[ 'spam_list_url' ] ) : false;
            if ( $has_access = $this->check_for_api_errors( $spam_records, $host_site_url ) ) {
                error_log( 'Attempt to get remote spam records during filtering. Skipping filtering of spam records. '.$has_access );
                return $is_spam;
            }
        }
        if ( empty( $spam_records ) ) {
            return $is_spam;
        }

        // Add extra protection by checking denied items here as well, even though they should be blocked in validation
        $double_check_denied = false;

        // Create arrays to store spam record values by type for quick lookup
        $email_actions = [];
        $domain_actions = [];
        $keyword_actions = [];
        foreach ( $spam_records[ 'results' ] as $record ) {

            $record_action = $record[ 'action' ];

            if ( !$double_check_denied &&  $record_action === 'deny' ) {
                continue; // Skip denied records if double_check_denied is false
            }

            $action =  $record_action === 'allow';
            $record_value = $record[ 'value' ];
            switch ( $record[ 'type' ] ) {
                case 'email':
                    $email_actions[ $record_value ] = $action;
                    break;
                case 'domain':
                    $domain_actions[ $record_value ] = $action;
                    break;
                case 'keyword':
                    $keyword_actions[ $record_value ] = $action;
                    break;
            }
        }

        // Initialize spam status flags
        $is_spam = false;
        $is_allowed = false;
        $log_message = '';

        // Process each field
        $field_types_to_filter = [ 'email', 'text', 'textarea' ];
        foreach ( $form[ 'fields' ] as $field ) {

            $type = $field->type;
            if ( !in_array( $type, $field_types_to_filter ) ) {
                continue;
            }

            $field_id = $field->id;
            $value = isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
            if ( empty( $value ) ) {
                continue;
            }

            // Sanitize field value
            if ( $type === 'email' ) {
                if ( is_array( $value ) ) {
                    $value = $value[0];
                }
                $is_email = filter_var( $value, FILTER_VALIDATE_EMAIL );
                if ( $is_email ) {
                    $domain = trim( substr( strrchr( $value, "@" ), 1 ) );
                } else {
                    $domain = $value;
                }
                
                // Check email and domain actions
                if ( $is_email && isset( $email_actions[ $value ] ) ) {
                    $is_spam = !$email_actions[ $value ];
                    $log_message = "Email '$value' matched with action '" . ( $email_actions[ $value ] ? 'allow' : 'deny' ) . "'.";
                    break;
                } elseif ( isset( $domain_actions[ $domain ] ) ) {
                    $is_spam = !$domain_actions[ $domain ];
                    $log_message = "Domain '$domain' matched with action '" . ( $domain_actions[ $domain ] ? 'allow' : 'deny' ) . "'.";
                    break;
                } else {
                    $is_allowed = true;
                }

            } else {

                // Check text and textarea fields
                foreach ( $keyword_actions as $keyword => $allowed ) {
                    if ( preg_match( '/\b' . preg_quote( $keyword, '/' ) . '\b/i', $value ) ) {
                        $is_spam = !$allowed;
                        $log_message = "Keyword '$keyword' found in $type field with action '" . ( $allowed ? 'allow' : 'deny' ) . "'.";
                        break 2;
                    }
                }

                foreach ( $domain_actions as $domain => $allowed ) {
                    if ( stripos( $value, $domain ) !== false ) {
                        $is_spam = !$allowed;
                        $log_message = "Domain '$domain' found in $type field with action '" . ( $allowed ? 'allow' : 'deny' ) . "'.";
                        break 2;
                    }
                }

                foreach ( $email_actions as $email => $allowed ) {
                    if ( stripos( $value, $email ) !== false ) {
                        $is_spam = !$allowed;
                        $log_message = "Email '$email' found in $type field with action '" . ( $allowed ? 'allow' : 'deny' ) . "'.";
                        break 2;
                    }
                }
            }
        }

        // Determine final spam status
        if ( $is_allowed ) {
            $is_spam = false;
        }

        if ( $log_message ) {
            // dpr( $log_message );
            GFCommon::log_debug( __METHOD__ . '(): Entry checked against spam records. '.$log_message );
        }
        
        return $is_spam;
    } // End filter_spam()
    

    /**
     * Prevent spammers from submitting links in textarea fields
     *
     * @param array $result
     * @param mixed $value
     * @param array $form
     * @param object $field
     * @return array
     */
    public function block_links( $result, $value, $form, $field ) {
        $field_types_to_filter = [ 'text', 'textarea' ];
        if ( in_array( $field->type, $field_types_to_filter ) ) {
            $nourl_pattern = '(http|https)';
            if ( preg_match( $nourl_pattern, $value ) ) {
                $result[ 'is_valid' ] = false;
                $result[ 'message' ]  = 'Message can not contain website addresses.';
            }
        }
        return $result;
    } // End block_links()

}