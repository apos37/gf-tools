<?php
/**
 * Import/Export class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Import_Export {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_import_export_nonce';


    /**
	 * Constructor
	 */
	public function __construct() {

        // Allows the form export filename to be changed.
        add_filter( 'gform_form_export_filename', [ $this, 'form_filename' ], 10, 2 );

        // Exclude BOM Character from Beginning of Entry Export Files
        $exclude_bom = get_option( 'gfat_export_exclude_bom' );
        if ( $exclude_bom ) {
            add_filter( 'gform_include_bom_export_entries', [ $this, 'exclude_bom' ] );
        }

        // Import Spam List
        $spam_filtering = get_option( 'gfat_spam_filtering' );
        if ( $spam_filtering && sanitize_key( $spam_filtering ) != 'client' ) {
            add_filter( 'gform_export_menu', [ $this, 'import_spam_list' ] );
            add_action( 'gform_export_page_import_spam_list', [ $this, 'import_spam_list_content' ] );
        }

        // Enqueue stylesheet
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );

        // TODO: Export Spam List in future version

	} // End __construct()


    /**
     * This is an example of using the plugin settings value inside the function
     *
     * @param string $filename
     * @return string
     */
    public function form_filename( $filename, $form_ids ) {
        // Get the option
        $export_form_filename = get_option( 'gfat_export_form_filename' );

        // Validate
        if ( $export_form_filename && $export_form_filename != $filename ) {
            $filename = sanitize_text_field( $export_form_filename );
            
            // Replace {form_id} with the actual form ID or "all"
            if ( strpos( $filename, '{form_id}' ) !== false ) {
                $form_id_replacement = count( $form_ids ) === 1 ? $form_ids[0] : 'multiple';
                $filename = str_replace( '{form_id}', $form_id_replacement, $filename );
            }
            
            // Regular expression to find date placeholders in curly brackets
            $pattern = '/\{([^}]+)\}/';
            
            // Replace each date placeholder with the formatted current date
            $filename = preg_replace_callback( $pattern, function( $matches ) {
                $date_format = $matches[1];

                // Return the formatted date
                return (new GF_Advanced_Tools_Helpers)->convert_date_to_wp_timezone( gmdate( 'Y-m-d H:i:s' ), $date_format );
            }, $filename );

            // Remove any invalid filename characters
            $filename = str_replace( ' ', '_', $filename );
            $filename = preg_replace( '/[\/:*?"<>|]/', '_', $filename );
        }
        return $filename;
    } // End form_filename()


    /**
     * Exclude BOM Character from Beginning of Entry Export Files
     *
     * @param bool $include_bom
     * @return bool
     */
    public function exclude_bom( $include_bom ) {
        return false;
    } // End exclude_bom()


    /**
     * Import spam list
     *
     * @param array $menu_items
     * @return array
     */
    public function import_spam_list( $menu_items ) {
        $menu_items[] = [
            'name'  => 'import_spam_list',
            'label' => __( 'Import Spam List', 'gf-tools' )
        ];
        return $menu_items;
    } // End import_spam_list()


    /**
     * Import spam list content
     *
     * @return void
     */
    public function import_spam_list_content() {
        GFExport::page_header();

        $msg = '';
        $preview = '';

        // Check if the form has been submitted
        if ( isset( $_REQUEST[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ '_wpnonce' ] ) ), $this->nonce ) && 
             isset( $_SERVER[ 'REQUEST_METHOD' ] ) && sanitize_text_field( wp_unslash( $_SERVER[ 'REQUEST_METHOD' ] ) ) === 'POST' && isset( $_FILES[ 'gf_import_file' ] ) ) {

            // Check for errors in the file upload
            if ( isset( $_FILES[ 'gf_import_file' ][ 'error' ] ) && $_FILES[ 'gf_import_file' ][ 'error' ] === UPLOAD_ERR_OK ) {
                $file_tmp_path = isset( $_FILES[ 'gf_import_file' ][ 'tmp_name' ] ) ? sanitize_text_field( $_FILES[ 'gf_import_file' ][ 'tmp_name' ] ) : false;
                $file_name = isset( $_FILES[ 'gf_import_file' ][ 'name' ] ) ? sanitize_text_field( $_FILES[ 'gf_import_file' ][ 'name' ] ) : false;
                if ( $file_tmp_path && $file_name ) {

                    // Ensure the file is a CSV
                    if ( pathinfo( $file_name, PATHINFO_EXTENSION ) === 'csv' ) {
                        
                        // Read the file contents
                        $csv_data = array_map( 'str_getcsv', file( $file_tmp_path ) );
                        $normalized_header_1 = preg_replace( '/^\xEF\xBB\xBF|\s+/', '', strtolower( $csv_data[0][0] ) );
                        $normalized_header_2 = preg_replace( '/^\xEF\xBB\xBF|\s+/', '', strtolower( $csv_data[0][1] ) );

                        if ( empty( $csv_data ) || !isset( $csv_data[0] ) || count( $csv_data[0] ) < 2 || 
                            $normalized_header_1 !== 'value' || 
                            $normalized_header_2 !== 'action' ) {

                            /* translators: %1$s is the expected header for the first column; %2$s is the expected header for the second column. */
                            $msg = sprintf( __( 'The CSV file must contain a header row with "%1$s" in the first column and "%2$s" in the second column.', 'gf-tools' ),
                                'Value',
                                'Action'
                            );

                        } else {

                            $errors = [];
                            $row_count = 0;

                            // Validate action rows are correct
                            foreach ( $csv_data as $num => $row ) {

                                // Skip the header row
                                if ( $num === 0 ) {
                                    continue;
                                }

                                $value = strtolower( sanitize_text_field( $row[0] ) );
                                $action = sanitize_key( $row[1] );
                                if ( $value == '' && $action == '' ) {
                                    continue;
                                }

                                $row_count++;

                                if ( $value == '' || ( $action != 'allow' && $action != 'deny' ) ) {
                                    if ( $value == '' ) {
                                        $value = '<span class="error">'.__( 'EMPTY', 'gf-tools' ).'</span>';
                                    }
                                    if ( $action == '' ) {
                                        $action = '<span class="error">'.__( 'EMPTY', 'gf-tools' ).'</span>';
                                    } elseif ( $action != 'allow' && $action != 'deny' ) {
                                        $action = '<span class="error strike">'.$action.'</span>';
                                    }

                                    $errors[ $num ] = [
                                        'value'  => $value,
                                        'action' => $action
                                    ];
                                }
                            }

                            if ( !empty( $errors ) ) {

                                $error_count = count( $errors );
                                
                                /* translators: %1$s is the count of errors; %2$s is either 'row' or 'rows' depending on the count; %3$s is the word 'allow'; %4$s is the word 'deny'. */
                                $msg = sprintf( __( 'Oops! There were %1$s %2$s with one or more errors in your file. The first column must include an email, domain, or keyword and cannot be blank. The second column should either include the word %3$s or %4$s, and cannot be blank. Please fix them and try again. Examples from your code:', 'gf-tools' ),
                                    '<strong>'.$error_count.'</strong>',
                                    $error_count === 1 ? __( 'row', 'gf-tools' ) : __( 'rows', 'gf-tools' ),
                                    '<code>allow</code>', 
                                    '<code>deny</code>'
                                );

                                $preview = '<table id="preview-errors-table">
                                    <thead>
                                        <tr>
                                            <td></td>
                                            <td>'.__( 'A', 'gf-tools' ).'</td>
                                            <td>'.__( 'B', 'gf-tools' ).'</td>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                        
                                        // Process the CSV data as needed
                                        $max_rows = 30;
                                        $display_count = 0;
                                        foreach ( $errors as $row => $error ) {
                                            
                                            $display_count++;

                                            $preview .= '<tr>
                                                <td>'.round( $row + 1 ).'</td>
                                                <td>'.$error[ 'value' ].'</td>
                                                <td>'.$error['action'].'</td>
                                            </tr>';

                                            if ( $display_count > $max_rows ) {
                                                break;
                                            }
                                        }

                                    $preview .= '</tbody>
                                </table>';

                            // No errors, continue
                            } else {

                                // Upload them
                                if ( (new GF_Advanced_Tools_Spam)->add_or_update_bulk_local_records( $csv_data ) ) {

                                    // Redirect
                                    $redirect_url = add_query_arg( 'imported', $row_count, gfadvtools_get_plugin_page_tab( 'spam_list' ) );
                                    wp_safe_redirect( $redirect_url );
                                    exit();

                                } else {
                                    $msg = __( 'There was an error uploading the file. Please try again.', 'gf-tools' );
                                }
                            }
                        }

                    } else {
                        $msg = __( 'Please upload a valid CSV file.', 'gf-tools' );
                    }

                } else {
                    $msg = __( 'There was an error uploading the file. Please try again.', 'gf-tools' );
                }

            } else {
                $msg = __( 'There was an error uploading the file. Please try again.', 'gf-tools' );
            }
        }

        if ( !$msg ) {
            /* translators: %1$s is the file extension; %2$s is the header for the first column; %3$s is the header for the second column; %4$s is the allow action; %5$s is the deny action. */
            $msg = sprintf( __( 'Select the CSV file you would like to import. Please make sure your file has the %1$s extension, and that your first row contains a header with %2$s in the first column and %3$s in the second column. Beneath that, the first column should consist of the value (domain, email, or keyword) and the second column should consist of either %4$s or %5$s.', 'gf-tools' ),
                '.csv',
                '<code>Value</code>',
                '<code>Action</code>',
                '<code>allow</code>',
                '<code>deny</code>'
            );
        }

        echo '<div class="gform-settings__content">
            <form method="post" enctype="multipart/form-data" class="gform_settings_form">
                '.wp_kses( wp_nonce_field( $this->nonce, '_wpnonce', true, false ), [
                    'input' => [ 
                        'type'  => [],
                        'id'    => [],
                        'name'  => [],
                        'value' => [],
                    ],
                ] ).'
                <div class="gform-settings-panel gform-settings-panel--full">
                    <header class="gform-settings-panel__header"><legend class="gform-settings-panel__title">'.esc_html__( 'Import Spam List', 'gf-tools' ).'</legend></header>
                    <div class="gform-settings-panel__content">
                        <div class="gform-settings-description">
                            '.wp_kses( $msg, [ 'code' => [] ] ).'<br><br>
                            '.wp_kses_post( $preview ).'
                            '.esc_html__( 'Example of how your CSV file should look', 'gf-tools' ).':<br>
                            <img src="'.esc_url( GFADVTOOLS_PLUGIN_DIR ).'/includes/img/import_spam_list.png">
                        </div>
                        <br><br>
                        <table class="form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><label for="gf_import_file">'.esc_html__( 'Select File', 'gf-tools' ).'</label></th>
                                    <td><input type="file" name="gf_import_file" id="gf_import_file" accept=".csv"></td>
                                </tr>
                            </tbody>
                        </table>
                        <br><br>
                        <input id="submit-btn" type="submit" value="'.esc_html__( 'Import', 'gf-tools' ).'" class="button large primary">
                    </div>
                </div>
            </form>
        </div>';
         
        GFExport::page_footer();
    } // End import_spam_list_content()


    /**
     * Enqueue stylesheets
     *
     * @return void
     */
    public function enqueue_styles() {
        $current_screen = get_current_screen();
        if ( $current_screen->id === 'forms_page_gf_export' ) {
            wp_enqueue_style( 'gfat-import-export', GFADVTOOLS_PLUGIN_DIR.'includes/css/import-export.css', [], time() );
        }
    } // End enqueue_styles()
}