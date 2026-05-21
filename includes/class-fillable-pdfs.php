<?php
/**
 * Fillable PDFs class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Fillable_PDFs {

    /**
	 * Constructor
	 */
	public function __construct() {

        // Add AJAX action for clearing all Fillable PDFs
        add_action( 'wp_ajax_gfat_clear_fillable_pdfs', [ $this, 'ajax_clear_fillable_pdfs' ] );

        // Cron hook for automated cleanup
        add_action( 'gfat_cron_clear_fillable_pdfs', [ $this, 'run_scheduled_cleanup' ] );

	} // End __construct()


    /**
     * Manage WP Cron scheduling based on user setting
     */
    public function update_cleanup_schedule( $schedule ) {
        $hook = 'gfat_cron_clear_fillable_pdfs';
        wp_clear_scheduled_hook( $hook );

        if ( $schedule !== 'none' ) {
            wp_schedule_event( time(), $schedule, $hook );
        }
    } // End update_cleanup_schedule()


    /**
     * Triggered by WP Cron
     */
    public function run_scheduled_cleanup() {
        // Run with debug logging so we can track cron success in error_log
        $this->clear_all( [], true, false, true );
    } // End run_scheduled_cleanup()


    /**
     * Clear all Fillable PDFs for specified forms and entries
     */
    private function clear_all( $form_ids = [], $active_only = true, $force_all = false, $debug_results = true ) {
        if ( ! function_exists( 'fg_fillablepdfs' ) ) {
            error_log( 'Fillable PDFs plugin is not active. Skipping PDF deletion.' );
            return;
        }

        global $wpdb;
        $transient_key = 'gfadvtools_last_pdf_cleanup';
        $last_run      = get_transient( $transient_key );

        if ( $force_all || ! $last_run ) {
            $registered_date = get_user_option( 'user_registered', 1 );
            $start_date      = wp_date( 'Y-m-d H:i:s', strtotime( $registered_date ) );
        } else {
            $start_date = $last_run;
        }

        if ( empty( $form_ids ) ) {
            $table_form   = $wpdb->prefix . 'gf_form';
            $where_active = $active_only ? ' AND is_active = 1' : '';
            $form_ids     = $wpdb->get_col( "SELECT id FROM {$table_form} WHERE is_trash = 0{$where_active}" );
        }

        if ( empty( $form_ids ) ) {
            return;
        }

        $table_entry  = $wpdb->prefix . 'gf_entry';
        $placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
        
        $query = $wpdb->prepare(
            "SELECT id FROM {$table_entry} WHERE form_id IN ($placeholders) AND date_created >= %s AND status = 'active' ORDER BY date_created ASC",
            array_merge( $form_ids, [ $start_date ] )
        );

        $entry_ids = $wpdb->get_col( $query );

        if ( empty( $entry_ids ) ) {
            set_transient( $transient_key, wp_date( 'Y-m-d H:i:s' ), WEEK_IN_SECONDS );
            return;
        }

        $chunks        = array_chunk( $entry_ids, 100 );
        $deleted_count = 0;

        foreach ( $chunks as $chunk ) {
            foreach ( $chunk as $entry_id ) {
                fg_fillablepdfs()->delete_entry_pdfs( absint( $entry_id ) );
                $deleted_count++;
            }
        }

        set_transient( $transient_key, wp_date( 'Y-m-d H:i:s' ), WEEK_IN_SECONDS );

        update_option( 'gfat_last_pdf_cleanup_time', time() );

        if ( $debug_results ) {
            error_log( sprintf( 'GFAdvTools: Successfully deleted Fillable PDFs for %d entries.', $deleted_count ) );
        }
    } // End clear_all()


    /**
     * AJAX handler to clear all Fillable PDFs
     */
    public function ajax_clear_fillable_pdfs() {
        check_ajax_referer( 'gfat_clear_pdfs' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $offset = isset( $_POST[ 'offset' ] ) ? absint( wp_unslash( $_POST[ 'offset' ] ) ) : 0;
        $total  = isset( $_POST[ 'total' ] ) ? absint( wp_unslash( $_POST[ 'total' ] ) ) : 0;
        $mode   = isset( $_POST[ 'mode' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'mode' ] ) ) : 'all';
        $limit  = 250; 
        $batch_count = 0;

        // --- SAFETY CHECK FOR DEEP CLEAN ---
        if ( $mode === 'deep' ) {
            $last_cleanup = get_option( 'gfat_last_pdf_cleanup_time' );
            $one_day_ago  = time() - DAY_IN_SECONDS;

            if ( ! $last_cleanup || $last_cleanup < $one_day_ago ) {
                wp_send_json_error( __( 'Deep Clean is locked. You must run "Clear All Connected PDFs" first within the last 24 hours to ensure database-linked files are handled.', 'gf-tools' ) );
            }
        }

        global $wpdb;
        $table_entry = $wpdb->prefix . 'gf_entry';
        $table_form  = $wpdb->prefix . 'gf_form';
        $status_where = ( $mode === 'inactive' ) ? "AND f.is_active = 0" : "";

        // --- DEEP CLEAN MODE (Direct Disk Access) ---
        if ( $mode === 'deep' ) {
            $forms = GFAPI::get_forms( true, false );
            if ( $offset === 0 ) {
                $total = count( $forms );
            }

            $slice = array_slice( $forms, $offset, $limit );
            $cutoff_time = time() - ( 30 * DAY_IN_SECONDS );
            
            foreach ( $slice as $form ) {
                $path = fg_fillablepdfs()::get_form_pdf_path( absint( $form[ 'id' ] ) );
                if ( is_dir( $path ) ) {
                    $items = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator( $path, RecursiveDirectoryIterator::SKIP_DOTS ),
                        RecursiveIteratorIterator::CHILD_FIRST 
                    );
                    foreach ( $items as $item ) {
                        $item_path = $item->getRealPath();
                        if ( $item->isFile() ) {
                            if ( filemtime( $item_path ) < $cutoff_time ) {
                                unlink( $item_path );
                                $batch_count++;
                            }
                        } elseif ( $item->isDir() ) {
                            @rmdir( $item_path );
                        }
                    }
                }
            }
        } 
        // --- STANDARD MODES (Database Driven) ---
        else {
            if ( $offset === 0 ) {
                $total = $wpdb->get_var( "SELECT COUNT(e.id) FROM {$table_entry} e JOIN {$table_form} f ON e.form_id = f.id WHERE e.status = 'active' $status_where" );
            }

            $entry_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT e.id FROM {$table_entry} e JOIN {$table_form} f ON e.form_id = f.id WHERE e.status = 'active' $status_where LIMIT %d OFFSET %d",
                $limit, $offset
            ) );

            if ( ! empty( $entry_ids ) ) {
                foreach ( $entry_ids as $entry_id ) {
                    fg_fillablepdfs()->delete_entry_pdfs( absint( $entry_id ) );
                    $batch_count++;
                }
            }
        }

        $is_done = ( $offset + $limit >= $total ) || ( $mode !== 'deep' && empty( $entry_ids ) );

        if ( $is_done ) {
            // We only update the cleanup time for "all" or "inactive" modes to satisfy the Deep Clean lock
            if ( $mode !== 'deep' ) {
                update_option( 'gfat_last_pdf_cleanup_time', time() );
            }

            wp_send_json_success( [ 
                'done'               => true, 
                'processed_in_batch' => $batch_count, // Fixed: Send the last batch's count!
                'message'            => __( 'Cleanup complete!', 'gf-tools' ) 
            ] );
        }

        wp_send_json_success( [ 
            'done'               => false, 
            'offset'             => $offset + $limit,
            'total'              => $total,
            'processed_in_batch' => $batch_count,
            'message'            => __( 'Processing batch...', 'gf-tools' )
        ] );
    } // End ajax_clear_fillable_pdfs()
}