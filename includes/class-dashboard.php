<?php
/**
 * Dashboard widgets
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Dashboard {

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
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_dashboard_nonce';


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings = false, $form_id = false ) {

        // Update the plugin settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
        $this->form_settings = isset( $form_settings ) ? $form_settings : []; 
        $this->form_id = isset( $form_id ) ? $form_id : false;

        // Ajax
        add_action( 'wp_ajax_get_all_spam_entry_ids', [ $this, 'ajax_get_all_spam_entry_ids' ] );
        add_action( 'wp_ajax_nopriv_get_all_spam_entry_ids', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );
        add_action( 'wp_ajax_delete_spam_entry', [ $this, 'ajax_delete_spam_entry' ] );
        add_action( 'wp_ajax_nopriv_delete_spam_entry', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );

        // JQuery
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End __construct()


    /**
     * Get the current tab
     *
     * @return string
     */
    public function get_current_tab() {
        if ( isset( $_GET[ 'tab' ] ) && sanitize_key( $_GET[ 'tab' ] ) != '' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return sanitize_key( $_GET[ 'tab' ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        return 'dashboard';
    } // End get_current_tab()


    /**
     * User Entries — Quickly search for entries in all forms by user
     *
     * @return void
     */
    public function global_search() {
        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'No forms found.', 'gf-tools' );
            return;
        }

        // Current tab
        $current_tab = $this->get_current_tab();

        // Verify nonce
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // What are we searching for
        if ( $nonce_verified && isset( $_GET[ 'search' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ) != '' ) {
            $search = sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) );
            $searching = true;
        } else {
            $search = '';
            $searching = false;
        }

        // Add instructions
        echo '<h2>'.esc_html__( 'Search Entries from All Forms:', 'gf-tools' ).'</h2>';

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-search"><?php echo esc_html__( 'Enter Keyword(s)', 'gf-tools' ); ?>:</label>
                <input type="text" name="search" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search" value="<?php echo esc_attr( $search ); ?>">
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
        // Stop if we are not searching
        if ( !$searching ) {
            return;
        }

        // Verify nonce
        if ( !$nonce_verified ) {
            die( esc_html__( 'Nonce could not be verified.', 'gf-tools' ) );
        }

        // Define list table columns
        $columns = [
            'date'        => __( 'Date', 'gf-tools' ),
            'result'      => __( 'Result', 'gf-tools' ),
            'field_label' => __( 'Field Label', 'gf-tools' ),
            'field_id'    => __( 'Field ID', 'gf-tools' ),
            'form_title'  => __( 'Form Title', 'gf-tools' ),
            'user'        => __( 'Created By', 'gf-tools' ),
            'entry_id'    => __( 'Entry ID', 'gf-tools' )
        ];

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( wp_unslash( $_GET[ 'paged' ] ) ) ) : 1;
        $per_page = isset( $_GET[ 'per_page' ] ) ? intval( wp_unslash( $_GET[ 'per_page' ] ) ) : get_option( 'gfadvtools_per_page', 25 );
        $offset = ( $paged - 1 ) * $per_page;

        global $wpdb;

        // Define search queries
        $search_keywords = esc_sql( '%' . $search . '%' );

        // Cache key for entries
        $entries_cache_key = 'gf_search_entries_' . md5( $search . '_' . $paged . '_' . $per_page );
        $entries = wp_cache_get( $entries_cache_key );

        if ( false === $entries ) {
            // Query to fetch results for the current page
            $sql_query = $wpdb->prepare( "
                SELECT em.*, e.date_created, e.form_id, e.created_by 
                FROM {$wpdb->prefix}gf_entry_meta em
                LEFT JOIN {$wpdb->prefix}gf_entry e 
                ON em.entry_id = e.id 
                WHERE em.meta_value LIKE %s
                ORDER BY e.date_created DESC
                LIMIT %d OFFSET %d
            ", $search_keywords, $per_page, $offset );

            // Fetch results
            $entries = $wpdb->get_results( $sql_query, ARRAY_A ); // phpcs:ignore

            // Cache the results
            wp_cache_set( $entries_cache_key, $entries );
        }

        // Cache key for total rows
        $count_cache_key = 'gf_search_total_rows_' . md5( $search );
        $total_rows = wp_cache_get( $count_cache_key );

        if ( false === $total_rows ) {

            // Query to count total number of matching entries
            $count_query = $wpdb->prepare( "
                SELECT COUNT(*)
                FROM {$wpdb->prefix}gf_entry_meta em
                LEFT JOIN {$wpdb->prefix}gf_entry e 
                ON em.entry_id = e.id 
                WHERE em.meta_value LIKE %s
            ", $search_keywords );

            $total_rows = $wpdb->get_var( $count_query ); // phpcs:ignore

            // Cache the total rows
            wp_cache_set( $count_cache_key, $total_rows );
        }

        // Collect data
        $data = [];
        foreach ( $entries as $entry ) {
            $form_id = absint( $entry[ 'form_id' ] );
            $entry_id = absint( $entry[ 'entry_id' ] );
            $date = sanitize_text_field( $entry[ 'date_created' ] );

            $user_id = absint( $entry[ 'created_by' ] );
            $user = get_userdata( $user_id );
            $display_name = $user ? $user->display_name : $user_id;

            $form_title = '';
            $field_id = $entry[ 'meta_key' ];
            $is_field = false;
            $field_label = $field_id;
           
            $result = sanitize_text_field( $entry[ 'meta_value' ] );

            // Find form and field label
            foreach ( $forms as $form ) {
                if ( $form[ 'id' ] == $form_id ) {
                    $form_title = $form[ 'title' ];

                    foreach ( $form[ 'fields' ] as $field ) {
                        if ( is_numeric( $field_id ) ) {
                            $field_id_to_check = (int) $field_id;
                        } else {
                            $field_id_to_check = $field_id;
                        }

                        if ( $field->id == $field_id_to_check ) {
                            $field_label = $field->label;
                            $is_field = true;
                            break;
                        }
                    }
                    break;
                }
            }

            if ( !$is_field ) {
                $field_id = __( 'Entry Meta', 'gf-tools' );
            }

            $entry_link = add_query_arg( [
                'page' => 'gf_entries',
                'view' => 'entry',
                'id'   => $form_id,
                'lid'  => $entry_id
            ], admin_url( 'admin.php' ) );

            $data[] = [
                'link'        => $entry_link,
                'date'        => $date,
                'result'      => $result,
                'field_label' => $field_label,
                'field_id'    => $field_id,
                'form_title'  => $form_title,
                'user'        => $display_name,
                'entry_id'    => $entry_id,
            ];
        }

        // Show the table
        $qs = [
            'search'  => $search,
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_rows );
    } // End global_search()


    /**
     * User Entries — Quickly search for entries in all forms by user
     *
     * @return void
     */
    public function user_entries() {
        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'No forms found.', 'gf-tools' );
            return;
        }

        // Current tab
        $current_tab = $this->get_current_tab();

        // Verify nonce
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // What are we searching for
        if ( $nonce_verified && isset( $_GET[ 'user' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'user' ] ) ) != '' ) {
            $s = sanitize_text_field( wp_unslash( $_GET[ 'user' ] ) );
            $searching = true;
        } else {
            $s = get_current_user_id();
            $searching = false;
        }

        // No dice text
        $no_dice = __( 'User cannot be found.', 'gf-tools' );

        // Get the user from the search
        if ( filter_var( $s, FILTER_VALIDATE_EMAIL ) ) {
            $user_email = sanitize_email( $s );
            if ( $user = get_user_by( 'email', $user_email ) ) {
                $user_id = $user->id;
            } else {
                echo esc_html( $no_dice );
                return;
            }
        } elseif ( is_numeric( $s ) ) {
            if ( $user = get_user_by( 'id', absint( $s ) ) ) {
                $user_id = $s;
                $user_email = $user->user_email;
            } else {
                echo esc_html( $no_dice );
                return;
            }
        } else { 
            echo esc_html( $no_dice );
            return;
        }

        // Add instructions
        echo '<h2>'.esc_html__( 'Search for User Entries:', 'gf-tools' ).'</h2>';

        // Selected form
        $selected_form_id = $nonce_verified && isset( $_GET[ 'form_id' ] ) ? intval( $_GET[ 'form_id' ] ) : 0;

        // Sort the forms
        usort( $forms, function( $a, $b ) {
            return strcasecmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-id-or-email"><?php echo esc_html__( 'User ID or Email', 'gf-tools' ); ?>:</label>
                <input type="text" name="user" id="gfat-<?php echo esc_attr( $current_tab ); ?>-id-or-email" value="<?php echo esc_attr( $user_id ); ?>">
                <select name="form_id" id="gfat-forms-filter">
                    <option value="0"><?php esc_html_e( 'All Forms', 'gf-tools' ); ?></option>
                    <?php
                    foreach ( $forms as $form ) {
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            esc_attr( $form[ 'id' ] ),
                            esc_html( selected( $selected_form_id, $form[ 'id' ], false ) ),
                            esc_html( $form[ 'title' ] )
                        );
                    }
                    ?>
                </select>
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
        // Stop if we are not searching
        if ( !$searching ) {
            return;
        }

        // Verify nonce
        if ( !$nonce_verified ) {
            die( 'Nonce could not be verified.' );
        }

        // Helpers
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Define list table columns
        $columns = [
            'date'       => __( 'Date', 'gf-tools' ),
            'entry_id'   => __( 'Entry ID', 'gf-tools' ),
            'form_title' => __( 'Form Title', 'gf-tools' ),
            'form_id'    => __( 'Form ID', 'gf-tools' ),
            'source'     => __( 'Source Page', 'gf-tools' )
        ];

        // Collect data
        $data = [];

        // Count for num col
        $count = 0;

        // Iter the forms
        foreach ( $forms as $form ) {
            $form_id = intval( $form[ 'id' ] );
            $form_title = sanitize_text_field( $form[ 'title' ] );

            // Only display the one we have selected
            if ( $selected_form_id && $selected_form_id !== $form_id ) {
                continue;
            }

            // Get the entry ids
            $entries = $HELPERS->get_entries_for_user( $form_id, $user_id );
            if ( empty( $entries ) ) {
                continue;
            }

            // List them
            foreach ( $entries as $entry ) {
                
                $count++;

                $entry_id = intval( $entry[ 'id' ] );
                
                $entry_link = add_query_arg( [
                    'page' => 'gf_entries',
                    'view' => 'entry',
                    'id'   => $form_id,
                    'lid'  => $entry_id
                ], admin_url( 'admin.php' ) );

                $source_url = filter_var( $entry[ 'source_url' ], FILTER_SANITIZE_URL );
                $source_title = $source_url;
                if ( $post_id = url_to_postid( $source_url ) ) {
                    $title = get_the_title( $post_id );
                    if ( $title ) {
                        $source_title = sanitize_text_field( $title );
                    }
                }
                $source_link = '<a href="'.$source_url.'" target="_blank">'.$source_title.'</a>';
                
                $data[] = [
                    'link'       => $entry_link,
                    'date'       => sanitize_text_field( $entry[ 'date_created' ] ),
                    'entry_id'   => $entry_id,
                    'form_title' => $form_title,
                    'form_id'    => $form_id,
                    'source'     => $source_link
                ];
            }
        }

        // Sort and cut
        usort( $data, function( $a, $b ) {
            return strtotime( $b[ 'date' ] ) - strtotime( $a[ 'date' ] );
        } );

        $count_entries = count( $data );

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
        
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        $offset = ( $paged - 1 ) * $per_page;
        $data = array_slice( $data, $offset, $per_page );

        // Get the user's name
        $display_name = $user->display_name;

        // Show count
        /* translators: %1$s is the count of entries; %2$s is either 'entry' or 'entries' based on the count; %3$s is the display name. */
        $formatted_text = sprintf( __( 'Found %1$s %2$s for %3$s', 'gf-tools' ).' ('.$user_email.')',
            esc_attr( $count_entries ),
            ( $count_entries == 1 ) ? __( 'entry', 'gf-tools' ) : __( 'entries', 'gf-tools' ),
            esc_html( $display_name )
        );
        echo '<br><h4>'.esc_html( $formatted_text ).'</h4>';

        // Show the table
        $qs = [
            'user'    => $user_id,
            'form_id' => $selected_form_id
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $count_entries );
    } // End user_entries()


    /**
     * Jump to an entry
     *
     * @return void
     */
    public function jump_to_entry() {
        // What are we searching for
        if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce ) && isset( $_GET[ 'entry_id' ] ) && absint( $_GET[ 'entry_id' ] ) > 0 ) {
            $entry_id = absint( $_GET[ 'entry_id' ] );
            $entry = GFAPI::get_entry( $entry_id );
            if ( $entry ) {
                $form_id = $entry[ 'form_id' ];

                update_option( 'gfat_last_entry_id', $entry_id );

                $url = add_query_arg( [
                    'page' => 'gf_entries',
                    'view' => 'entry',
                    'id'   => $form_id,
                    'lid'  => $entry_id
                ], admin_url( 'admin.php' ) );
                wp_safe_redirect( $url );
                exit();
            }
        }

        // Current tab
        $current_tab = $this->get_current_tab();

        // Get the last entry we jumped to
        $last_entry_id = get_option( 'gfat_last_entry_id', '' );

        // Add instructions
        echo '<h2>'.esc_html__( 'Jump Straight to an Entry by ID:', 'gf-tools' ).'</h2>';

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-entry_id"><?php echo esc_html__( 'Enter Entry ID', 'gf-tools' ); ?>:</label>
                <input type="number" name="entry_id" id="gfat-<?php echo esc_attr( $current_tab ); ?>-entry_id" value="<?php echo esc_attr( $last_entry_id ); ?>">
                <input type="submit" value="<?php echo esc_html__( 'Go', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
    } // End jump_to_entry()


    /**
     * Entries by Date — See number of entries for each form, filter by date
     *
     * @return void
     */
    public function entries_by_date() {
        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'No forms found.', 'gf-tools' );
            return;
        }

        $total_count = count( $forms );

        // Current tab
        $current_tab = $this->get_current_tab();

        // Helpers
        $HELPERS = new GF_Advanced_Tools_Helpers();
        $HELPERS->remove_qs_without_refresh( 'test' );

        // Verify nonce
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // Dates
        if ( isset( $_GET[ 'start_date' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'start_date' ] ) ) != '' && $nonce_verified ) {
            $start = sanitize_text_field( wp_unslash( $_GET[ 'start_date' ] ) );
        } else {
            $start = gmdate( 'Y-m-d', strtotime( get_user_option( 'user_registered', 1 ) ) );
        }

        if ( isset( $_GET[ 'end_date' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'end_date' ] ) ) != '' && $nonce_verified ) {
            $end = sanitize_text_field( wp_unslash( $_GET[ 'end_date' ] ) );
        } else {
            $end = gmdate( 'Y-m-d' );
        }

        // Add instructions if no dates
        echo '<p>'.esc_html__( 'Use the form below to see how many entries there were during the timeframe you specify.', 'gf-tools' ).'</p>';

        // Title
        echo '<br><h2>'.esc_html__( 'Select a Quarter or Specify a Date Frame', 'gf-tools' ).'</h2>';

        // Selected form
        $selected_form_id = isset( $_GET[ 'form_id' ] ) ? intval( $_GET[ 'form_id' ] ) : 0;

        // Sort the forms
        usort( $forms, function( $a, $b ) {
            return strcasecmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // Quarter links
        $query_strings = [
            '_wpnonce' => wp_create_nonce( $this->nonce )
        ];
        if ( $selected_form_id ) {
            $query_strings[ 'form_id' ] = $selected_form_id;
        }
        $base_url = add_query_arg( $query_strings, gfadvtools_get_plugin_page_tab( $current_tab ) );
        $ff = isset( $this->plugin_settings[ 'federal_fiscal' ] ) && $this->plugin_settings[ 'federal_fiscal' ] == 1;

        echo wp_kses_post( $HELPERS->quarter_links( $base_url, $ff ) );

        // Date filter
        ?>
        <br>
        <div class="gfat-date-filter">
            <form id="gfat-date-filter-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <div class="date-fields">
                    <div class="start">
                        <label for="start_date"><?php echo esc_html__( 'Start Date', 'gf-tools' ); ?>:</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo esc_html( $start ); ?>">
                    </div>
                    <div class="end">
                        <label for="end_date"><?php echo esc_html__( 'End Date', 'gf-tools' ); ?>:</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo esc_html( $end ); ?>">
                    </div>
                    <select name="form_id" id="gfat-forms-filter">
                    <option value="0"><?php esc_html_e( 'All Forms', 'gf-tools' ); ?></option>
                    <?php
                    foreach ( $forms as $form ) {
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            esc_attr( $form[ 'id' ] ),
                            esc_html( selected( $selected_form_id, $form[ 'id' ], false ) ),
                            esc_html( $form[ 'title' ] )
                        );
                    }
                    ?>
                </select>
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat_date_filter_search" class="button button-primary"/>
                </div>
            </form>
        </div>
        <br>
        <?php
        // Define list table columns
        $columns = [
            'form_title' => __( 'Form Title', 'gf-tools' ),
            'form_id'    => __( 'Form ID', 'gf-tools' ),
            'count'      => __( 'Number of Entries', 'gf-tools' ),
            // 'source'     => __( 'Source Page', 'gf-tools' )
        ];

        // Collect data
        $data = [];

        // Count for num col
        $count_entries = 0;

        // Iter the forms
        foreach ( $forms as $form ) {
            $form_id = intval( $form[ 'id' ] );
            $form_title = sanitize_text_field( $form[ 'title' ] );

            // Only display the one we have selected
            if ( $selected_form_id && $selected_form_id !== $form_id ) {
                continue;
            }

            // Get the entries filtered by date
            $search_criteria = [
                'status'     => 'active',
                'start_date' => $start,
                'end_date'   => $end,
            ];
            $count = GFAPI::count_entries( $form_id, $search_criteria );
            $count_entries += $count;

            // Link to entries
            $entries_link = add_query_arg( [
                'page' => 'gf_entries',
                'id'   => $form_id
            ], admin_url( 'admin.php' ) );

            // TODO: Get the source link if a page is associated with it
            // $source_link = '';

            // The data
            $data[] = [
                'link'       => $entries_link,
                'form_title' => $form_title,
                'form_id'    => $form_id,
                'count'      => $count,
                // 'source'     => $source_link
            ];
        }

        // Sort and cut
        usort( $data, function( $a, $b ) {
            return $b[ 'count' ] - $a[ 'count' ];
        } );

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
        
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        $offset = ( $paged - 1 ) * $per_page;
        $data = array_slice( $data, $offset, $per_page );

        // Show the table
        $qs = [
            'start_date' => $start,
            'end_date'   => $end,
            'form_id'    => $selected_form_id
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_count );
    } // End entries_by_date()


    /**
     * Recent Entries — Quick glance of most recent entries across all forms
     *
     * @return void
     */
    public function recent() {
        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'No forms found.', 'gf-tools' );
            return;
        }

        // Current tab
        $current_tab = $this->get_current_tab();

        // Verify nonce, but allow to pull current dates if not verified
        $nonce_verified = isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_key( $_GET[ '_wpnonce' ] ), $this->nonce );

        // Count
        if ( $nonce_verified && isset( $_GET[ 'count' ] ) && absint( $_GET[ 'count' ] ) != '' ) {
            $total_count = absint( $_GET[ 'count' ] );
            update_option( 'gfat_recent_entry_count', $total_count );
        } elseif ( $recent_entry_count = get_option( 'gfat_recent_entry_count' ) ) {
            $total_count = $recent_entry_count;
        } else {
            $total_count = 10;
        }

        // Selected form
        $selected_form_id = isset( $_GET[ 'form_id' ] ) ? intval( $_GET[ 'form_id' ] ) : 0;

        // Sort the forms
        usort( $forms, function( $a, $b ) {
            return strcasecmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-count"><?php echo esc_html__( 'Specify Total Number of Entries', 'gf-tools' ); ?>:</label>
                <input type="text" name="count" id="gfat-<?php echo esc_attr( $current_tab ); ?>-count" value="<?php echo esc_attr( $total_count ); ?>">
                <select name="form_id" id="gfat-forms-filter">
                    <option value="0"><?php esc_html_e( 'All Forms', 'gf-tools' ); ?></option>
                    <?php
                    foreach ( $forms as $form ) {
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            esc_attr( $form[ 'id' ] ),
                            esc_html( selected( $selected_form_id, $form[ 'id' ], false ) ),
                            esc_html( $form[ 'title' ] )
                        );
                    }
                    ?>
                </select>
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
        // Define list table columns
        $columns = [
            'date'       => __( 'Date', 'gf-tools' ),
            'form_title' => __( 'Form Title', 'gf-tools' ),
            'form_id'    => __( 'Form ID', 'gf-tools' ),
            'user'       => __( 'User', 'gf-tools' ),
            'entry_id'   => __( 'Entry ID', 'gf-tools' ),
            'source'     => __( 'Source Page', 'gf-tools' )
        ];

        // Collect data
        $data = [];

        // Iter the forms
        foreach ( $forms as $form ) {
            $form_id = intval( $form[ 'id' ] );
            $form_title = sanitize_text_field( $form[ 'title' ] );

            // Only display the one we have selected
            if ( $selected_form_id && $selected_form_id !== $form_id ) {
                continue;
            }

            // Get the entries filtered by date
            $search_criteria = [
                'status' => 'active',
            ];
            $count_entries = GFAPI::count_entries( $form_id, $search_criteria );
            $paging = [ 'offset' => 0, 'page_size' => $count_entries ];
            $entries = GFAPI::get_entries( $form_id, $search_criteria, [], $paging );

            // List them
            foreach ( $entries as $entry ) {

                $entry_id = intval( $entry[ 'id' ] );
                
                $entry_link = add_query_arg( [
                    'page' => 'gf_entries',
                    'view' => 'entry',
                    'id'   => $form_id,
                    'lid'  => $entry_id
                ], admin_url( 'admin.php' ) );

                $user_id = intval( $entry[ 'created_by' ] );
                if ( $user_id && $user = get_userdata( $user_id ) ) {
                    $user_display = $user->display_name;
                } else {
                    $user_display = __( 'Guest', 'gf-tools' );
                }

                $source_url = filter_var( $entry[ 'source_url' ], FILTER_SANITIZE_URL );
                $source_title = $source_url;
                if ( $post_id = url_to_postid( $source_url ) ) {
                    $title = get_the_title( $post_id );
                    if ( $title ) {
                        $source_title = sanitize_text_field( $title );
                    }
                }
                $source_link = '<a href="'.$source_url.'" target="_blank">'.$source_title.'</a>';
                
                $data[] = [
                    'link'       => $entry_link,
                    'date'       => sanitize_text_field( $entry[ 'date_created' ] ),
                    'user'       => $user_display,
                    'form_title' => $form_title,
                    'form_id'    => $form_id,
                    'entry_id'   => $entry_id,
                    'source'     => $source_link,
                ];
            }
        }

        // Sort and cut
        usort( $data, function( $a, $b ) {
            return strtotime( $b[ 'date' ] ) - strtotime( $a[ 'date' ] );
        } );
        $data = array_slice( $data, 0, $total_count );

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
        
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        $offset = ( $paged - 1 ) * $per_page;
        $data = array_slice( $data, $offset, $per_page );

        // Show the table
        $qs = [
            'count'   => $total_count,
            'form_id' => $selected_form_id
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_count );
    } // End recent()

    
    /**
     * Spam Entries — Displays spam counts on each form
     *
     * @return void
     */
    public function spam_entries() {
        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'No forms found.', 'gf-tools' );
            return;
        }

        // Description
        echo '<p>'.esc_html__( 'Managing spam entries can be quite a task if you have a ton of forms. This is a quick way to see how many spam entries you have across the board.', 'gf-tools' ).'</p><br><br>';

        // Current tab
        $current_tab = $this->get_current_tab();

        // Define list table columns
        $columns = [
            'form_title' => __( 'Form Title', 'gf-tools' ),
            'form_id'    => __( 'Form ID', 'gf-tools' ),
            'count'      => __( 'Number of Spam Entries', 'gf-tools' ),
            // 'source'     => __( 'Source Page', 'gf-tools' ),
            'actions'    => __( 'Actions', 'gf-tools' )
        ];

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        // Per page
        if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce ) && isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        // Calculate total forms count
        $total_forms = count( $forms );

        // Sort
        usort( $forms, function( $a, $b ) {
            return strcmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // Calculate total pages
        $total_pages = ceil( $total_forms / $per_page );

        // Ensure the current page is not out of range
        if ( $paged > $total_pages ) {
            $paged = $total_pages;
        }

        // Calculate the offset
        $offset = ( $paged - 1 ) * $per_page;

        // Slice the forms array
        $forms_to_display = array_slice( $forms, $offset, $per_page );

        // Collect data
        $data = [];
    
        // Cycle
        foreach ( $forms_to_display as $form ) {
            $form_id = intval( $form[ 'id' ] );
            $form_title = sanitize_text_field( $form[ 'title' ] );

            // Get the entries
            $search_criteria = [
                'status' => 'spam',
            ];
            $count = GFAPI::count_entries( $form_id, $search_criteria );

            // Link to entries
            $entries_link = add_query_arg( [
                'page'   => 'gf_entries',
                'id'     => $form_id,
                'filter' => 'spam'
            ], admin_url( 'admin.php' ) );

            // TODO: Get the source link if a page is associated with it
            // $source_link = '';

            // Actions
            if ( $count > 0 ) {
                $actions = '<div class="form-actions" data-form-id="'.$form_id.'">
                    <a href="#" class="button delete-all-spam">Delete All Spam</a>
                    <div class="progress-container" style="display: none;">
                        <div class="progress-bar button">
                            <div class="progress" style="width: 0%;"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                </div>';
            } else {
                $actions = '';
            }

            // The data
            $data[] = [
                'link'       => $entries_link,
                'form_title' => $form_title,
                'form_id'    => $form_id,
                'count'      => $count,
                // 'source'     => $source_link,
                'actions'    => $actions
            ];
        }
        
        // Show the table
        $this->wp_list_table( $columns, $data, $current_tab, [], $total_forms );
    } // End spam_entries()


    /**
     * Spam — Manage Lists
     *
     * @return void
     */
    public function spam_list() {
        $location = isset( $this->plugin_settings[ 'spam_filtering' ] ) ? sanitize_key( $this->plugin_settings[ 'spam_filtering' ] ) : 'local';
        $is_client = ( $location == 'client' );
        $host_site_url = ( $is_client && isset( $this->plugin_settings[ 'spam_list_url' ] ) ) ? sanitize_text_field( $this->plugin_settings[ 'spam_list_url' ] ) : false;
        $api_spam_key = ( $is_client && isset( $this->plugin_settings[ 'api_spam_key' ] ) ) ? sanitize_text_field( $this->plugin_settings[ 'api_spam_key' ] ) : false;
        $home_url = home_url();
        $spam_list_created = get_option( 'gfat_spam_list_table_created' );

        if ( $is_client && ( !$host_site_url || !$api_spam_key ) ) {
            echo '<em>This site is set up as a Client, but we are missing an API or home site URL. Please enter them in the Plugin Settings.</em>';
            return;
        }

        // Helpers
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Imported notice
        if ( isset( $_GET[ 'imported' ] ) && absint( $_GET[ 'imported' ] ) > 0 ) { // phpcs:ignore 
            $imported = absint( $_GET[ 'imported' ] ); // phpcs:ignore 
            echo '<span class="gfat-dashboard-notice success">'.esc_html( 
                /* translators: %1$s is the number of records imported; %2$s is either 'record' or 'records' depending on the count. */
                sprintf( __( 'You have successfully imported %1$s %2$s!', 'gf-tools' ),
                    $imported,
                    $imported === 1 ? __( 'record', 'gf-tools' ) : __( 'records', 'gf-tools' )
                ) ).'</span>';
            $HELPERS->remove_qs_without_refresh( 'imported' );
        }

        // Get started
        $SPAM = new GF_Advanced_Tools_Spam();

        // Nonce
        $nonce = wp_create_nonce( $this->nonce );
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // Local
        if ( !$is_client && $spam_list_created && isset( $_REQUEST[ '_wpnonce' ] ) && 
             $nonce_verified && 
             isset( $_GET[ 'delete_db' ] ) && absint( wp_unslash( $_GET[ 'delete_db' ] ) ) == 1 ) {
            
            if ( $SPAM->delete_spam_list_table() ) {

                echo '<span class="gfat-dashboard-notice success">'.esc_html__( 'Your spam list has been deleted.', 'gf-tools' ).'</span>';
                $spam_list_created = false;

            } else {

                $delete_db_url = add_query_arg( [
                    'delete_db' => true,
                    '_wpnonce'  => $nonce
                ], gfadvtools_get_plugin_page_tab( 'spam_list' ) );
                echo '<div><p>'.esc_html__( 'Uh-oh! Something went wrong when trying to delete the spam list.', 'gf-tools' ).'</p><br><a href="'.esc_url( $delete_db_url ).'" class="button button-primary">'.esc_html__( 'Try again!', 'gf-tools' ).'</a></div>';
                return;
            }
        }

        // Updates
        if ( $nonce_verified && isset( $_POST[ 'update' ] ) ) {
            $update = filter_var_array( wp_unslash( $_POST[ 'update' ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ); // phpcs:ignore 

            $update_value = sanitize_text_field( $update[ 'value' ] );
            $get_type = $SPAM->determine_type( $update_value );
            $update_action = sanitize_key( $update[ 'action' ] );

            if ( !$is_client ) {
                $add = $SPAM->add_or_update_local_record(
                    $update_value ,
                    $get_type, 
                    $update_action
                );
            } else {
                $add = $SPAM->add_or_update_remote_record(
                    $update_value ,
                    $get_type, 
                    $update_action
                );
                if ( $has_access = $SPAM->check_for_api_errors( $add, $host_site_url ) ) {
                    echo wp_kses_post( $has_access );
                    return;
                }
            }

            if ( $add[ 'result' ] == 'success' ) {
                /* translators: %s is the message indicating the action taken on the spam record. */
                $add_notice_msg = sprintf( __( 'Your spam record has been %s successfully. It may take a moment to update in your list due to caching.', 'gf-tools' ), $add[ 'msg' ] );
            } else {
                /* translators: %s is the error message indicating the failure reason for adding or updating. */
                $add_notice_msg = sprintf( __( 'Could not add or update: %s.', 'gf-tools' ), ucfirst( $add[ 'msg' ] ) );
            }
            echo '<span class="gfat-dashboard-notice '.esc_attr( $add[ 'result' ] ).'">'.esc_html( $add_notice_msg ).'</span>';
        }

        // Delete single
        if ( $nonce_verified && isset( $_GET[ 'delete' ] ) ) {
            $get_value = sanitize_text_field( wp_unslash( $_GET[ 'delete' ] ) );
            $HELPERS->remove_qs_without_refresh( [ 'delete', '_wpnonce' ] );

            if ( !$is_client ) {
                $remove = $SPAM->remove_local_record( $get_value );
            } else {
                $remove = $SPAM->remove_remote_record( $get_value );
                if ( $has_access = $SPAM->check_for_api_errors( $remove, $host_site_url ) ) {
                    echo wp_kses_post( $has_access );
                    return;
                }
            }

            if ( $remove[ 'result' ] == 'success' ) {
                /* translators: %s is the name of the item that has been removed. */
                $add_notice_msg = sprintf( __( '"%s" has been removed successfully. It may take a moment to update in your list due to caching. You can verify that it has been deleted by searching for it.', 'gf-tools' ), $get_value );
            } else {
                /* translators: %s is the error message indicating the failure reason for deletion. */
                $add_notice_msg = sprintf( __( 'Could not delete: %s.', 'gf-tools' ), ucfirst( $remove[ 'msg' ] ) );
            }
            echo '<span class="gfat-dashboard-notice '.esc_attr( $remove[ 'result' ] ).'">'.esc_html( $add_notice_msg ).'</span>';
        }

        // Delete selected
        if ( !$is_client && $nonce_verified && isset( $_POST[ 'delete_selected' ] ) ) {
            $delete_selected = filter_var_array( wp_unslash( $_POST[ 'delete_selected' ] ), FILTER_SANITIZE_FULL_SPECIAL_CHARS ); // phpcs:ignore 
            $HELPERS->remove_qs_without_refresh( [ 'delete_selected', '_wpnonce' ] );

            if ( $SPAM->remove_bulk_local_records( $delete_selected ) ) {
                $add_notice_msg = __( 'Records have been removed successfully.', 'gf-tools' );
                $remove_result = 'success';
            } else {
                $add_notice_msg = __( 'There was an issue removing the records. Please try again.', 'gf-tools' );
                $remove_result = 'error';
            }
            echo '<span class="gfat-dashboard-notice '.esc_attr( $remove_result ).'">'.esc_html( $add_notice_msg ).'</span>';
        }

        // More vars
        $spam_records = [];
        $current_tab = $this->get_current_tab();

        // Filters
        $value = isset( $_GET[ 'value' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'value' ] ) ) : '';
        $type = isset( $_GET[ 'type' ] ) ? sanitize_key( wp_unslash( $_GET[ 'type' ] ) ) : '';
        $action = isset( $_GET[ 'action' ] ) ? sanitize_key( wp_unslash( $_GET[ 'action' ] ) ) : '';
        $user = isset( $_GET[ 'user' ] ) ? absint( wp_unslash( $_GET[ 'user' ] ) ) : '';
        $site = isset( $_GET[ 'site' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'site' ] ) ) : '';

        // Get the local records
        $args = [];
        if ( $value != '' ) {
            $args[ 'value' ] = $value;
        }
        if ( $type != '' ) {
            $args[ 'type' ] = $type;
        }
        if ( $action != '' ) {
            $args[ 'action' ] = $action;
        }
        if ( $user != '' ) {
            $args[ 'user' ] = $user;
        }
        if ( $site != '' ) {
            if ( $site == home_url() && $location != 'client' ) {
                $args[ 'site' ] = '';
            } else {
                $args[ 'site' ] = $site;
            }
        }

        // Set up pagination
        $args[ 'paged' ] = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
        
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) ) {
            $args[ 'per_page' ] = intval( $_GET[ 'per_page' ] );
        } else {
            $args[ 'per_page' ] = get_option( 'gfadvtools_per_page', 25 );
        }

        // Local
        if ( !$is_client ) {
            
            // Check if we have a table created
            if ( !$spam_list_created ) {

                $create_db_url = add_query_arg( [
                    'page'      => 'gf-tools',
                    'tab'       => 'spam_list',
                    'create_db' => true,
                    '_wpnonce'  => $nonce
                ], GFADVTOOLS_DASHBOARD_URL );

                // Create the database
                if ( $nonce_verified && isset( $_GET[ 'create_db' ] ) && absint( $_GET[ 'create_db' ] ) == 1 ) {

                    if ( $SPAM->create_spam_list_table() ) {

                        echo '<span class="gfat-dashboard-notice success">'.esc_html__( 'Fantastic! We are all set and ready to go. You can now start adding emails, domains, and keywords to your spam list.', 'gf-tools' ).'</span>';

                    } else {

                        echo '<div><p>'.esc_html__( 'Uh-oh! Something went wrong when trying to create the database.', 'gf-tools' ).'</p><br><a href="'.esc_url( $create_db_url ).'" class="button button-primary">'.esc_html__( 'Try again!', 'gf-tools' ).'</a></div>';
                        return;
                    }
                    
                // Show button to create one
                } else {
                    
                    echo '<div><p>'.esc_html__( 'No spam list database found.', 'gf-tools' ).'</p><br><a href="'.esc_url( $create_db_url ).'" class="button button-primary">'.esc_html__( 'Create one now!', 'gf-tools' ).'</a></div>';
                    return;
                }
                
            }
            $fetch = $SPAM->get_local_records( $args );

        // Remote
        } else {
            $fetch = $SPAM->get_remote_records( $args );
            if ( $has_access = $SPAM->check_for_api_errors( $fetch, $host_site_url ) ) {
                echo wp_kses_post( $has_access );
                return;
            }
        }

        $spam_records = $fetch[ 'results' ];
        $total_count = is_object( $fetch[ 'count' ] ) ? $fetch[ 'count' ]->scalar : $fetch[ 'count' ];

        ?>
        <h2><?php echo esc_html__( 'Search for Spam Records', 'gf-tools' ); ?></h2>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <div>
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-value"><?php echo esc_html__( 'Value', 'gf-tools' ); ?>:</label>
                    <input type="text" name="value" id="gfat-<?php echo esc_attr( $current_tab ); ?>-value" value="<?php echo esc_attr( $value ); ?>">
                </div>
                <div>
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-action"><?php echo esc_html__( 'Action', 'gf-tools' ); ?>:</label>
                    <select name="action" id="gfat-<?php echo esc_attr( $current_tab ); ?>-action">
                        <option value=""><?php echo esc_html__( 'All Actions', 'gf-tools' ); ?></option>
                        <option value="allow"<?php echo esc_attr( $action == 'allow' ? ' selected' : '' ); ?>><?php echo esc_html__( 'Allow', 'gf-tools' ); ?></option>
                        <option value="deny"<?php echo esc_attr( $action == 'deny' ? ' selected' : '' ); ?>><?php echo esc_html__( 'Deny', 'gf-tools' ); ?></option>
                    </select>
                </div>
                <div>
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-type"><?php echo esc_html__( 'Type', 'gf-tools' ); ?>:</label>
                    <select name="type" id="gfat-<?php echo esc_attr( $current_tab ); ?>-type">
                        <option value=""><?php echo esc_html__( 'All Types', 'gf-tools' ); ?></option>    
                        <option value="email"<?php echo esc_attr( $type == 'email' ? ' selected' : '' ); ?>><?php echo esc_html__( 'Emails', 'gf-tools' ); ?></option>
                        <option value="domain"<?php echo esc_attr( $type == 'domain' ? ' selected' : '' ); ?>><?php echo esc_html__( 'Domains', 'gf-tools' ); ?></option>
                        <option value="keyword"<?php echo esc_attr( $type == 'keyword' ? ' selected' : '' ); ?>><?php echo esc_html__( 'Keywords', 'gf-tools' ); ?></option>
                    </select>
                </div>
                <?php if ( $location != 'local' ) {
                    if ( $location == 'client' ) {
                        $sites = $SPAM->get_remote_site_choices();
                    } else {
                        $sites = $SPAM->get_local_site_choices();
                    }
                    
                    $final_sites = [];
                    foreach ( $sites as $site ) {
                        $parsed_url = wp_parse_url( $site );
                        $final_sites[] = [ 
                            'full'  => $site,
                            'short' => isset( $parsed_url[ 'host' ] ) ? $parsed_url[ 'host' ] : $site
                        ];
                    }
                    usort( $final_sites, function( $a, $b ) {
                        return strcmp( $a[ 'short' ], $b[ 'short' ] );
                    } );
                    ?>
                    <div>
                        <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-site"><?php echo esc_html__( 'Website', 'gf-tools' ); ?>:</label>
                        <select name="site" id="gfat-<?php echo esc_attr( $current_tab ); ?>-site">
                            <option value=""><?php echo esc_html__( 'All Sites', 'gf-tools' ); ?></option>   
                            
                            <?php
                            foreach ( $final_sites as $s ) {
                                ?>
                                <option value="<?php echo esc_url( $s[ 'full' ] ); ?>"<?php echo esc_attr( $s == $site ? ' selected' : '' ); ?>><?php echo esc_attr( $s[ 'short' ] ); ?></option>
                                <?php
                            }

                            ?>
                        </select>
                    </div>
                <?php } ?>
                <input type="submit" value="<?php echo esc_html__( 'Search/Filter', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
        // TODO: Add "Export" link in future version
        ?>
        <h2><?php echo esc_html__( 'Add or Update a Spam Record', 'gf-tools' ); ?></h2>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-add-form" method="POST">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce' ); ?>
                <div>
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-value"><?php echo esc_html__( 'Value', 'gf-tools' ); ?>:</label>
                    <input type="text" name="update[value]" id="gfat-<?php echo esc_attr( $current_tab ); ?>-value" value="">
                </div>
                <div>
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-action"><?php echo esc_html__( 'Action', 'gf-tools' ); ?>:</label>
                    <select name="update[action]" id="gfat-<?php echo esc_attr( $current_tab ); ?>-action">
                        <option value="allow"><?php echo esc_html__( 'Allow', 'gf-tools' ); ?></option>
                        <option value="deny"><?php echo esc_html__( 'Deny', 'gf-tools' ); ?></option>
                    </select>
                </div>
                <input type="submit" value="<?php echo esc_html__( 'Submit', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
                <?php if ( !$is_client ) { ?>
                    <a class="import-export-link" href="<?php echo esc_url( add_query_arg( [ 'page' => 'gf_export', 'subview' => 'import_spam_list' ], admin_url( 'admin.php' ) ) ); ?>">Import</a>
                <?php } ?>
            </form>
        </div>
        <br>
        <?php

        // Define list table columns
        $columns = [
            'value'  => __( 'Value', 'gf-tools' ),
            'action' => __( 'Action', 'gf-tools' ),
            'type'   => __( 'Type', 'gf-tools' ),
            'time'   => __( 'Date Added', 'gf-tools' ),
            'user'   => __( 'Added By', 'gf-tools' ),
            'delete' => __( 'Delete', 'gf-tools' )
        ];
        if ( $location != 'local' ) {
            $columns = array_slice( $columns, 0, -1, true ) + 
               [ 'site' => __( 'Added From', 'gf-tools' ) ] + 
               array_slice( $columns, -1, 1, true );
        }

        // Collect data
        $data = [];
    
        // Cycle
        foreach ( $spam_records as $record ) {

            $action = sanitize_text_field( $record[ 'action' ] );

            $date = $HELPERS->convert_date_to_wp_timezone( sanitize_text_field( $record[ 'date' ] ), 'F j, Y g:ia T' );
            
            if ( $location != 'local' ) {
                $site = sanitize_text_field( $record[ 'site' ] );
                if ( $site === '' ) {
                    if ( !$is_client ) {
                        $site = $home_url;
                    } elseif ( $host_site_url ) {
                        $site = $host_site_url;
                    }
                } else {
                    $site = sanitize_url( $site );
                }
            }

            $user_id = absint( $record[ 'user' ] );
            if ( $location == 'local' || $site == $home_url ) {
                $user = get_userdata( $user_id );
                $display_user = $user->display_name;
            } else {
                $display_user = 'User ID '.$user_id;
            }

            $delete_url = add_query_arg( [
                'page'      => 'gf-tools',
                'tab'       => 'spam_list',
                'delete'    => $record[ 'value' ],
                '_wpnonce'  => $nonce
            ], GFADVTOOLS_DASHBOARD_URL );

            $delete = '<div class="delete" data-spam-id="'.absint( $record[ 'id' ] ).'">
                <a href="'.$delete_url.'" class="button delete-spam-record">Delete</a>
            </div>';

            // The data
            $data[] = [
                'value'   => '<code><strong>'.sanitize_text_field( $record[ 'value' ] ).'</strong></code>',
                'action'  => '<span class="gfat-spam '.$action.'">'.ucwords( $action ).'</span>',
                'type'    => ucwords( sanitize_text_field( $record[ 'type' ] ) ),
                'time'    => $date,
                'user'    => $display_user,
                'delete'  => $delete
            ];
            if ( $location != 'local' ) {
            
                // Remove protocol from site URL
                $parsed_url = wp_parse_url( $site );
                $site_without_protocol = isset( $parsed_url[ 'host' ] ) ? $parsed_url[ 'host' ] : $site;
            
                $data[ count( $data ) - 1 ][ 'site' ] = $site_without_protocol;
            }
        }

        // Delete selected button
        if ( !$is_client ) {
            ?>
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-delete-selected-form" method="POST">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce' ); ?>
                <input type="submit" value="<?php echo esc_html__( 'Delete Selected Records', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-delete-selected-button" class="button button-secondary"/>
            <?php
            // </form> is added inside $this->wp_list_table

            $checkbox_name = 'delete_selected';
            $checkbox_value = 'value';
            $end_form = true;
        } else {
            $checkbox_name = false;
            $checkbox_value = false;
            $end_form = false;
        }

        // Show the table
        $qs = $args;
        unset( $qs[ 'paged' ] );
        unset( $qs[ 'per_page' ] );

        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_count, $checkbox_name, $checkbox_value, $end_form );
    } // End spam_list()


    /**
     * Front-End Reports
     *
     * @return void
     */
    public function reports() {
        // Info box
        echo '<div class="info-box">
            '.esc_html__( 'You can build a report for the front-end of your website that shows a table of entries along with an optional search feature and export button. This is a great way to allow a management team to see form entries without needing access to the back-end. Create a new report, then come back here to grab the shortcode associated with it. Place the shortcode on the page that you want to display it on.', 'gf-tools' ).'
        </div>';

        // Get forms first
        $forms = GFAPI::get_forms();
        if ( empty( $forms ) ) {
            echo esc_html__( 'You have no forms. Please add a new form in order to report on it.', 'gf-tools' );
            return;
        }
        
        // Current tab
        $current_tab = $this->get_current_tab();

        // Verify nonce, but allow to pull current dates if not verified
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // Delete
        if ( $nonce_verified && isset( $_GET[ 'delete' ] ) ) {
            $delete_post_id = absint( $_GET[ 'delete' ] );

            if ( wp_delete_post( $delete_post_id ) ) {
                $add_notice_msg = __( 'Report has been deleted.', 'gf-tools' );
                $delete_class = 'success';
            } else {
                $add_notice_msg = __( 'Report could not be deleted. Please try again.', 'gf-tools' );
                $delete_class = 'error';
            }
            echo '<span class="gfat-dashboard-notice '.esc_attr( $delete_class ).'">'.esc_html( $add_notice_msg ).'</span>';
            (new GF_Advanced_Tools_Helpers)->remove_qs_without_refresh( [ 'delete', '_wpnonce' ] );
        }

        // Search
        if ( isset( $_GET[ 'search' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) ) != '' && $nonce_verified ) {
            $search = sanitize_text_field( wp_unslash( $_GET[ 'search' ] ) );
        } else {
            $search = '';
        }

        // Selected form
        $selected_form_id = isset( $_GET[ 'form_id' ] ) ? intval( $_GET[ 'form_id' ] ) : 0;

        // Sort the forms
        usort( $forms, function( $a, $b ) {
            return strcasecmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-search"><?php echo esc_html__( 'Search Reports', 'gf-tools' ); ?>:</label>
                <input type="text" name="search" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search" value="<?php echo esc_attr( $search ); ?>">
                <select name="form_id" id="gfat-forms-filter">
                    <option value="0"><?php esc_html_e( 'All Forms', 'gf-tools' ); ?></option>
                    <?php
                    foreach ( $forms as $form ) {
                        echo sprintf(
                            '<option value="%d"%s>%s</option>',
                            esc_attr( $form[ 'id' ] ),
                            esc_html( selected( $selected_form_id, $form[ 'id' ], false ) ),
                            esc_html( $form[ 'title' ] )
                        );
                    }
                    ?>
                </select>
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <?php
        // Add new button
        $add_new_url = add_query_arg( [
            'post_type' => 'gfat-reports'
        ], admin_url( 'post-new.php' ) );
        echo '<a href="'.esc_url( $add_new_url ).'" class="button add-new-report">'.esc_html__( 'Create New Report', 'gf-tools' ).'</a>';

        // Define list table columns
        $columns = [
            'title'      => __( 'Title', 'gf-tools' ),
            'date'       => __( 'Date', 'gf-tools' ),
            'form'       => __( 'Form', 'gf-tools' ),
            'ID'         => __( 'Report ID', 'gf-tools' ),
            'shortcode'  => __( 'Shortcode', 'gf-tools' ),
            'actions'    => __( 'Actions', 'gf-tools' )
        ];

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1;
        
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        // Collect data
        $data = [];

        // Fetch reports
        $args = [
            'post_type'      => 'gfat-reports',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
        ];

        if ( $search ) {
            $args[ 's' ] = $search;
        }

        if ( $selected_form_id ) {
            $args[ 'meta_query' ] = [ // phpcs:ignore
                [
                    'key'   => 'form_id',
                    'value' => $selected_form_id,
                ]
            ];
        }
        $query = new WP_Query( $args );

        $total_count = $query->found_posts;

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();

                $post_id = get_the_ID();

                $form_id = get_post_meta( get_the_ID(), 'form_id', true );
                $form = GFAPI::get_form( $form_id );
                $form_url = add_query_arg( [
                    'page'    => 'gf_edit_forms',
                    'view'    => 'settings',
                    'subview' => 'gf-tools',
                    'id'      => $form_id
                ], admin_url( 'admin.php' ) );
                $form_link = '<a href="'.esc_url( $form_url ).'">'.esc_attr( $form[ 'title' ] ).'</a>';

                $code = htmlspecialchars( '[gfat_report id="'.$post_id.'"]', ENT_QUOTES, 'UTF-8' );
                $shortcode = '<code>'.esc_html( $code ).'</code>';

                $edit_url = add_query_arg( [
                    'post'   => $post_id,
                    'action' => 'edit'
                ], admin_url( 'post.php' ) );

                $nonce = wp_create_nonce( $this->nonce );
                $delete_url = add_query_arg( [
                    'page'      => 'gf-tools',
                    'tab'       => 'reports',
                    'delete'    => $post_id,
                    '_wpnonce'  => $nonce
                ], GFADVTOOLS_DASHBOARD_URL );

                $actions = '<div class="delete">
                    <a href="'.$edit_url.'" class="button edit-report">Edit</a>
                    <a href="'.$delete_url.'" class="button delete-report">Delete</a>
                </div>';

                $data[] = [
                    'link'      => $edit_url,
                    'title'     => '<strong>'.get_the_title().'</strong>',
                    'date'      => get_the_date(),
                    'form'      => $form_link,
                    'ID'        => $post_id,
                    'shortcode' => $shortcode,
                    'actions'   => $actions
                ];

            }

            wp_reset_postdata();
        }

        // Sort and cut
        usort( $data, function( $a, $b ) {
            return strcmp( $a[ 'title' ], $b[ 'title' ] );
        } );

        // Show the table
        $qs = [
            'search'  => $search,
            'form_id' => $selected_form_id
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_count );
    } // End reports()

    
    /**
     * The shortcodes data that will populate the page and the sidebar
     *
     * @return array
     */
    public function shortcodes_data() {
        $required_text = __( 'Required', 'gf-tools' );
        return [
            [
                'id'     => 'gravityform',
                'title'  => __( 'Gravity Forms Form Shortcode', 'gf-tools' ), 
                'code'   => '[gravityform id="1" title="false" description="false" ajax="true" tabindex="49" field_values="check=First Choice,Second Choice" theme="orbital"]',
                'params' => [
                    'id'           => __( 'The numeric ID of the form that is to be embedded.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                    'title'        => __( 'Whether or not to display the form title. Defaults to <code>true</code>.', 'gf-tools' ),
                    'description'  => __( 'Whether or not to display the form description. Defaults to <code>true</code>.', 'gf-tools' ),
                    'ajax'         => __( 'Specify whether or not to use AJAX to submit the form.', 'gf-tools' ),
                    'tabindex'     => __( 'Specify the starting tab index for the fields of this form.', 'gf-tools' ),
                    'field_values' => __( 'Specify the default field values.', 'gf-tools' ),
                    'theme'        => __( 'Specify the theme to be applied to the form by providing the theme slug. Possible values are orbital for the Orbital Theme and gravity for the Gravity Forms 2.5 Theme.', 'gf-tools' ),
                ],
                'refs'   => [
                    'https://docs.gravityforms.com/shortcodes/',
                    'https://docs.gravityforms.com/using-dynamic-population/'
                ],
            ],
            [
                'id'     => 'gravityforms',
                'title'  => __( 'Gravity Forms Conditional Shortcode', 'gf-tools' ), 
                'code'   => '[gravityforms action="conditional" merge_tag="{Number:1}" condition="greater_than" value="3"]\n'.__( 'Content you would like to conditionally display.', 'gf-tools' ).'\n[/gravityforms]',
                'params' => [
                    'action'    => __( 'The action you would like to perform. This must be set to conditional as in the example above.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                    'merge_tag' => __( 'The form merge tag who’s value you are executing the conditional logic for. You can get the correct merge tag for the form data you would like to use using the insert merge tag drop down.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                    'condition' => __( 'The type of condition used to determine success. Available conditions are: is, isnot, greater_than, less_than, contains, starts_with, ends_with.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                    'value'     => __( 'The value that the condition must equal in order for the condition to be met and the content displayed.', 'gf-tools' ).' <em>('.$required_text.')</em>'
                ],
                'refs'   => 'https://docs.gravityforms.com/conditional-shortcode/', 
            ],
            [
                'id'     => 'gfat_remove_qs',
                'title'  => __( 'Remove Query String Parameters from URL Without Refreshing', 'gf-tools' ),
                'desc'   => __( 'An alternative method to the same option under Form Settings > Advanced Tools > Field Population, but can also be used on other pages without forms. Useful if you are passing parameters to a page, but then want to remove them from the URL so the user can\'t easily copy the URL or refresh the page to complete the form again if a parameter is required. If using on a form, add the shortcode to an HTML field at the bottom of your form or after the form on the page. Query string parameters look like this: <code>http://domain.com/?page_id=1234&user_id=1</code> (where <code>page_id</code> and <code>user_id</code> are the parameters).', 'gf-tools' ),
                'code'   => '[gfat_remove_qs params="id"]',
                'params' => [
                    'params' => __( 'The URL parameters you want removed, separated by commas.', 'gf-tools' ).' <em>('.__( 'Optional - If no params are set, it will remove all parameters.', 'gf-tools' ).')</em>',
                ],
            ],
            [
                'id'     => 'gfat_form',
                'title'  => __( 'Display Form from ID in URL', 'gf-tools' ),
                /* translators: %s is an example URL for passing the form ID. */
                'desc'   => sprintf( __( 'This will pass the form ID from the URL to the shortcode and display the form you want, allowing you to use a single page for multiple forms. Add the <code>param</code> value to your URL like so: "<code>%s</code>".', 'gf-tools' ), home_url( 'your-form-page/?form_id=1' ) ),
                'code'   => '[gfat_form param="form_id" forms="1, 2, 5 (Custom Form Title)"]',
                'params' => [
                    'param' => __( 'The URL parameter you will be using to capture the form ID. Do not use spaces.', 'gf-tools' ).' <em>('.__( 'Optional - Default:', 'gf-tools' ).' "form_id")</em>',
                    'forms' => __( 'A comma-separated list of form IDs to list if an ID is not provided in the URL. It will list them in the order that you include them. Forms will use their title by default or you can provide alternatives in parenthesis after the IDs.', 'gf-tools' ).' <em>('.__( 'Optional - Defaults to an "Oops! We could not locate your form." message.', 'gf-tools' ).')</em>',
                    '<em><a href="#gravityform">[gravityform] attributes</a></em>' => __( 'You can use any of the available attributes also used on the <code>[gravityform]</code> shortcode, such as setting <code>title="false"</code>.', 'gf-tools' ),
                ],
            ],
            [
                'id'     => 'gfat_report',
                'title'  => __( 'Display a Front-End Report for Any Form', 'gf-tools' ),
                /* translators: %s is an example URL for passing the report ID. */
                'desc'   => sprintf( __( 'Use <code>id</code> <em>or</em> add the <code>param</code> value to your URL like so: "<code>%s</code>". Using the <code>param</code> attribute will pass the report ID from the URL to the shortcode and display the report you want, allowing you to use a single page for multiple reports.', 'gf-tools' ), home_url( 'your-report-page/?report_id=1' ) ),
                'code'   => '[gfat_report id="1" param="report_id" reports="1, 2, 5 (Custom Form Title)"]',
                'params' => [
                    'id'      => __( 'The report ID.', 'gf-tools' ).' <em>('.__( 'Optional - Default to <code>param</code>', 'gf-tools' ).')</em>',
                    'param'   => __( 'The URL parameter you will be using to capture the report ID if one isn\'t provided. Do not use spaces.', 'gf-tools' ).' <em>('.__( 'Optional - Default:', 'gf-tools' ).' "report_id")</em>',
                    'reports' => __( 'A comma-separated list of report IDs to list if a <code>id</code> is not provided or if an ID is not provided in the URL. It will list them in the order that you include them. Reports will use their title by default or you can provide alternatives in parenthesis after the IDs.', 'gf-tools' ).' <em>('.__( 'Optional - Defaults to an "Oops! We could not locate your report." message.', 'gf-tools' ).')</em>',
                ],
                'refs'   => add_query_arg( 'post_type', 'gfat-reports', admin_url( 'edit.php' ) ),
            ],
            [
                'id'     => 'gfat_export_entries',
                'title'  => __( 'Export Entries on Front-End', 'gf-tools' ),
                'desc'   => __( 'Allow others with specific roles (defined in Advanced Tools plugin settings) to export form entries from the front-end. You can do a single form by ID, or to include a dropdown to pick from a selection of forms you must also enable "Add to Export Entries Shortcode" in each of the forms\' Advanced Tools settings.', 'gf-tools' ),
                'code'   => '[gfat_export_entries id="1"]',
                'params' => [
                    'id'       => __( 'The form ID.', 'gf-tools' ).' <em>('.__( 'Optional - If no <code>id</code> is provided, checks for <code>combined</code> param, otherwise displays a dropdown of forms to choose from', 'gf-tools' ).')</em>',
                    'combined' => __( 'Comma-separated list of form IDs.', 'gf-tools' ).' <em>('.__( 'Optional', 'gf-tools' ).')</em>',
                ],
                'refs'   => GFADVTOOLS_SETTINGS_URL.'#exporting',
            ],
            [
                'id'     => 'gfat_entry_submitted',
                'title'  => __( 'Content Displayed if Entry Submitted', 'gf-tools' ),
                'desc'   => __( 'Display content only if the current user has submitted an entry for a given form. Accepts HTML in the content between the opening and closing shortcode tags. You can use the <code>{date}</code> in your content to display their most recent entry\'s date.', 'gf-tools' ),
                'code'   => '[gfat_entry_submitted form_id="1" date_format="F j, Y"]You registered on {date}![/gfat_entry_submitted]',
                'params' => [
                    'form_id'     => __( 'The form ID.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                    'date_format' => __( 'Date format if you are using the <code>{date}</code> merge tag in your content.', 'gf-tools' ).' <em>('.__( 'Optional - If no format is provided, it defaults to F j, Y (see reference link below for formatting options).', 'gf-tools' ).')</em>'
                ],
                'refs'   => 'https://www.w3schools.com/php/func_date_date.asp',
            ],
            [
                'id'     => 'gfat_entry_not_submitted',
                'title'  => __( 'Content Displayed if Entry is NOT Submitted', 'gf-tools' ),
                'desc'   => __( 'Display content only if the current user has NOT submitted an entry for a given form. Accepts HTML in the content between the opening and closing shortcode tags.', 'gf-tools' ),
                'code'   => '[gfat_entry_not_submitted form_id="1"]<a href="/register/" class="button">Register now!</a>[/gfat_entry_not_submitted]',
                'params' => [
                    'form_id'     => __( 'The form ID.', 'gf-tools' ).' <em>('.$required_text.')</em>'
                ]
            ],
            [
                'id'     => 'gfat_qs_value',
                'title'  => __( 'Return Query String Value', 'gf-tools' ),
                'desc'   => sprintf( __( 'Returns a query string parameter value if found. Add the <code>param</code> to your URL like so: "<code>%s</code>". In the example the parameter is "id", and the value returned will be "999". This is especially useful to use in an HTML field when passing parameters into a link.', 'gf-tools' ), home_url( 'your-form-page/?id=999' ) ),
                'code'   => '[gfat_qs_value param="id"]',
                'params' => [
                    'param'   => __( 'The URL parameter you will be capturing. Do not use spaces.', 'gf-tools' ).' <em>('.$required_text.')</em>',
                ]
            ]
        ];
    } // End shortcodes_data()


    /**
     * Shortcodes
     *
     * @return void
     */
    public function shortcodes() {
        // Info box
        echo '<div class="info-box">
            '.esc_html__( 'WordPress shortcodes allow users to perform certain actions as well as display predefined items within WordPress pages and posts. Below are some shortcodes available from Gravity Forms as well as some that we have made for you.', 'gf-tools' ).'
        </div>';

        $data = $this->shortcodes_data();
        $this->reference_page( $data );
    } // End shortcodes()


    /**
     * Shortcodes sidebar
     *
     * @return void
     */
    public function sidebar_shortcodes() {
        $data = $this->shortcodes_data();
        $this->reference_sidebar( $data );
    } // End sidebar_shortcodes()


    /**
     * The merge tags data that will populate the page and the sidebar
     *
     * @return array
     */
    public function merge_tags_data( $user_id = false, $post_id = false, $entry = false, $form = null ) {
        // Defaults
        $sig_notice = '<em>The global signature you provide in Advanced Tools settings (see reference)</em>';
        $confirmations_sig = ( isset( $this->plugin_settings[ 'confirmations_signature' ] ) && $this->plugin_settings[ 'confirmations_signature' ] != '' ) ? wp_kses_post( $this->plugin_settings[ 'confirmations_signature' ] ) : $sig_notice;
        $notifications_sig = ( isset( $this->plugin_settings[ 'notifications_signature' ] ) && $this->plugin_settings[ 'notifications_signature' ] != '' ) ? wp_kses_post( $this->plugin_settings[ 'notifications_signature' ] ) : $sig_notice;

        $all_merge_tags = [
            [
                'label' => 'Confirmations Signature',
                'tag'   => '{confirmations_signature}',
                'returns' => $confirmations_sig,
                'ref'   => '<a href="'.GFADVTOOLS_SETTINGS_URL.'#confirmations'.'">Confirmation Settings</a>',
            ],
            [
                'label' => 'Notifications Signature',
                'tag'   => '{notifications_signature}',
                'returns' => $notifications_sig,
                'ref'   => '<a href="'.GFADVTOOLS_SETTINGS_URL.'#notifications'.'">Notification Settings</a>',
            ]
        ];

        // Custom tags
        if ( isset( $this->plugin_settings[ 'custom_merge_tags' ] ) ) {
            $my_gfat_tags = $this->plugin_settings[ 'custom_merge_tags' ];
            foreach ( $my_gfat_tags as $gfat_tag ) {
                $label = sanitize_text_field( $gfat_tag[ 'label' ] );
                $modifier = sanitize_text_field( $gfat_tag[ 'modifier' ] );
                $value = sanitize_text_field( $gfat_tag[ 'value' ] );
                if ( !$label || !$modifier ) {
                    continue;
                }
                if ( sanitize_key( $gfat_tag[ 'return_type' ] ) == 'callback' ) {
                    $returns = __( 'Callback function:', 'gf-tools' ).'<br><code>'.$value.'</code>';
                } else {
                    $returns = '<code>'.$value.'</code>';
                }
                $all_merge_tags[] = [
                    'label'   => $label,
                    'tag'     => '{gfat:'.$modifier.'}',
                    'returns' => $returns,
                    'ref'     => '<a href="'.GFADVTOOLS_SETTINGS_URL.'#merge_tags'.'">Merge Tag Settings</a>'
                ];
            }
        }

        // Stock urls
        $urls = [
            'current_page_number'       => '',
            'source_page_number'        => '',
            'quiz_score'                => '',
            'quiz_passfail'             => '',
            'quiz'                      => '',
            'square_receipt_url'        => '',
            'apc_media'                 => '',
            'admin_email'               => 'admin-email-merge-tag',
            'ip'                        => '',
            'referer'                   => '',
            'user_agent'                => '',
            'all_fields'                => 'fields-merge-tag',
            'embed_post'                => '',
            'embed_url'                 => 'embed_url',
            'entry_id'                  => '',
            'entry_url'                 => '',
            'entry'                     => '',
            'post_id'                   => 'post-id-merge-tag',
            'form_id'                   => '',
            'form_title'                => '',
            'payment_action'            => '',
            'post_edit_url'             => '',
            'pricing_fields'            => '',
            'save_email_input'          => 'save_email_input',
            'user'                      => '',
            'all_quiz_results'          => '',
            'quiz_grade'                => '',
            'quiz_percent'              => '',
            'survey_total_score'        => '',
            'score'                     => '',
            'today'                     => '',
            'date_mdy'                  => 'category/user-guides/merge-tags-getting-started',
            'date_dmy'                  => 'category/user-guides/merge-tags-getting-started'
        ];

        // GF docs base url
        $docs_base_url = 'https://docs.gravityforms.com';

        // Get only the tags
        $tags = array_column( $all_merge_tags, 'tag' );

        // Get form and form id
        if ( $entry ) {
            $form_id = $entry[ 'form_id' ];
            $form = GFAPI::get_form( $form_id );
        } elseif ( !is_null( $form ) ) {
            $form_id = $form[ 'id' ];
        } else {
            $form_id = false;
        }

        // Get user id
        if ( $entry && $entry[ 'created_by' ] > 0 ) {
            $user_id = $entry[ 'created_by' ];
        }

        // Verify nonce
        $nonce_verified = isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce );

        // Post id
        if ( !is_null( $form ) ) {
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
            if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
                $associated_page_qs = $form_settings[ 'associated_page_qs' ];
                if ( is_numeric( $associated_page_qs ) ) {
                    $post_id = absint( $associated_page_qs );
                } elseif ( $nonce_verified ) {
                    $query_string = sanitize_text_field( $associated_page_qs );
                    if ( isset( $_GET[ $query_string ] ) && absint( $_GET[ $query_string ] ) > 0 ) {
                        $post_id = absint( $_GET[ $query_string ] );
                    }
                }
            }
        }

        $HELPERS = new GF_Advanced_Tools_Helpers();

        // GF Stock
        $gf_merge_tags = GFCommon::get_merge_tags( null, 'gfat_merge_tag_dropdown', false, [], '' );
        foreach ( $gf_merge_tags as $gf_merge_tag_group ) {
            foreach ( $gf_merge_tag_group[ 'tags' ] as $gf_merge_tag ) {
                $tag = sanitize_text_field( $gf_merge_tag[ 'tag' ] );
                $stripped_tag = str_replace( [ '{', '}' ], '', $tag );
                if ( in_array( $tag, $tags ) || $tag == '{connection:[meta_key]}' ) {
                    continue;
                }

                $replace_variables = false;
                $url = '';
                foreach ( $urls as $key => $u ) {
                    if ( $HELPERS->str_starts_with( $stripped_tag, $key ) ) {
                        if ( $u == '' ) {
                            $url = $key.'-merge-tag';
                        } else {
                            $url = $u;
                        }
                        $replace_variables = true;
                        break;
                    }
                }

                // Replace the merge tag to get the value
                if ( $stripped_tag == 'entry_id' && $entry ) {
                    $returns = $entry[ 'id' ];
                } elseif ( $replace_variables && $entry ) {
                    $returns = GFCommon::replace_variables( $tag, $form, $entry );
                } else if ( ( $stripped_tag == 'post_id' || $stripped_tag == 'embed_post:ID' ) && $post_id ) {
                    $returns = $post_id;
                } else if ( $stripped_tag == 'embed_post:post_title' && $post_id ) {
                    $returns = get_the_title( $post_id );
                } else if ( ( $stripped_tag == 'post_url' || $stripped_tag == 'embed_url' ) && $post_id ) {
                    $returns = get_the_permalink( $post_id );
                } elseif ( $stripped_tag == 'form_id' && !is_null( $form ) ) {
                    $returns = $form_id;
                } else if ( $stripped_tag == 'form_title' && !is_null( $form ) ) {
                    $returns = $form[ 'title' ];
                } elseif ( $stripped_tag == 'date_mdy' ) {
                    $returns = gmdate( 'm/d/Y' );
                } elseif ( $stripped_tag == 'date_dmy' ) {
                    $returns = gmdate( 'd/m/Y' );
                } else {
                    $returns = '';
                }

                // The row data
                $all_merge_tags[] = [
                    'label'   => sanitize_text_field( $gf_merge_tag[ 'label' ] ),
                    'tag'     => $tag,
                    'returns' => $returns,
                    'ref'     => ( $url != '' ) ? '<a href="'.$docs_base_url.'/'.$url.'/'.'" target="_blank">Gravity Forms Docs</a>' : '',
                ];
            }
        }

        // Get only the tags again
        $tags = array_column( $all_merge_tags, 'tag' );

        // Add other GF tags not in dropdown
        foreach ( $urls as $stripped_tag => $u ) {
            $tag = '{'.$stripped_tag.'}';
            if ( in_array( $tag, $tags ) ) {
                continue;
            }
            if ( $u == '' ) {
                $url = $stripped_tag.'-merge-tag';
            } else {
                $url = $u;
            }
            if ( $entry ) {
                $returns = GFCommon::replace_variables( $tag, $form, $entry );
                if ( $returns == $tag ) {
                    $returns = '';
                }
            } else {
                $returns = '';
            }
            $all_merge_tags[] = [
                'label'   => ucwords( str_replace( '_', ' ', $stripped_tag ) ),
                'tag'     => $tag,
                'returns' => $returns,
                'ref'     => ( $url != '' ) ? '<a href="'.$docs_base_url.'/'.$url.'/'.'" target="_blank">Gravity Forms Docs</a>' : '',
            ];
        }

        // Get only the tags again
        $tags = array_column( $all_merge_tags, 'tag' );

        // User tags
        if ( $user_id ) {
            $user_meta_key_choices = (new GF_Advanced_Tools_Helpers)->get_available_user_meta_keys( $user_id );
            $user = get_userdata( $user_id );
            foreach ( $user_meta_key_choices as $choice ) {
                $tag = '{user:'.$choice.'}';
                if ( in_array( $tag, $tags ) ) {
                    continue;
                }
                $returns = isset( $user->$choice ) ? sanitize_text_field( $user->$choice ) : '';
                $all_merge_tags[] = [
                    'label'   => __( 'Custom User Meta', 'gf-tools' ),
                    'tag'     => $tag,
                    'returns' => $returns,
                    'ref'     => '<a href="'.$docs_base_url.'/user-merge-tag/">Gravity Forms Docs</a>'
                ];
            }
        } else {
            $all_merge_tags[] = [
                'label'   => __( 'Custom User Meta', 'gf-tools' ),
                'tag'     => '{user:[meta_key]}',
                'returns' => '<em>'.__( 'Meta value', 'gf-tools' ).'</em>',
                'ref'     => '<a href="'.$docs_base_url.'/user-merge-tag/">Gravity Forms Docs</a>'
            ];
        }

        // Form settings
        $form_settings_breadcrumbs = __( 'Forms', 'gf-tools' ).' > '.__( 'Form', 'gf-tools' ).' > '.__( 'Settings', 'gf-tools' ).' > Advanced Tools > ';
        $form_settings_url = gfadvtools_get_form_settings_url( $form_id );

        // Post tags
        if ( $post_id ) {

            // All custom meta
            $post_meta_key_choices = (new GF_Advanced_Tools_Helpers)->get_available_post_meta_keys( $post_id );
            $post = get_post( $post_id );
            foreach ( $post_meta_key_choices as $choice ) {
                $tag = '{connection:'.$choice.'}';
                if ( in_array( $tag, $tags ) ) {
                    continue;
                }

                $returns = isset( $post->$choice ) ? sanitize_text_field( $post->$choice ) : '';
                $ref = $form_id ? '<a href="'.$form_settings_url.'#field-population">Field Population Settings</a>' : $form_settings_breadcrumbs.__( 'Field Population Settings', 'gf-tools' );
                $all_merge_tags[] = [
                    'label'   => __( 'Connected Page Meta', 'gf-tools' ),
                    'tag'     => $tag,
                    'returns' => $returns,
                    'ref'     => $ref
                ];
            }
        } else {
            if ( $form_id ) {
                $form_settings_url = gfadvtools_get_form_settings_url( $form_id );
                $field_pop = '<a href="'.$form_settings_url.'#field-population">'.__( 'Field Populations', 'gf-tools' ).'</a>';
            } else {
                $field_pop = __( 'Field Populations', 'gf-tools' );
            }
            $all_merge_tags[] = [
                'label'   => __( 'Connected Page Meta', 'gf-tools' ),
                'tag'     => '{connection:[meta_key]}',
                'returns' => '<em>'.__( 'Meta value', 'gf-tools' ).'</em>',
                'ref'     => $form_settings_breadcrumbs.$field_pop
            ];
        }
    
        // Form tags
        if ( $entry || !is_null( $form ) ) {
            $fields = $form[ 'fields' ];
            $using_labels = isset( $this->plugin_settings[ 'mt_form_field_labels' ] ) && $this->plugin_settings[ 'mt_form_field_labels' ] == 1;
            foreach ( $fields as $field ) {
                $tag = $using_labels ? '{'.$field->label.':'.$field->id.'}' : '{:'.$field->id.'}';
                if ( $entry ) {
                    $returns = isset( $entry[ $field->id ] ) ? $entry[ $field->id ] : '';
                } else {
                    $returns = '<em>Entry Value</em>';
                }
                $all_merge_tags[] = [
                    'label'   => __( 'Form Field: ', 'gf-tools' ).$field->label,
                    'tag'     => $tag,
                    'returns' => $returns,
                    'ref'     => '<a href="'.$docs_base_url.'/field-merge-tags/">Gravity Forms Docs</a>'
                ];
            }
        }
        
        // Data
        return $all_merge_tags;
    } // End merge_tags_data()


    /**
     * Merge tags
     *
     * @return void
     */
    public function merge_tags() {
        $docs_base_url = 'https://docs.gravityforms.com';
        $gf_docs_url = $docs_base_url.'/category/user-guides/merge-tags-getting-started/';

        echo '<div class="info-box">
            '.wp_kses( 
                /* translators: %1$s is the HTML for the strong tag around "Gravity Forms Merge Tags"; %2$s is the URL to the documentation. */
                sprintf( __( 'Gravity Forms uses merge tags to allow you to dynamically populate submitted field values and other dynamic information in confirmations, notification emails, post content templates, and more! For additional reference and a list of all %1$s, you can reference their docs here: <a href="%2$s" target="_blank">%2$s</a>', 'gf-tools' ), 
                '<strong>Gravity Forms Merge Tags</strong>',
                esc_url( $gf_docs_url ),
            ), [ 'strong' => [], 'a' => [ 'href' => [], 'target'=> [] ] ] ).'
            <br><br>
            '.wp_kses( 
                /* translators: %s contains a note about merge tag values and their visibility. */
                sprintf( __( 'Note: %s', 'gf-tools' ), 
                '<em><strong>The values that are provided may or may not exactly match what you\'ll see where you use the merge tags!</strong> The search filters are here for you to get an idea of what kind of information you will get depending on how and where you place them. User meta will only show if you filter by User or Entry ID (if completed by a logged-in user). Post tags will only show if you filter by Connected Post ID. Entry data will only show if you filter by Entry ID. Form data will only show if you filter by Form or Entry ID. Some other values may also be blank here if we cannot pull them.</em>'
            ), [ 'strong' => [], 'em' => [] ] ).'
        </div>';

        // Verify nonce, but allow to pull current dates if not verified
        $nonce_verified = isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_key( $_GET[ '_wpnonce' ] ), $this->nonce );

        // User
        if ( isset( $_GET[ 'user' ] ) && sanitize_text_field( wp_unslash( $_GET[ 'user' ] ) ) != '' && $nonce_verified ) {
            $s_user = sanitize_text_field( wp_unslash( $_GET[ 'user' ] ) );

            // No dice text
            $no_dice = __( 'User cannot be found.', 'gf-tools' );
            
            if ( filter_var( $s_user, FILTER_VALIDATE_EMAIL ) ) {
                $user_email = sanitize_email( $s_user );
                if ( $user = get_user_by( 'email', $user_email ) ) {
                    $user_id = $user->id;
                } else {
                    echo esc_html( $no_dice );
                    return;
                }
            } elseif ( is_numeric( $s_user ) ) {
                if ( $user = get_user_by( 'id', absint( $s_user ) ) ) {
                    $user_id = $s_user;
                    $user_email = $user->user_email;
                } else {
                    echo esc_html( $no_dice );
                    return;
                }
            } else { 
                echo esc_html( $no_dice );
                return;
            }
        } else {
            $user_id = '';
            $s_user = '';
        }

        // Entry
        if ( isset( $_GET[ 'entry_id' ] ) && absint( $_GET[ 'entry_id' ] ) > 0 && $nonce_verified ) {
            $entry_id = absint( $_GET[ 'entry_id' ] );

            // No dice text
            $no_dice = __( 'Entry cannot be found.', 'gf-tools' );
            $entry = GFAPI::get_entry( $entry_id );
            if ( !$entry || is_wp_error( $entry ) ) {
                echo esc_html( $no_dice );
                return;
            }
        } else {
            $entry_id = '';
            $entry = null;
        }

        // Selected form
        if ( isset( $_GET[ 'form_id' ] ) && absint( $_GET[ 'form_id' ] ) > 0 && $nonce_verified ) {
            $selected_form_id = absint( $_GET[ 'form_id' ] );

            // No dice text
            $no_dice = __( 'Form cannot be found.', 'gf-tools' );
            $selected_form = GFAPI::get_form( $selected_form_id );
            if ( !$selected_form || is_wp_error( $selected_form ) ) {
                echo esc_html( $no_dice );
                return;
            }
        } else {
            $selected_form_id = 0;
            $selected_form = null;
        }

        // Associated page
        $post_id = '';
        if ( isset( $_GET[ 'post_id' ] ) && absint( $_GET[ 'post_id' ] ) > 0 && $nonce_verified ) {
            $post_id = absint( $_GET[ 'post_id' ] );

        } elseif ( $selected_form ) {
            $form_settings = (new GF_Advanced_Tools)->get_form_settings( $selected_form );
            if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
                $associated_page_qs = $form_settings[ 'associated_page_qs' ];
                if ( is_numeric( $associated_page_qs ) ) {
                    $post_id = absint( $associated_page_qs );
                } else {
                    $query_string = sanitize_text_field( $associated_page_qs );
                    if ( isset( $_GET[ $query_string ] ) && absint( $_GET[ $query_string ] ) > 0 ) {
                        $post_id = absint( $_GET[ $query_string ] );
                    }
                }
            }
        }

        // Validate post
        if ( $post_id ) {
            $no_dice = __( 'Page cannot be found.', 'gf-tools' );
            $post = get_post( $post_id );
            if ( !$post ) {
                echo esc_html( $no_dice );
                return;
            }
        }

        // Set up pagination
        $paged = isset( $_GET[ 'paged' ] ) ? max( 1, intval( $_GET[ 'paged' ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        
        // Per page
        if ( $nonce_verified && isset( $_GET[ 'per_page' ] ) ) {
            $per_page = intval( $_GET[ 'per_page' ] );
        } else {
            $per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        // Get the tag data before we manipulate data
        $merge_tags =  $this->merge_tags_data( $user_id, $post_id, $entry, $selected_form );
        usort( $merge_tags, function( $a, $b)  {
            return strcmp( $a[ 'tag' ], $b[ 'tag' ] );
        } );

        // Calculate total forms count
        $total_tags = count( $merge_tags );

        // Ensure the current page is not out of range
        if ( $paged > $total_tags ) {
            $paged = $total_tags;
        }

        // Calculate the offset
        $offset = ( $paged - 1 ) * $per_page;

        // Slice the forms array
        $merge_tags_to_display = array_slice( $merge_tags, $offset, $per_page );

        // Current tab
        $current_tab = $this->get_current_tab();

        // The form with results
        ?>
        <div class="gfat-<?php echo esc_attr( $current_tab ); ?> gform-settings-panel__content">
            <form id="gfat-<?php echo esc_attr( $current_tab ); ?>-form" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
                <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
                <div class="search-field">
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-id-or-email"><?php echo esc_html__( 'User ID or Email', 'gf-tools' ); ?>:</label>
                    <?php
                    if ( $entry_id ) {
                        $user_id = '';
                        $disable_user_field = ' disabled';
                        $placeholder_user_field = 'Will get user from entry';
                    } else {
                        $disable_user_field = '';
                        $placeholder_user_field = '';
                    }
                    ?>
                    <input type="text" name="user" id="gfat-<?php echo esc_attr( $current_tab ); ?>-id-or-email" value="<?php echo esc_attr( $user_id ); ?>"<?php echo esc_attr( $disable_user_field ); ?> placeholder="<?php echo esc_html( $placeholder_user_field ); ?>">
                </div>
                <div class="search-field">
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-post"><?php echo esc_html__( 'Connected Post ID', 'gf-tools' ); ?>:</label>
                    <?php
                    if ( $entry_id ) {
                        $user_id = '';
                        $disable_postid_field = ' disabled';
                        $placeholder_postid_field = 'Will get ID from entry';
                    } elseif ( $selected_form_id ) {
                        $user_id = '';
                        $disable_postid_field = ' disabled';
                        $placeholder_postid_field = 'Will get ID from form';
                    } else {
                        $disable_postid_field = '';
                        $placeholder_postid_field = '';
                    }
                    ?>
                    <input type="number" name="post_id" id="gfat-<?php echo esc_attr( $current_tab ); ?>-post" value="<?php echo esc_attr( $post_id ); ?>"<?php echo esc_attr( $disable_postid_field ); ?> placeholder="<?php echo esc_html( $placeholder_postid_field ); ?>">
                </div>
                <div class="search-field">
                    <label for="gfat-<?php echo esc_attr( $current_tab ); ?>-entry"><?php echo esc_html__( 'Entry ID', 'gf-tools' ); ?>:</label>
                    <input type="number" name="entry_id" id="gfat-<?php echo esc_attr( $current_tab ); ?>-entry" value="<?php echo esc_attr( $entry_id ); ?>">
                </div>
                <?php
                // Only include the forms dropdown if we have forms
                $forms = GFAPI::get_forms();
                if ( !empty( $forms ) ) {
                    usort( $forms, function( $a, $b ) {
                        return strcasecmp( $a[ 'title' ], $b[ 'title' ] );
                    } );
                    if ( $entry_id ) {
                        $selected_form_id = 0;
                        $disable_form_filter = ' disabled';
                        $option_0 = 'Will get form from entry';
                    } else {
                        $disable_form_filter = '';
                        $option_0 = '-- Select a Form --';
                    }
                    ?>
                    <div class="search-field">
                        <label for="gfat-forms-filter"><?php echo esc_html__( 'Form', 'gf-tools' ); ?>:</label>
                        <select name="form_id" id="gfat-forms-filter"<?php echo esc_attr( $disable_form_filter ); ?>>
                            <option value="0"><?php echo esc_html( $option_0 ); ?></option>
                            <?php
                            foreach ( $forms as $form ) {
                                echo sprintf(
                                    '<option value="%d"%s>%s</option>',
                                    esc_attr( $form[ 'id' ] ),
                                    esc_html( selected( $selected_form_id, $form[ 'id' ], false ) ),
                                    esc_html( $form[ 'title' ] )
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <?php
                }
                ?>
                <input type="submit" value="<?php echo esc_html__( 'Search', 'gf-tools' ); ?>" id="gfat-<?php echo esc_attr( $current_tab ); ?>-search-button" class="button button-primary"/>
            </form>
        </div>
        <br>
        <?php
        // Define list table columns
        $columns = [
            'tag'     => __( 'Merge Tag', 'gf-tools' ),
            'label'   => __( 'Name', 'gf-tools' ),
            'returns' => __( 'Value', 'gf-tools' ),
            'copy'    => __( 'Copy', 'gf-tools' ),
            'ref'     => __( 'Reference', 'gf-tools' )
        ];

        // Collect data
        $data = [];

        foreach ( $merge_tags_to_display as $merge_tag ) {

            $actions = '<div class="tag-actions" data-tag-id="'.$merge_tag[ 'tag' ].'">
                <a href="#" class="button copy-merge-tag">Copy to Clipboard</a>
            </div>';

            $data[] = [
                'tag'       => $merge_tag[ 'tag' ],
                'label'     => $merge_tag[ 'label' ],
                'returns'   => isset( $merge_tag[ 'returns' ] ) ? $merge_tag[ 'returns' ] : '',
                'copy'      => $actions,
                'ref'       => $merge_tag[ 'ref' ]
            ];
        }

        // Filter info
        if ( $user_id && $user_email && $user ) {
            echo '<p>'.esc_html__( 'Showing results with user', 'gf-tools' ).': <code>'.esc_html( $user->display_name ).'</code>, User ID: <code>'.esc_attr( $user_id ).'</code>, '.esc_html__( 'Email', 'gf-tools' ).': <code>'.esc_attr( $user_email ).'</code></p>';
        }

        if ( $post_id && $post ) {
            $post_type = get_post_type( $post_id );
            $post_type_object = get_post_type_object( $post_type );
            $label = $post_type_object->labels->singular_name;
            /* translators: %s is the label being shown in results. */
            echo '<p>'.esc_html( sprintf( __( 'Showing results with %s', 'gf-tools' ), strtolower( $label ) ) ).': <code>'.esc_html( $post->post_title ).'</code>, '.esc_html( $label ).' ID: <code>'.esc_attr( $post_id ).'</code>, '.esc_html__( 'Status', 'gf-tools' ).': <code>'.esc_attr( get_post_status( $post_id ) ).'</code></p>';
        }

        if ( $entry_id && $entry ) {
            $entry_form_id = $entry[ 'form_id' ];
            $entry_form = GFAPI::get_form( $entry_form_id );
            $entry_created_by = $entry_form[ 'created_by' ];
            $entry_name = 'Guest';
            if ( $entry_created_by > 0 ) {
                if ( $entry_user = get_userdata( $entry_created_by ) ) {
                    $entry_name = $entry_user->display_name;
                }
            }
            echo '<p>'.esc_html__( 'Showing results with entry ID', 'gf-tools' ).': <code>'.esc_html( $entry_id ).'</code>, Created On: <code>'.esc_html( (new GF_Advanced_Tools_Helpers)->convert_date_to_wp_timezone( $entry_form[ 'date_created' ], 'F j, Y g:i A T' ) ).'</code>, Created By: <code>'.esc_html( $entry_name ).'</code>, Form: <code>'.esc_attr( $entry_form[ 'title' ] ).'</code></p>';
        }

        if ( $selected_form_id && $form ) {
            echo '<p>'.esc_html__( 'Showing results with form', 'gf-tools' ).': <code>'.esc_html( $form[ 'title' ] ).'</code>, Form ID: <code>'.esc_attr( $selected_form_id ).'</code></p>';
        }

        // Show the table
        $qs = [
            'user'     => $s_user,
            'entry_id' => $entry_id,
            'form_id'  => $selected_form_id,
            'post_id'  => $post_id
        ];
        $this->wp_list_table( $columns, $data, $current_tab, $qs, $total_tags );
    } // End merge_tags()


    /**
     * The pre-populate data that will populate the page and the sidebar
     *
     * @return array
     */
    public function pre_populate_data() {
        $required_text = __( 'Required', 'gf-tools' );
        return [
            [
                'id'     => 'previous_value',
                'title'  => __( 'Previous Value', 'gf-tools' ), 
                'desc'   => __( 'Populate the field with the previous value submitted for the current form and field by the logged-in user.', 'gf-tools' ),
                'code'   => 'previous_value',
            ],
            [
                'id'     => 'timezones',
                'title'  => __( 'Timezones', 'gf-tools' ), 
                'desc'   => __( 'Adds all available timezones to a <strong>drop down</strong> field with the current time in each timezone. Great for setting timezones in registration forms.', 'gf-tools' ),
                'code'   => 'timezones',
            ],
            [
                'id'     => 'users',
                'title'  => __( 'List All Users', 'gf-tools' ), 
                'desc'   => __( 'Adds all users to a <strong>drop down</strong> field in alphabetical order. Values are their user IDs.', 'gf-tools' ),
                'code'   => 'users',
            ],
            [
                'id'     => 'users_role',
                'title'  => __( 'List Users for a Specific Role', 'gf-tools' ), 
                'desc'   => __( 'Adds all users with a specific role to a <strong>drop down</strong> field in alphabetical order. Values are their user IDs.', 'gf-tools' ),
                'code'   => 'users_[role]',
            ],
            [
                'id'     => 'connection',
                'title'  => __( 'Connected Post/Page Data', 'gf-tools' ), 
                'desc'   => __( 'If you have a connected post ID or query string parameter set in your form\'s Advanced Tools settings (under <Strong>Field Population</Strong> > "<strong>Enter the Post ID or Query String Parameter Used to Connect To</strong>"), you can use populate its meta value. Works with posts, pages, or any custom post type.', 'gf-tools' ),
                'code'   => 'connection_[meta_key]',
            ],
            [
                'id'     => 'qs_post',
                'title'  => __( 'Query String Post Meta Value', 'gf-tools' ), 
                'desc'   => __( 'If you add <code>post_id</code> as a query string parameter to your form\'s URL, it will populate the respective post\'s meta value. Works with posts, pages, or any custom post type.', 'gf-tools' ),
                'code'   => 'qs_post_[meta_key]',
                'params' => [
                    /* translators: %s is an example URL for fetching the post ID. */
                    'post_id' => sprintf( __( 'The query string parameter to use in your URL to fetch the post ID. Example: <code>%s</code>', 'gf-tools' ), home_url( 'your-form-page/?post_id=10' ) ).' <em>('.$required_text.')</em>',
                ]
            ],
            [
                'id'     => 'qs_user',
                'title'  => __( 'Query String User Meta Value', 'gf-tools' ), 
                'desc'   => __( 'If you add <code>user_id</code> as a query string parameter to your form\'s URL, it will populate the respective user\'s meta value.', 'gf-tools' ),
                'code'   => 'qs_user_[meta_key]',
                'params' => [
                    /* translators: %s is an example URL for fetching the user ID. */
                    'user_id' => sprintf( __( 'The query string parameter to use in your URL to fetch the user ID. Example: <code>%s</code>', 'gf-tools' ), home_url( 'your-form-page/?user_id=1' ) ).' <em>('.$required_text.')</em>',
                ]
            ],
            [
                'id'     => 'cookie',
                'title'  => __( 'Cookie Data', 'gf-tools' ), 
                'desc'   => __( 'You can populate a field with cookie data, too.', 'gf-tools' ),
                'code'   => 'cookie_[name]',
            ],
            [
                'id'     => 'session',
                'title'  => __( 'Session Data', 'gf-tools' ), 
                'desc'   => __( 'You can populate a field with session data, too.', 'gf-tools' ),
                'code'   => 'session_[name]',
            ],
        ];
    } // End pre_populate_data()


    /**
     * Shortcodes Fields
     *
     * @return void
     */
    public function pre_populate() {
        $docs_base_url = 'https://docs.gravityforms.com';
        $gf_docs_url = $docs_base_url.'/using-dynamic-population/';
        echo '<div class="info-cont">
            <div class="info-box">
                '.wp_kses( 
                    /* translators: %s is the URL to the documentation on dynamic population. */
                    sprintf( __( 'When editing a form field, go to <strong>Advanced</strong> > "<strong>Allow field to be populated dynamically</strong>." Checking this option will enable data to be passed to the form and pre-populate this field dynamically. Data can be passed via Query Strings, Shortcode and/or Hooks. After checking this option, you will need to specify the name of the parameter you will be using to pass data to this field. For more detailed information on how to dynamically populate a field and known limitations please see Gravity Forms\' <a href="%s" target="_blank">documentation on dynamic population</a>.', 'gf-tools' ), 
                    esc_url( $gf_docs_url )
                    ), [ 'strong' => [], 'a' => [ 'href' => [], 'target'=> [] ] ] ).'
                <br><br>
                '.esc_html__( 'Below are parameters that we have pre-built for you.', 'gf-tools' ).'
            </div>
            <div class="info-img">
                <img src="'.esc_url( GFADVTOOLS_PLUGIN_DIR.'includes/img/pre-populate-fields.png' ).'" alt="Screenshot of where to add the parameter name">
            </div>
        </div>';

        $data = $this->pre_populate_data();
        $this->reference_page( $data );
    } // End pre_populate()


    /**
     * Pre-Populate Fields sidebar
     *
     * @return void
     */
    public function sidebar_pre_populate() {
        $data = $this->pre_populate_data();
        $this->reference_sidebar( $data );
    } // End sidebar_pre_populate()


    /**
     * Reference link filter
     *
     * @param string $url
     * @return string
     */
    public function reference_link( $url ) {
        if ( $url == add_query_arg( 'post_type', 'gfat-reports', admin_url( 'edit.php' ) ) ) {
            $ref_text = '<a href="'.$url.'">'.__( 'Front-End Reports', 'gf-tools' ).'</a>';
        
        } else if ( (new GF_Advanced_Tools_Helpers)->str_starts_with( $url, GFADVTOOLS_SETTINGS_URL ) ) {
            $parsedUrl = wp_parse_url( $url );
            $anchor = $parsedUrl[ 'fragment' ];
            $ref_text = 'Forms > Settings > Advanced Tools > <a href="'.$url.'">'.ucwords( str_replace( '_', ' ', $anchor ) ).'</a>';
        
        } else {
            $ref_text = '<a href="'.$url.'" target="_blank">'.$url.'</a>';
        }

        return $ref_text;
    } // End reference_link()


    /**
     * Reference page for shortcodes and merge tags
     *
     * @param array $data
     * @return void
     */
    public function reference_page( $data ) {
        // Helpers
        $HELPERS = new GF_Advanced_Tools_Helpers();

        // Separator
        $sep = '<br><br>';

        // Shortcode data
        $total = count( $data );

        $current = 0;
        foreach ( $data as $d ) {
            $current++;
            
            // Title
            echo '<h2 id="'.esc_attr( $d[ 'id' ] ).'">'.esc_html( $d[ 'title' ] ).'</h2>';

            // Description
            if ( isset( $d[ 'desc' ] ) ) {
                echo '<p class="desc">'.wp_kses_post( $d[ 'desc' ] ).'</p>';
            } else {
                echo '<br>';
            }

            // The shortcode
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
            echo wp_kses( $HELPERS->display_code_box( $d[ 'code' ] ), $code_box_html );

            // Params
            if ( isset( $d[ 'params' ] ) && !empty( $d[ 'params' ] ) ) {
                
                echo '<ul class="params-list">';

                foreach ( $d[ 'params' ] as $param => $desc ) {
                    echo '<li><strong>'.wp_kses( $param, [ 'em' => [], 'a' => [ 'href' => [] ] ] ).'</strong><br>'.wp_kses( $desc, [ 'em' => [], 'strong' => [], 'code' => [] ] ).'</li>';
                }

                echo '</ul>';
            }

            // The reference
            if ( isset( $d[ 'refs' ] ) ) {
                $refs = $d[ 'refs' ];
                $refs_allowed_html = [ 'a' => [ 'href' => [], 'target' => [] ] ];

                if ( is_array( $refs ) ) {

                    echo '<span class="reference"><strong>References:</strong><br>';
                    foreach ( $refs as $ref ) {
                        echo wp_kses( $this->reference_link( $ref ), $refs_allowed_html ).'<br>';
                    }
                    echo '</span>';

                } else if ( is_string( $refs ) ) {

                    echo '<span class="reference"><strong>Reference:</strong> '.wp_kses( $this->reference_link( $refs ), $refs_allowed_html ).'</span>';
                }
            }

            // Seperator
            if ( $current < $total ) {
                echo wp_kses( $sep, [ 'br' => [] ] );
            }
        }
    } // End reference_page()


    /**
     * Reference page sidebar
     *
     * @return void
     */
    public function reference_sidebar( $data ) {
        ?>
        <h2>Contents</h2>
        <ul class="nav-tab-list">
            <?php
            foreach ( $data as $d ) {
                ?>
                <li class="nav-tab"><a href="#<?php echo esc_attr( $d[ 'id' ] ); ?>"><?php echo esc_attr( $d[ 'title' ] ); ?></a></li>
                <?php
            }
            ?>
        </ul>
        <?php
    } // End reference_sidebar()


    /**
     * Help page
     *
     * @return void
     */
    public function help() {
        $HELPERS = new GF_Advanced_Tools_Helpers();
        ?>
        <h2><?php echo esc_html__( 'Tooltips', 'gf-tools' ); ?></h2>
        <?php echo esc_html__( "Most settings have a tooltip next to them so you can see helpful information about what it does. Be sure to check them out!", 'gf-tools' ); ?>
        <br>
        <img src="<?php echo esc_url( GFADVTOOLS_PLUGIN_DIR.'includes/img/tooltip.png' ); ?>" alt="<?php echo esc_html__( 'Example of tooltip', 'gf-tools' ); ?>">

        <br><br><br><hr><br><br>
        <h2><?php echo esc_html__( 'Frequently Asked Questions', 'gf-tools' ); ?></h2>

        <br>
        <h3><?php echo esc_html__( "What's the purpose of connecting a form to a post or page?", 'gf-tools' ); ?></h3>
        <?php echo wp_kses( 
            /* translators: %1$s is the merge tag for meta data connection; %2$s is an example URL; %3$s is the query string parameter. */
            sprintf( __( "You can connect a form to a another post, page, or custom post type to populate meta data into your form. You can use merge tags %1\$s to display the data in your confirmations or notifications. To set up, navigate to your form's Advanced Tools settings, and scroll down to Field Population. You can either enter a post ID to connect to a single post for the entire form, or if you want to use the same form for multiple posts, like I do for using a single evaluation across multiple training posts, you can enter a query string parameter instead. Then you would pass the post ID in the URL like: %2\$s, whereas %3\$s would be the query string parameter. This is useful to combine reports into one, as well as to minimize the number of forms and pages you have to create and manage if they're all going to be the same anyway.", 'gf-tools' ),
                '<code>{connection:[meta_key]}</code>',
                '<code>'.home_url( '/your-form-page/?post_id=1' ).'</code>',
                '<code>post_id</code>'
            ), [ 'code' => [] ] ); ?>

        <br><br>
        <h3><?php echo esc_html__( "Can I use the same spam records across multiple sites?", 'gf-tools' ); ?></h3>
        <?php echo wp_kses( __( 'Yes. Navigate to Forms > Settings > Advanced Tools. Scroll down to the Entries section. Where it says, "Enable Enhanced Spam Filtering," choose "Host" on the host site, and generate a new API Key. On the other sites, choose "Client" and enter the API Key from the host site, along with the URL of the host site. Then on the host site you need to create a database table where you will store the spam records. To do so, click on "Manage Spam List" from these settings, or navigate to Forms > Advanced Tools > Spam List. <strong>You will only need to create a database table on the host site!</strong> Now, on the client site you can go to the spam list and you should see the list of spam records from the host site and a form where you can add a new record. Records will be saved on the host site\'s database where all sites will use the same list.', 'gf-tools' ), [ 'strong' => [] ] ); ?>

        <br><br>
        <h3><?php echo esc_html__( "How do I use the global signatures?", 'gf-tools' ); ?></h3>
        <?php echo wp_kses(
            /* translators: %1$s is the merge tag for confirmation signature; %2$s is the merge tag for notification signature. */
            sprintf( __( "Navigate to Forms > Settings > Advanced Tools. Scroll down to the Confirmatations and Notifications sections. Create a confirmations signature and/or a notifications signature here. Then use the %1\$s merge tag on the bottom of your confirmations where you want to use the confirmation signature. Likewise, use the %2\$s merge tag on the notifications where you want to use the notifications signature.", 'gf-tools' ),
                '<code>{confirmation_signature}</code>',
                '<code>{notification_signature}</code>'
            ), [ 'code' => [] ] ); ?>

        <br><br>
        <h3><?php echo esc_html__( "How do I make custom merge tags?", 'gf-tools' ); ?></h3>
        <?php echo wp_kses( 
            /* translators: %s is the merge tag format for custom tags. */
            sprintf( __( "Navigate to Forms > Settings > Advanced Tools. Scroll down to the Merge Tags section. Add a new field. Enter a label that you want to use in the merge tag drop downs. Enter a modifier, which will be used in the merge tag itself (ie. %s).", 'gf-tools' ),
                '<code>{gfat:[modifier]}</code>'
            ), [ 'code' => [] ] ); ?>
        <ul>
            <li><?php echo esc_html__( 'For a direct value (such as a contact phone number that may change in the future), you can select "Value" and enter the text or numeric value that you want the merge tag to populate.', 'gf-tools' ); ?></li>
            <li><?php echo esc_html__( 'For more advanced users, you can select "Callback Function," and include the callback function name. This way you can populate stuff more dynamically. Your function should look like this:', 'gf-tools' ); ?></li>
        </ul>
        <?php 
        $code = "<?php\nfunction callback_name( \$form, \$entry ) {\n    return 'your value';\n}\n?>";
        echo wp_kses_post( $HELPERS->display_code_box( $code ) ); 
        ?>

        <br>
        <h3><?php echo esc_html__( "How do I make custom form settings?", 'gf-tools' ); ?></h3>
        <?php echo esc_html__( "Navigate to Forms > Settings > Advanced Tools. Scroll down to the For Developers section. Add a new field and enter the field label, meta key and field type. The field will then be added to all of your forms' settings. The form setting values are saved on the form object, and can be used in your custom queries.", 'gf-tools' ); ?>

        <br><br><br><hr><br><br>
        <h2><?php echo esc_html__( 'Plugin Support', 'gf-tools' ); ?></h2>
        <br><img src="<?php echo esc_url( GFADVTOOLS_PLUGIN_DIR.'includes/img/discord.png' ); ?>" width="auto" height="100">
        <p><?php echo esc_html__( 'For fastest assistance with this plugin or have suggestions for improving it, please join my Discord server.', 'gf-tools' ); ?></p>
        <a class="button button-primary" href="<?php echo esc_url( GFADVTOOLS_DISCORD_SUPPORT_URL ); ?>" target="_blank"><?php echo esc_html__( 'Join Our Support Server', 'gf-tools' ); ?> »</a><br>

        <br>
        <p><?php echo esc_html__( 'If you would rather get support on WordPress.org, you can do so here:', 'gf-tools' ); ?></p>
        <a class="button button-primary" href="https://wordpress.org/support/plugin/gf-tools/" target="_blank"><?php echo esc_html__( 'WordPress.org Plugin Support Page', 'gf-tools' ); ?> »</a><br>

        <br><br><br><hr><br><br>
        <?php
        $HELPERS->apos37_plugin_links();
    } // End help()


    /**
     * Return the WP List Table
     *
     * @param array $columns
     * @param array $data
     * @param string $current_tab
     * @param array $qs             [ 'param' => $value, 'param' => $value ]
     * @return void
     */
    public function wp_list_table( $columns, $data, $current_tab, $qs = [], $total_count = -1, $checkbox_name = false, $checkbox_value = false, $incl_end_form = false ) {
        // Per page
        if ( isset( $_GET[ 'per_page' ] ) && isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash ( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce ) ) {
            $current_per_page = intval( $_GET[ 'per_page' ] );
            update_option( 'gfadvtools_per_page', $current_per_page );
        } else {
            $current_per_page = get_option( 'gfadvtools_per_page', 25 );
        }

        // Display the table
        $table = new GF_Advanced_Tools_WP_List_Table( $columns, $data, $current_per_page, $total_count, $checkbox_name, $checkbox_value );
        $table->prepare_items();
        $table->display();

        // End form
        if ( $incl_end_form ) {
            echo '</form>';
        }

        // Add per-page dropdown
        $per_page_options = [ 5, 10, 25, 50 ];
        if ( !in_array( $current_per_page, $per_page_options ) ) {
            $per_page_options[] = $current_per_page;
            sort( $per_page_options );
        }

        // Additional query strings
        $inputs = [];
        if ( !empty( $qs ) ) {
            foreach ( $qs as $param => $value ) {
                $inputs[] = '<input type="hidden" name="'.$param.'" value="'.$value.'">';
            }
        }
        ?>
        <form id="gfat-items-per-page" method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr( GFADVTOOLS_TEXTDOMAIN ); ?>">
            <input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
            <?php echo wp_kses( implode( '', $inputs ), [ 'input' => [ 'type' => [], 'name' => [], 'value' => [] ] ] ); ?>
            <?php wp_nonce_field( $this->nonce, '_wpnonce', false ); ?>
            <label for="per_page"><?php esc_html_e( 'Items per page:', 'gf-tools' ); ?></label>
            <select name="per_page" id="per_page" onchange="this.form.submit()">
                <?php foreach ( $per_page_options as $option ) : ?>
                    <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $option, $current_per_page ); ?>>
                        <?php echo esc_html( $option ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php
    } // End wp_list_table()


    /**
     * Get all form ids
     *
     * @return void
     */
    public function ajax_get_all_spam_entry_ids() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( esc_html__( 'No naughty business please.', 'gf-tools' ) );
        }
        
        // Get the ID
        $form_id = isset( $_REQUEST[ 'formID' ] ) ? absint( $_REQUEST[ 'formID' ] ) : false;

        // Make sure we have a source URL
        if ( $form_id ) {

            // Get the entries filtered by date
            $search_criteria = [
                'status' => 'spam',
            ];
            // $count_entries = GFAPI::count_entries( $form_id, $search_criteria );
            // $paging = [ 'offset' => 0, 'page_size' => $count_entries ];
            $entry_ids = GFAPI::get_entry_ids( $form_id, $search_criteria, [], [] );

            // Return them
            if ( empty( $entry_ids ) ) {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'No entries found for form ID '.$form_id.'.';
            } else {
                $result[ 'type' ] = 'success';
                $result[ 'entry_ids' ] = $entry_ids;
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No form ID provided.';
        }

        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_get_all_spam_entry_ids()


    /**
     * Delete all spam entries for a form
     *
     * @return void
     */
    public function ajax_delete_spam_entry() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( esc_html__( 'No naughty business please.', 'gf-tools' ) );
        }
    
        // Get the IDs
        $form_id = isset( $_REQUEST[ 'formID' ] ) ? absint( $_REQUEST[ 'formID' ] ) : false;
        $entry_id = isset( $_REQUEST[ 'entryID' ] ) ? absint( $_REQUEST[ 'entryID' ] ) : false;

        // Make sure we have a source URL
        if ( $form_id && $entry_id ) {

            // Delete the entry
            $delete = GFAPI::delete_entry( $entry_id );
            if ( !$delete ) {
                $result[ 'type' ] = 'error';
                $result[ 'msg' ] = 'Count not delete entry ID '.$entry_id.'.';
            } else {
                $result[ 'type' ] = 'success';
            }

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No form ID and/or entry ID found.';
        }
        
        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_delete_spam_entry()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'forms_page_'.GFADVTOOLS_TEXTDOMAIN ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_dashboard', 'enqueued' ) ) {
            wp_localize_script( 'gfadvtools_dashboard', 'gfat_dashboard', [
                'text'     => [
                    'merge_tags_filter_field' => __( 'Will get form from entry', 'gf-tools' ),
                    'select_a_form'           => __( 'Select a Form', 'gf-tools' ),
                    'merge_tags_user_field'   => __( 'Will get user from entry', 'gf-tools' ),
                    'merge_tags_postid_entry' => __( 'Will get ID from entry', 'gf-tools' ),
                    'merge_tags_postid_form'  => __( 'Will get ID from form', 'gf-tools' ),
                    'spam_delete_all'         => __( 'WARNING! This operation cannot be undone. Permanently delete all spam? "Ok" to delete. "Cancel" to abort.', 'gf-tools' ),
                    'spam_canceled'           => __( 'Operation canceled', 'gf-tools' ),
                    'something_went_wrong'    => __( 'Uh oh! Something went wrong. ', 'gf-tools' ),
                    'unexpected_error'        => __( 'An unexpected error occurred.', 'gf-tools' ),
                    'spam_delete_record'      => __( 'Are you sure you want to delete this record? This cannot be undone.', 'gf-tools' ),
                    'spam_select_records'     => __( 'Please select the records you want to delete.', 'gf-tools' ),
                    'spam_delete_records'     => __( 'Are you sure you want to delete these records? This cannot be undone.', 'gf-tools' ),
                    'report_delete'           => __( 'Are you sure you want to delete this report? This cannot be undone.', 'gf-tools' ),
                ],
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( $this->nonce )
            ] );
        }
    } // End enqueue_scripts()
}