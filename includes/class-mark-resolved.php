<?php 
/**
 * Mark Resolved class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Mark_Resolved {

    /**
     * Nonce
     *
     * @var string
     */
    private $nonce = 'gfat_change_entry_status';


    /**
     * The statuses
     *
     * @var array
     */
    public $statuses = [];


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings ) {
        // Check if it's an AJAX request
        $is_ajax_request = defined( 'DOING_AJAX' ) && DOING_AJAX;

        // Update the plugin settings
        if ( !$is_ajax_request && ( !$form_settings || !isset( $form_settings[ 'mark_resolved' ] ) || !$form_settings[ 'mark_resolved' ] ) ) {
            return;
        }

        // Update the statuses
        $this->statuses = [
            'unresolved'  => __( 'Unresolved', 'gf-tools' ),
            'in_progress' => __( 'In Progress', 'gf-tools' ),
            'resolved'    => __( 'Resolved', 'gf-tools' ),
        ];

        // Add entry meta boxes
        add_filter( 'gform_entry_detail_meta_boxes', [ $this, 'entry_meta_box' ], 10, 3 );

        // Add entry meta
        add_filter( 'gform_entry_meta', [ $this, 'add_entry_meta' ], 10, 2 );

        // Column content
        add_action( 'gform_entries_field_value', [ $this, 'col_content' ], 10, 4 );

        // Add mark as resolved action link
        add_action( 'gform_entries_first_column_actions', [ $this, 'first_column_actions' ], 10, 4 );
        add_action( 'gform_pre_entry_list', [ $this, 'pre_entry_list' ] );

        // Add the Resolved option to bulk actions
        add_filter( 'gform_entry_list_bulk_actions', [ $this, 'add_actions' ], 10, 2 );
        add_action( 'gform_entry_list_action', [ $this, 'bulk' ], 10, 3 );

        // Ajax
        add_action( 'wp_ajax_mark_resolved', [ $this, 'ajax_change_status' ] );
        add_action( 'wp_ajax_nopriv_mark_resolved', [ 'GF_Advanced_Tools_Helpers', 'ajax_must_login' ] );

        // JQuery
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	} // End __construct()


    /**
     * Add entry meta box
     *
     * @param array $meta_boxes
     * @param [ type ] $entry
     * @param [ type ] $form
     * @return void
     */
    public function entry_meta_box( $meta_boxes, $entry, $form ) {
        if ( !isset( $meta_boxes[ 'resolved_entry' ] ) ) {
            $meta_boxes[ 'resolved_entry' ] = [
                'title'         => esc_html__( 'Is Entry Resolved?', 'gf-tools' ),
                'callback'      => [ $this, 'entry_meta_box_content' ],
                'context'       => 'side',
                'callback_args' => [ $entry ],
            ];
        }
        return $meta_boxes;
    } // End entry_meta_box()

    
    /**
     * The content of the meta box
     *
     * @param array $args
     * @return void
     */
    public function entry_meta_box_content( $args ) {
        $entry = $args[ 'entry' ];
        $entry_id = $entry[ 'id' ];

        $nonce_verified = isset( $_POST[ $this->nonce ] ) && wp_verify_nonce( sanitize_key( $_POST[ $this->nonce ] ), $this->nonce );
        $status_id = isset( $_POST[ 'resolved_status' ] ) ? sanitize_key( $_POST[ 'resolved_status' ] ) : false;

        if ( $status_id && $nonce_verified ) {
            $this->update_entry_meta( $entry_id, $status_id );
        }

        if ( !$status_id ) {
            $status_id = isset( $entry[ 'resolved' ] ) ? sanitize_key( $entry[ 'resolved' ] ) : '';
        }

        // Start the container
        ?>
        <?php wp_nonce_field( $this->nonce, $this->nonce ); ?>
        <select name="resolved_status" class="<?php echo esc_attr( $status_id ); ?>">
            <option value="unresolved"><?php echo esc_html( $this->statuses[ 'unresolved' ] ); ?></option>
            <option value="in_progress"<?php echo esc_html( ( $status_id == 'in_progress' ) ? ' selected' : '' ); ?>><?php echo esc_html( $this->statuses[ 'in_progress' ] ); ?></option>
            <option value="resolved"<?php echo esc_html( ( $status_id == 'resolved' ) ? ' selected' : '' ); ?>><?php echo esc_html( $this->statuses[ 'resolved' ] ); ?></option>
        </select>
        <br><br>
        <input type="submit" value="Update Status" class="button">
        <?php
    } // End entry_meta_box_content()


    /**
     * Update the meta on an entry
     *
     * @param [ type ] $entry_id
     * @param [ type ] $value
     * @param [ type ] $date
     * @param [ type ] $user_id
     * @return void
     */
    public function update_entry_meta( $entry_id, $status ) {
        $now = gmdate( 'Y-m-d H:i:s' );
        $user_id = get_current_user_id();

        GFAPI::update_entry_field( $entry_id, 'resolved', $status );
        GFAPI::update_entry_field( $entry_id, 'resolved_date', $now );
        GFAPI::update_entry_field( $entry_id, 'resolved_by', $user_id );
        
        $user = get_user_by( 'id', $user_id );
        $display_name = $user->display_name;

        $display_status = $this->statuses[ $status ];

        if ( $status == 'resolved' ) {
            $sub_type = 'success';
        } elseif ( $status == 'in_progress' ) {
            $sub_type = 'warning';
        } else {
            $sub_type = 'error';
        }
        
        /* translators: %s is the status being displayed. */
        $note = sprintf( __( 'Marked as %s', 'gf-tools' ), esc_html( $display_status ) );
        
        RGFormsModel::add_note( $entry_id, $user_id, $display_name, $note, 'gf-tools', $sub_type );
        GFCommon::log_debug( __METHOD__ . '(): Marking Entry #'.$entry_id.' as '.$display_status );
    } // End update_entry_meta()


    /**
     * Add entry meta
     *
     * @param array $entry_meta
     * @param integer $form_id
     * @return array
     */
    public function add_entry_meta( $entry_meta, $form_id ) {
        $entry_meta[ 'resolved' ] = [
            'label'                      => __( 'Resolved', 'gf-tools' ),
            'is_numeric'                 => false,
            'update_entry_meta_callback' => [ $this, 'default_entry_meta' ],
            'is_default_column'          =>  true
        ];
        $entry_meta[ 'resolved_date' ] = [
            'label'                      => __( 'Resolved Date', 'gf-tools' ),
            'is_numeric'                 => false,
            'update_entry_meta_callback' => [ $this, 'default_entry_meta' ],
            'is_default_column'          => false
        ];
        $entry_meta[ 'resolved_by' ] = [
            'label'                      => __( 'Resolved By', 'gf-tools' ),
            'is_numeric'                 => false,
            'update_entry_meta_callback' => [ $this, 'default_entry_meta' ],
            'is_default_column'          => false
        ];
    
        return $entry_meta;
    } // End add_entry_meta()

    
    /**
     * Default value upon form submission or editing an entry
     *
     * @param [ type ] $key
     * @param [ type ] $entry
     * @param [ type ] $form
     * @return void
     */
    public function default_entry_meta( $key, $entry, $form ) {
        return ' ';
    } // End default_entry_meta()


    /**
     * Column content
     *
     * @param mixed $value
     * @param int $form_id
     * @param int $field_id
     * @param array $entry
     * @return string
     */
    public function col_content( $value, $form_id, $field_id, $entry ) {
        $HELPERS = new GF_Advanced_Tools_Helpers();

        if ( $field_id == 'resolved' && isset( $value ) && $value ) {
            $value = isset( $this->statuses[ $value ] ) ? $this->statuses[ $value ] : '';

        } elseif ( $field_id == 'resolved_by' && isset( $value ) && $value ) {
            $user = get_user_by( 'id', $value );
            $value = $user->display_name;

        } elseif ( $field_id == 'resolved_date' && isset( $value ) && $value ) {
            $now = gmdate( 'Y-m-d H:i:s', strtotime( $value ) );
            $value = $HELPERS->convert_date_to_wp_timezone( $now, 'F j, Y \a\t g:i a T' );

        // Let's also update the entry date while we're at it so we have correct timezone
        } elseif ( $field_id == 'date_created' ) {
            $explode_date = explode( ' at ', $value );
            $value = $HELPERS->convert_date_to_wp_timezone( $explode_date[0], 'F j, Y \a\t g:i a T' );
        }

        return $value;
    } // End col_content()


    /**
     * Action link
     *
     * @param int $form_id
     * @param int $field_id
     * @param mixed $value
     * @param array $entry
     * @return void
     */
    public function first_column_actions( $form_id, $field_id, $value, $entry ) {
        $entry_id = $entry[ 'id' ];

        $status = isset( $entry[ 'resolved' ] ) ? sanitize_key( $entry[ 'resolved' ] ) : 'unresolved';

        $class_in_progress = ( $status !== 'in_progress' ) ? '' : ' hide';
        $class_resolved = ( $status != 'resolved' ) ? '' : ' hide';
        $class_unresolved = ( $status == 'resolved' || $status == 'in_progress' ) ? '' : ' hide';

        $class_in_progress_sep = ( $class_resolved === '' ) ? '' : ' hide';
        $class_resolved_sep = ( $class_unresolved === '' ) ? '' : ' hide';

        echo '<span>| 
            <a class="gfat-mark-resolved in-progress'.esc_attr( $class_in_progress ).'" data-status="in_progress" data-entry="'.esc_attr( $entry_id ).'" href="#" style="display: inline;">'.esc_html__( 'Mark in progress', 'gf-tools' ).'</a>
            <i class="gfat-mark-resolved in-progress-sep'.esc_attr( $class_in_progress_sep ).'">|</i>
            <a class="gfat-mark-resolved resolved'.esc_attr( $class_resolved ).'" data-status="resolved" data-entry="'.esc_attr( $entry_id ).'" href="#" style="display: inline;">'.esc_html__( 'Mark resolved', 'gf-tools' ).'</a>
            <i class="gfat-mark-resolved resolved-sep'.esc_attr( $class_resolved_sep ).'">|</i>
            <a class="gfat-mark-resolved unresolved'.esc_attr( $class_unresolved ).'" data-status="unresolved" data-entry="'.esc_attr( $entry_id ).'" href="#" style="display: inline;">'.esc_html__( 'Mark unresolved', 'gf-tools' ).'</a>
        </span>';
    } // End first_column_actions()
    

    /**
     * Update the list if mark resolved is set in bulk on entry list
     *
     * @param int $form_id
     * @return void
     */
    public function pre_entry_list( $form_id ) {
        $current_url = add_query_arg( [
            'page'     => 'gf_entries',
            'id'       => $form_id,
            '_wpnonce' => wp_create_nonce( $this->nonce )
        ], admin_url( 'admin.php' ) );

        // Update the entry meta
        $nonce_verified = isset( $_GET[ '_wpnonce' ] ) && wp_verify_nonce( sanitize_key( $_GET[ '_wpnonce' ] ), $this->nonce );

        if ( isset( $_GET[ 'resolved' ] ) && absint( $_GET[ 'resolved' ] ) > 0 && $nonce_verified ) {
            $this->update_entry_meta(  absint( $_GET[ 'resolved' ] ), 'resolved' );
            header( 'location: '.$current_url );

        } elseif ( isset( $_GET[ 'in_progress' ] ) && absint( $_GET[ 'in_progress' ] ) > 0 && $nonce_verified ) {
            $this->update_entry_meta( absint( $_GET[ 'in_progress' ] ), 'in_progress' );
            header( 'location: '.$current_url );

        } elseif ( isset( $_GET[ 'unresolved' ] ) && absint( $_GET[ 'unresolved' ] ) > 0 && $nonce_verified ) {
            $this->update_entry_meta( absint( $_GET[ 'unresolved' ] ), 'unresolved' );
            header( 'location: '.$current_url );
        }
    } // End pre_entry_list()


    /**
     * Bulk actions
     *
     * @param array $actions
     * @param int $form_id
     * @return array
     */    
    public function add_actions( $actions, $form_id ) {
        $actions[ 'resolved' ]    = __( 'Mark Resolved', 'gf-tools' );
        $actions[ 'in_progress' ] = __( 'Mark In Progress', 'gf-tools' );
        $actions[ 'unresolved' ]  = __( 'Mark Unresolved', 'gf-tools' );
        return $actions;
    } // End add_actions()

    
    /**
     * Add the Resolved option to bulk actions
     *
     * @param string $action
     * @param array $entries
     * @param int $form_id
     * @return void
     */
    public function bulk( $action, $entries, $form_id ) {
        GFCommon::log_debug( __METHOD__ . '(): running.' );
        
        if ( in_array( $action, array_keys( $this->statuses ) ) ) {
            foreach ( $entries as $entry_id ) {
                $this->update_entry_meta( $entry_id, $action );
            }
        }
    } // End bulk()


    /**
     * Change status of entry
     *
     * @return void
     */
    public function ajax_change_status() {
        // Verify nonce
        if ( !isset( $_REQUEST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ 'nonce' ] ) ), $this->nonce ) ) {
            exit( esc_html__( 'No naughty business please.', 'gf-tools' ) );
        }
    
        // Get the IDs
        $status = isset( $_REQUEST[ 'status' ] ) ? sanitize_key( $_REQUEST[ 'status' ] ) : false;
        $entry_id = isset( $_REQUEST[ 'entryID' ] ) ? absint( $_REQUEST[ 'entryID' ] ) : false;

        // Make sure we have a source URL
        if ( $status && $entry_id ) {

            $this->update_entry_meta( $entry_id, $status );

            $current_time = (new GF_Advanced_Tools_Helpers)->convert_date_to_wp_timezone( null, 'F j, Y \a\t g:i a T' );
            $current_user = wp_get_current_user();
            $resolved_by = $current_user->display_name;

            $result[ 'type' ] = 'success';
            $result[ 'resolved_date' ] = $current_time;
            $result[ 'resolved_by' ] = $resolved_by;

        // Nope
        } else {
            $result[ 'type' ] = 'error';
            $result[ 'msg' ] = 'No status and/or entry ID found.';
        }
        
        // Respond
        wp_send_json( $result );
        die();
    } // End ajax_change_status()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function enqueue_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'forms_page_gf_entries' ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_mark_resolved', 'enqueued' ) ) {
            wp_localize_script( 'gfadvtools_mark_resolved', 'gfat_mark_resolved', [
                'text'     => [
                    'in_progress'          => __( 'In Progress', 'gf-tools' ),
                    'resolved'             => __( 'Resolved', 'gf-tools' ),
                    'unresolved'           => __( 'Unresolved', 'gf-tools' ),
                    'something_went_wrong' => __( 'Uh oh! Something went wrong. ', 'gf-tools' ),
                ],
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( $this->nonce )
            ] );
        }
    } // End enqueue_scripts()
}