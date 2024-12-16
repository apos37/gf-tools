<?php
/**
 * Gravity Forms Framework
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_addon_framework();

class GF_Advanced_Tools extends GFAddOn {
   
	protected $_version = GFADVTOOLS_VERSION;
	protected $_min_gravityforms_version = '2.2';
	protected $_slug = GFADVTOOLS_TEXTDOMAIN;
	protected $_path = GFADVTOOLS_TEXTDOMAIN.'/'.GFADVTOOLS_TEXTDOMAIN.'.php';
	protected $_full_path = __FILE__;
	protected $_title = GFADVTOOLS_NAME;
	protected $_short_title = 'Advanced Tools';

	private static $_instance = null;


	/**
	 * Get an instance of this class.
	 *
	 * @return GF_Advanced_Tools
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	} // End get_instance()


	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();

        $plugin_settings = $this->get_plugin_settings();

        if ( isset( $_GET[ 'id' ] ) && $form = GFAPI::get_form( absint( $_GET[ 'id' ] ) ) ) { // phpcs:ignore
            $form_settings = $this->get_form_settings( $form );
            $form_id = absint( $_GET[ 'id' ] ); // phpcs:ignore
        } elseif ( isset( $_GET[ 'form_id' ] ) && $form = GFAPI::get_form( absint( $_GET[ 'form_id' ] ) ) ) { // phpcs:ignore
            $form_settings = $this->get_form_settings( $form );
            $form_id = absint( $_GET[ 'form_id' ] ); // phpcs:ignore
        } else {
            $form_settings = false;
            $form_id = false;
        }

        // Classes
        $classes = [
            'wp-list-table'   => false,
            'customize-gf'    => 'Customize',
            'forms-table'     => 'Forms_Table',
            'form-editor'     => 'Form_Editor',
            'spam'            => 'Spam',
            'entries'         => 'Entries',
            'merge-tags'      => 'Merge_Tags',
            'populate-fields' => 'Populate_Fields',
            'confirmations'   => 'Confirmations',
            'notifications'   => 'Notifications',
            'developers'      => 'Developers',
            'mark-resolved'   => 'Mark_Resolved',
            'reports'         => 'Reports',
            'shortcodes'      => 'Shortcodes',
            'form-display'    => 'Form_Display',
            'dashboard'       => 'Dashboard',
        ];

        foreach ( $classes as $file_name => $class ) {
            $file_path = GFADVTOOLS_PLUGIN_ROOT . 'includes/class-' . $file_name . '.php';
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                if ( $class ) {
                    $full_class_name = 'GF_Advanced_Tools_' . $class;
                    new $full_class_name( $plugin_settings, $form_settings, $form_id );
                }
            }
        }

        // Localize scripts for settings
        add_action( 'admin_enqueue_scripts', [ $this, 'localize_scripts' ] );

	} // End init()


    /**
	 * Form settings icon
	 *
	 * @return string
	 */
	public function get_menu_icon() {
        return 'dashicons-admin-tools dashicons';
	} // End get_menu_icon()


	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------


	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
        $scripts = [];

        $script_data = [
            'settings'              => 'page=gf_settings&subview='.GFADVTOOLS_TEXTDOMAIN,
            'forms-table'           => 'page=gf_edit_forms',
            'form-editor'           => 'page=gf_edit_forms',
            'shortcode-generator'   => [ 'page=gf_edit_forms&subview=notification', 'page=gf_edit_forms&subview=confirmation' ],
            'debugging'             => [ 'page=gf_edit_forms', 'page=gf_entries' ],
            'mark-resolved'         => 'page=gf_entries',
            'reports-back'          => [ 'post_type=gfat-reports', 'post.php&action=edit' ],
            'dashboard'             => 'page=gf-tools',
        ];

        foreach ( $script_data as $key => $query ) {
            $enqueue = [];
            if ( is_array( $query ) ) {
                foreach ( $query as $q ) {
                    $enqueue[] = [ 'query' => $q ];
                }
            } else {
                $enqueue[] = [ 'query' => $query ];
            }
            $scripts[] = [
				'handle'    => 'gfadvtools_'.str_replace( '-', '_', $key ),
				'src'       => GFADVTOOLS_PLUGIN_DIR.'/includes/js/'.$key.'.js',
				'version'   => time(), // TODO: $this->_version
				'deps'      => [ 'jquery' ],
				'in_footer' => true,
				'enqueue'   => $enqueue,
			];
        }

		return array_merge( parent::scripts(), $scripts );
	} // End scripts()


    /**
     * Enqueue javascript
     *
     * @return void
     */
    public function localize_scripts( $hook ) {
        // Check if we are on the correct admin page
        if ( $hook !== 'forms_page_gf_settings' ) {
            return;
        }

        // Ensure the script is enqueued by add-on before localizing
        if ( wp_script_is( 'gfadvtools_settings', 'enqueued' ) ) {
            wp_localize_script( 'gfadvtools_settings', 'gfat_settings', [
                'confirm_delete_text' => __( 'Are you sure you want to delete the spam list table? This action cannot be undone.', 'gf-tools' ),
                'clipboard_text'      => __( 'Copied to clipboard!', 'gf-tools' ),
                'generating_text'     => __( 'Generating', 'gf-tools' ),
            ] );
        }
    } // End localize_scripts()


	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {
        $styles = [];

        $style_data = [
            'settings'              => 'page=gf_settings&subview='.GFADVTOOLS_TEXTDOMAIN,
            'form-editor'           => 'page=gf_edit_forms',
            'debugging'             => [ 'page=gf_edit_forms', 'page=gf_entries' ],
            'shortcode-generator'   => [ 'page=gf_edit_forms&subview=notification', 'page=gf_edit_forms&subview=confirmation' ],
            'mark-resolved'         => 'page=gf_entries',
            'reports'               => [ 'post_type=gfat-reports', 'post.php&action=edit' ],
            'reports-back'          => [ 'post_type=gfat-reports', 'post.php&action=edit' ],
            'dashboard'             => 'page=gf-tools',
        ];

        foreach ( $style_data as $key => $query ) {
            $enqueue = [];
            if ( is_array( $query ) ) {
                foreach ( $query as $q ) {
                    $enqueue[] = [ 'query' => $q ];
                }
            } else {
                $enqueue[] = [ 'query' => $query ];
            }
            $styles[] = [
				'handle'    => 'gfadvtools_'.str_replace( '-', '_', $key ),
				'src'       => GFADVTOOLS_PLUGIN_DIR.'/includes/css/'.$key.'.css',
				'version'   => time(), // TODO: $this->_version
				'enqueue'   => $enqueue,
			];
        }

		return array_merge( parent::styles(), $styles );
	} // End styles()


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------


    /**
	 * Creates a custom page for this add-on.
	 */
	public function plugin_page() {
        // Get the settings once
        $plugin_settings = $this->get_plugin_settings();

        // The tabs
        $tabs = [
            'global_search'   => [ 'label' => 'Global Search' ],
            'user_entries'    => [ 'label' => 'User Entries' ],
            'jump_to_entry'   => [ 'label' => 'Jump to Entry' ],
            'entries_by_date' => [ 'label' => 'Entries by Date' ],
            'recent'          => [ 'label' => 'Recent Entries' ],
            'spam_entries'    => [ 'label' => 'Spam Entries' ],
            'spam_list'       => [ 'label' => 'Spam List', 'plugin_setting' => 'spam_filtering' ],
            'reports'         => [ 'label' => 'Front-End Reports' ],
            'shortcodes'      => [ 'label' => 'Shortcodes' ],
            'merge_tags'      => [ 'label' => 'Merge Tags' ],
            'pre_populate'    => [ 'label' => 'Pre-Populate Fields' ],
            'settings'        => [ 'label' => 'Settings' ],
            'help'            => [ 'label' => 'Help' ],
        ];

        // Dashboard url
        $dashboard_url = add_query_arg( 'tab', 'global_search', GFADVTOOLS_DASHBOARD_URL );

        // Active tab
        $active_tab = isset( $_GET[ 'tab' ] ) ? sanitize_key( $_GET[ 'tab' ] ) : false; // phpcs:ignore
        if ( !$active_tab || !isset( $tabs[ sanitize_key( $_GET[ 'tab' ] ) ] ) ) { // phpcs:ignore
            wp_safe_redirect( $dashboard_url ); exit();
        }

        $active_plugin_setting = isset( $tabs[ $active_tab ][ 'plugin_setting' ] ) ? $tabs[ $active_tab ][ 'plugin_setting' ] : false;
        if ( $active_plugin_setting && ( !isset( $plugin_settings[ $active_plugin_setting ] ) || !$plugin_settings[ $active_plugin_setting ] ) ) {
            wp_safe_redirect( $dashboard_url ); exit();
        }

        // Instantiate class
        $DASHBOARD = new GF_Advanced_Tools_Dashboard( $plugin_settings );

        // Set up the page
        ?>
        <div id="gfat-tabs-container">
            <div id="gfat-tabs-sidebar">
                <ul class="nav-tab-list">
                    <?php 
                    foreach ( $tabs as $name => $tab ) {
                        $plugin_setting = isset( $tab[ 'plugin_setting' ] ) ? $tab[ 'plugin_setting' ] : false;
                        if ( $plugin_setting && ( !isset( $plugin_settings[ $plugin_setting ] ) || !$plugin_settings[ $plugin_setting ] ) ) {
                            continue;
                        }

                        $label = $tab[ 'label' ];
                        $active_class = ( $active_tab == $name ) ? ' nav-tab-active' : '';
                        if ( $name == 'settings' ) {
                            $url = GFADVTOOLS_SETTINGS_URL;
                        // } elseif ( $name == 'reports' ) {
                        //     $url = add_query_arg( 'post_type', 'gfat-reports', admin_url( 'edit.php' ) );
                        } else {
                            $url = add_query_arg( 'tab', $name, GFADVTOOLS_DASHBOARD_URL );
                        }
                        ?>
                        <li class="nav-tab<?php echo esc_attr( $active_class ); ?>">
                            <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                <?php
                $sidebar_method = 'sidebar_'.$active_tab;
                if ( method_exists( 'GF_Advanced_Tools_Dashboard', $sidebar_method ) ) {
                    $DASHBOARD->$sidebar_method();
                }
                ?>
            </div>
            <div id="gfat-tab-content">
                <div id="gfat_<?php echo esc_attr( $active_tab ); ?>_content" class="tab-panel">
                    <legend class="page-title"><?php echo esc_attr( $tabs[ $active_tab ][ 'label' ] ); ?> </legend>
                    <div class="tab-content">
                        <?php
                        if ( method_exists( 'GF_Advanced_Tools_Dashboard', $active_tab ) ) {
                            $DASHBOARD->$active_tab();
                        } else {
                            echo 'Oop! Something went wrong. There is no method for <code>(new GF_Advanced_Tools_Dashboard)->'.esc_attr( $active_tab ).'()</code>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
	} // End plugin_page()


	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
        $plugin_settings = $this->get_plugin_settings();
        // dpr( $plugin_settings, null, true );

        if ( !function_exists( 'get_editable_roles' ) ) {
            require_once( GFADVTOOLS_ADMIN_INCLUDES_URL.'user.php' );
        }
        $roles = get_editable_roles();
        $role_choices = [];
        foreach ( $roles as $key => $role ) {
            if ( $key == 'administrator' ) {
                continue;
            }

            $role_choices[] = [
                'label' => $role[ 'name' ],
                'name' => 'entries_export_'.$key
            ];
        }

        if ( is_plugin_active( 'gravityformsquiz/quiz.php' ) ) {
            $incl_quiz_answers_panel = [
                'type'    => 'checkbox',
                'name'    => 'quiz_answers_panel_group',
                'label'   => esc_html__( 'Show Quiz Answers in Side Panel', 'gf-tools' ),
                'tooltip' => esc_html__( 'View quiz answers in a quick reference panel on the right side of the form editor, makes it is easier to compare notes.', 'gf-tools' ),
                'choices' => [
                    [
                        'label' => esc_html__( 'Yes', 'gf-tools' ),
                        'name'  => 'quiz_answers_panel',
                    ],
                ],
            ];
        } else {
            $incl_quiz_answers_panel = [];
        }

        // API Key
        $api_key_data = get_option( 'gfat_spam_api_key' );
        $api_key = !empty( $api_key_data ) && isset( $api_key_data[ 'key' ] ) ? sanitize_text_field( $api_key_data[ 'key' ] ) : '';
        $api_key_class = $api_key != '' ? ' showCopyAPI' : '';
        $api_timestamp = !empty( $api_key_data ) && isset( $api_key_data[ 'timestamp' ] ) ? sanitize_text_field( $api_key_data[ 'timestamp' ] ) : '';
        $api_old_timestamp = strtotime( '-1 year' );
        $api_old_class = ( $api_timestamp <= $api_old_timestamp ) ? ' old' : '';
        $api_timestamp_display = ( $api_timestamp != '' ) ? __( 'Updated: ', 'gf-tools' ).'<span class="date">'.gmdate( 'F j, Y', $api_timestamp ).'</span>' : '';

        // Delete spam list button
        $location = isset( $plugin_settings[ 'spam_filtering' ] ) ? sanitize_key( $plugin_settings[ 'spam_filtering' ] ) : 'local';
        if ( $location != 'client' && get_option( 'gfat_spam_list_table_created' ) ) {
            $nonce = wp_create_nonce( 'gfat_dashboard_nonce' );
            $delete_db_url = add_query_arg( [
                'delete_db' => true,
                '_wpnonce'  => $nonce
            ], gfadvtools_get_plugin_page_tab( 'spam_list' ) );
            $incl_delete_spam_list_button = [
                'type' => 'html',
                'name' => 'delete_spam_list_table',
                'args' => [
                    'html' => '<a id="gfat_delete_spam_list_table" href="'.$delete_db_url.'" class="button">'.esc_html__( 'Delete Spam List Table', 'gf-tools' ).'</a>'
                ],
            ];
        } else {
            $incl_delete_spam_list_button = [];
        }

        // Save items as options since we can't call it from plugin settings
        $nonce_verified = isset( $_POST[ 'gform_settings_save_nonce' ] ) && wp_verify_nonce( sanitize_key( $_POST[ 'gform_settings_save_nonce' ] ), 'gform_settings_save' );

        if ( $nonce_verified ) {

            update_option( 'gfat_spam_filtering', $location );

            if ( isset( $_POST[ '_gform_setting_export_form_filename' ] ) ) {
                $export_form_filename = sanitize_text_field( wp_unslash( $_POST['_gform_setting_export_form_filename'] ) );
                update_option( 'gfat_export_form_filename', $export_form_filename );
            }
    
            if ( isset( $_POST[ '_gform_setting_exclude_bom' ] ) ) {
                $exclude_bom = filter_var( wp_unslash( $_POST['_gform_setting_exclude_bom'] ), FILTER_VALIDATE_BOOLEAN );
                update_option( 'gfat_export_exclude_bom', $exclude_bom );
            }
        }

        // Return
		return [
            [
                'title'  => esc_html__( 'Advanced Tools Global Settings', 'gf-tools' ),
                'fields' => [
                    [
                        'type' => 'html',
                        'name' => 'customize_gravity_forms_section',
                        'args' => [
                            'html' => '<h2 id="customize">Customize Gravity Forms</h2>'
                        ],
                    ],
                    [
                        'type'          => 'text',
                        'input_type'    => 'number',
                        'name'          => 'menu_position',
                        'label'         => esc_html__( 'Position of the Gravity Forms’ “Forms” menu in the WordPress admin menu.', 'gf-tools' ),
                        'tooltip'       => esc_html__( 'Default value ‘16.9’. May need to refresh after saving to see the changes.', 'gf-tools' ),
                        'class'         => 'small',
                        'default_value' => '16.9',
                        'required'      => true
                    ],
                    [
                        'type' => 'html',
                        'name' => 'reports_section',
                        'args' => [
                            'html' => '<br><br><h2 id="reports">Reports</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'federal_fiscal_group',
                        'label'   => esc_html__( 'Set Quarterly Links to Federal Fiscal', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Quarterly links are found with the date filters on our reports.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'federal_fiscal',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'forms_table_section',
                        'args' => [
                            'html' => '<br><br><h2 id="forms_table">Forms Table</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'created_modified_group',
                        'label'   => esc_html__( 'Track When and Who Created and Modified Forms', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Also adds a column on the forms table that displays this information.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'created_modified',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'copy_shortcode_action_group',
                        'label'   => esc_html__( 'Add an Action Link to the Forms Table to Copy Form Shortcode to Clipboard', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'copy_shortcode_action',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'disable_view_count_group',
                        'label'   => esc_html__( 'Disable Forms View Counter', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'disable_view_count',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'hide_conversion_group',
                        'label'   => esc_html__( 'Hide Forms Conversion Column', 'gf-tools' ),
                        'tooltip' => esc_html__( 'The conversion column indicates the percentage of individuals who complete your form after viewing it.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'hide_conversion',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'form_editor_section',
                        'args' => [
                            'html' => '<br><br><h2 id="form_editor">Form Editor</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'password_field_group',
                        'label'   => esc_html__( 'Enable the password field', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'password_field',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'bypass_template_library_group',
                        'label'   => esc_html__( 'Always Bypass Template Library when Creating New Forms', 'gf-tools' ),
                        'tooltip' => esc_html__( 'The template library is the popup that you see when you create a new form. If you always start from scratch, this just saves you a step.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'bypass_template_library',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'ajax_saving_group',
                        'label'   => esc_html__( 'Disable AJAX Saving for All Forms', 'gf-tools' ),
                        'tooltip' => esc_html__( 'The template library is the popup that you see when you create a new form. If you always start from scratch, this just saves you a step.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'ajax_saving',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'united_states_first_group',
                        'label'   => esc_html__( 'Move United States to Top of Countries List on Address Fields', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'united_states_first',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'associated_states_group',
                        'label'   => esc_html__( 'Add Associated States to US States List', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Includes: Federated States of Micronesia, Palau, and Marshall Islands. They are nations with a unique relationship with the United States, typically under the Compact of Free Association, but they are not U.S. states.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'associated_states',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'post_meta_query_group',
                        'label'   => esc_html__( 'Disable Post Meta Query on Form Editor', 'gf-tools' ),
                        'tooltip' => esc_html__( 'The post meta query, which retrieves the custom field names (meta keys), can be disabled to help improve editor performance on some sites.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'post_meta_query',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'remove_add_field_buttons_group',
                        'label'   => esc_html__( 'Remove "Add Field" Buttons', 'gf-tools' ),
                        'tooltip' => esc_html__( 'This can be useful for minimizing clutter or preventing users from adding fields that should never be used.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Section Field', 'gf-tools' ),
                                'name'  => 'remove_section',
                            ],
                            [
                                'label' => esc_html__( 'File Upload Field', 'gf-tools' ),
                                'name'  => 'remove_file_upload',
                            ],
                            [
                                'label' => esc_html__( 'All Post Fields', 'gf-tools' ),
                                'name'  => 'remove_post_section',
                            ],
                            [
                                'label' => esc_html__( 'All Pricing Fields', 'gf-tools' ),
                                'name'  => 'remove_pricing_section',
                            ],
                        ],
                    ],
                    $incl_quiz_answers_panel,
                    [
                        'type' => 'html',
                        'name' => 'form_display_section',
                        'args' => [
                            'html' => '<br><br><h2 id="form_display">Form Display</h2>'
                        ],
                    ],
                    [
                        'type'          => 'text',
                        'name'          => 'submit_button_classes',
                        'label'         => esc_html__( 'Add Additional Classes to the Submit Buttons - Separated by Spaces.', 'gf-tools' ),
                        'class'         => 'small',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'entries_section',
                        'args' => [
                            'html' => '<br><br><h2 id="entries">Entries</h2>'
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'name'    => 'disable_akismet',
                        'label'   => esc_html__( 'Disable All Spam Filtering on Form Entries for Logged-In Users', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Users that complete forms repeatedly may find themselves getting flagged as spam, especially admins during testing. You can disable it so that admins or all logged-in users never get flagged.', 'gf-tools' ),
                        'class'   => 'small',
                        'choices' => [
                            [
                                'label' => esc_html__( 'No', 'gf-tools' ),
                                'value' => '',
                            ],
                            [
                                'label' => esc_html__( 'Admins Only', 'gf-tools' ),
                                'value' => 'admins',
                            ],
                            [
                                'label' => esc_html__( 'All Logged-In Users', 'gf-tools' ),
                                'value' => 'everyone',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'block_links_group',
                        'label'   => esc_html__( 'Block Form Entries with Links', 'gf-tools' ),
                        'tooltip' => esc_html__( 'To help combat spam. Checks all text and textarea fields, and will not allow the form to be submitted if a link is present. Does not check URL fields.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'block_links',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'name'    => 'spam_filtering',
                        'label'   => esc_html__( 'Enable Enhanced Spam Filtering', 'gf-tools' ).' - <a href="'.add_query_arg( [ 'page' => 'gf-tools', 'tab' => 'spam_list' ], GFADVTOOLS_DASHBOARD_URL ).'">'.esc_html__( 'Manage Spam List', 'gf-tools' ).'</a>',
                        'tooltip' => esc_html__( 'Adds an option to whitelist or blacklist individual emails, entire email domains, and keywords. You can also choose to select a host website in which all of your websites will share one spam list. You must set an API Key for authentication for it to work remotely.', 'gf-tools' ),
                        'class'   => 'small',
                        'choices' => [
                            [
                                'label' => esc_html__( 'No', 'gf-tools' ),
                                'value' => '',
                            ],
                            [
                                'label' => esc_html__( 'Local — For this website only', 'gf-tools' ),
                                'value' => 'local',
                            ],
                            [
                                'label' => esc_html__( 'Host — This website should host the spam list for multiple sites', 'gf-tools' ),
                                'value' => 'host',
                            ],
                            [
                                'label' => esc_html__( 'Client — This website should use the host\'s spam list', 'gf-tools' ),
                                'value' => 'client',
                            ],
                        ],
                    ],
                    [
                        'type'       => 'text',
                        'input_type' => 'password',
                        'name'       => 'api_spam_key',
                        'label'      => esc_html__( 'API Key for Remote Spam List Access', 'gf-tools' ),
                        'tooltip'    => esc_html__( 'On your host website, choose "Host" in the "Enable Enhanced Spam Filtering" setting above. Then generate an API Key underneath it. Copy this key and enter it here.', 'gf-tools' ),
                        'class'      => 'large',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'generate_api_key',
                        'args' => [
                            'html' => '<a id="gfat_generate_api_key" href="#" class="button">'.esc_html__( 'Generate New API Key for Client Sites', 'gf-tools' ).'</a> <a id="gfat_api_key" href="#" class="button'.$api_old_class.$api_key_class.'" data-key="'.esc_html( $api_key ).'">Copy API Key</a> <div id="gfat_api_timestamp">'.wp_kses( $api_timestamp_display, [ 'span' => [ 'class' => [] ] ] ).'</div>'
                        ],
                    ],
                    [
                        'type'        => 'text',
                        'input_type'  => 'url',
                        'name'        => 'spam_list_url',
                        'label'       => esc_html__( 'Host Website URL - Only Necessary if Client is Selected Above', 'gf-tools' ),
                        'class'       => 'large',
                        'placeholder' => 'https://'
                    ],
                    $incl_delete_spam_list_button,
                    [
                        'type'    => 'checkbox',
                        'name'    => 'prevent_ip_group',
                        'label'   => esc_html__( 'Prevent User\'s IP from Being Saved', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'prevent_ip',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'exporting_section',
                        'args' => [
                            'html' => '<br><br><h2 id="exporting">Exporting</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'exclude_bom_group',
                        'label'   => esc_html__( 'Exclude BOM Character from Beginning of Entry Export Files', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Preferred when working with systems that might display BOM as a strange character or do not handle BOM correctly.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'exclude_bom',
                            ],
                        ],
                    ],
                    [
                        'type'          => 'text',
                        'name'          => 'export_form_filename',
                        'label'         => esc_html__( 'Export Form Filename', 'gf-tools' ),
                        /* translators: %s is for the display when exporting multiple forms. */
                        'tooltip'       => esc_html( sprintf( __( 'You can change the filename of the export form json file here. The date format uses PHP Date and Time Formatting and may be changed. A form id can be included by using the {form_id} tag; if more than one form is exported, this value will display "%s". Note: Do not include a file extension. The extension “.json” is added automatically.', 'gf-tools' ), 'multiple' ) ),
                        'class'         => 'large',
                        'default_value' => 'gravityforms-export-{Y-m-d}',
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'entries_export_roles_group',
                        'label'   => wp_kses( __( 'Which Addtional Roles (Besides Administrator) Can Export Entries on <code>[gfat_export_entries]</code> Shortcode', 'gf-tools' ), [ 'code' => [] ] ),
                        'tooltip' => esc_html__( 'The [gfat_export_entries] shortcode can be used to export entries on the front-end. You must also enable "Add to Export Entries Shortcode" in the forms\' Advanced Tools settings.', 'gf-tools' ),
                        'choices' => $role_choices,
                    ],
                    [
                        'type' => 'html',
                        'name' => 'confirmations_section',
                        'args' => [
                            'html' => '<br><br><h2 id="confirmations">Confirmations</h2><p>Use <code>{confirmation_signature}</code> merge tag to display this global signature on any confirmation.</p>'
                        ],
                    ],
                    [
                        'type'       => 'textarea',
                        'name'       => 'confirmations_signature',                    
                        'label'      => esc_html__( 'Confirmations Signature', 'gf-tools' ),
                        'tooltip'    => esc_html__( 'Set a generic signature to be added to the bottom of your confirmations.', 'gf-tools' ),
                        'use_editor' => true,
                        'class'      => 'large merge-tag-support mt-position-right',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'notifications_section',
                        'args' => [
                            'html' => '<br><br><h2 id="notifications">Notifications</h2><p>Use <code>{notification_signature}</code> merge tag to display this global signature on any notification.</p>'
                        ],
                    ],
                    [
                        'type'       => 'textarea',
                        'name'       => 'notifications_signature',                    
                        'label'      => esc_html__( 'Notifications Signature', 'gf-tools' ),
                        'tooltip'    => esc_html__( 'Set a generic signature to be added to the bottom of your notifications.', 'gf-tools' ),
                        'use_editor' => true,
                        'class'      => 'large merge-tag-support mt-position-right',
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'default_admin_notifications_group',
                        'label'   => esc_html__( 'Disable Default Admin Notifications Automatically Generated for New Forms', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'default_admin_notifications',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'format_to_group',
                        'label'   => esc_html__( 'Format the TO Email Address Properly to Improve Spam Score', 'gf-tools' ),
                        'tooltip' => esc_html__( 'By default, emails sent from Gravity Forms have a TO address formatted as [to] => samuel@example.com. This option will format the address as [to] => "samuel@example.com" ‹samuel@example.com›.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'format_to',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'merge_tags_section',
                        'args' => [
                            'html' => '<br><br><h2 id="merge_tags">Merge Tags</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'mt_form_field_labels_group',
                        'label'   => esc_html__( 'Include Form Field Labels on Merge Tags Dashboard', 'gf-tools' ),
                        'tooltip' => esc_html__( '{Field Label:2} vs {:2}', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'mt_form_field_labels',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'merge_tags_desc',
                        'args' => [
                            'html' => '<br><p>Use <code>{gfat:[modifier]}</code> merge tag to display your custom merge tags.</p>'
                        ],
                    ],
                    [
                        'type'    => 'text_plus',
                        'name'    => 'custom_merge_tags',
                        'label'   => esc_html__( 'Add Your Own Custom Merge Tags', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Modifier must be all lowercase without spaces. If Callback Function is selected, the callback is your function\'s name — ie. function callback_name( $form, $entry ) { return "your value"; }', 'gf-tools' ),
                        'args'    => [
                            [
                                'type'     => 'text',
                                'name'     => 'label',
                                'label'    => esc_html__( 'Merge Tag Label', 'gf-tools' )
                            ],
                            [
                                'type'       => 'text',
                                'input_type' => 'metakey',
                                'name'       => 'modifier',
                                'label'      => esc_html__( 'modifier', 'gf-tools' ),
                                'class'      => 'metakey'
                            ],
                            [
                                'type'       => 'text',
                                'name'       => 'value',
                                'label'      => esc_html__( 'Value or Callback', 'gf-tools' ),
                            ],
                            [
                                'type'    => 'select',
                                'name'    => 'return_type',
                                'label'   => esc_html__( 'Return', 'gf-tools' ),
                                'choices' => [
                                    [
                                        'label' => esc_html__( 'Value', 'gf-tools' ),
                                        'value' => 'text',
                                    ],
                                    [
                                        'label' => esc_html__( 'Callback Function', 'gf-tools' ),
                                        'value' => 'callback',
                                    ],
                                ],
                            ]
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'for_developers_section',
                        'args' => [
                            'html' => '<br><br><h2 id="developers">For Developers</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'view_object_links_group',
                        'label'   => esc_html__( 'Add Debug View of Form and Entry Objects to Toolbar', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Quickly view form and entry object data. Links on forms are in top toolbar of edit screen and in a meta box on the entry detail page. Also shows a quick view of the field IDS and their visibility.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'view_object_links',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'use_debug_log_group',
                        'label'   => esc_html__( 'Write All Gravity Forms Log Messages to System Debug Log', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Writes them to your debug.log file.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'use_debug_log',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'space_2',
                        'args' => [
                            'html' => '<br>'
                        ],
                    ],
                    [
                        'type'    => 'text_plus',
                        'name'    => 'custom_form_settings',
                        'label'   => esc_html__( 'Add Your Own Custom Fields to the Form Settings Page', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Values are stored in the form object meta. Meta Key must be all lowercase without spaces.', 'gf-tools' ),
                        'args'    => [
                            [
                                'type'     => 'text',
                                'name'     => 'label',
                                'label'    => esc_html__( 'Field Label', 'gf-tools' )
                            ],
                            [
                                'type'       => 'text',
                                'input_type' => 'metakey',
                                'name'       => 'meta_key',
                                'label'      => esc_html__( 'Meta Key', 'gf-tools' ),
                                'class'      => 'metakey'
                            ],
                            [
                                'type'    => 'select',
                                'name'    => 'field_type',
                                'label'   => esc_html__( 'Field Type', 'gf-tools' ),
                                'choices' => [
                                    [
                                        'label' => esc_html__( 'Text', 'gf-tools' ),
                                        'value' => 'text',
                                    ],
                                    [
                                        'label' => esc_html__( 'Number', 'gf-tools' ),
                                        'value' => 'number',
                                    ],
                                    [
                                        'label' => esc_html__( 'Date', 'gf-tools' ),
                                        'value' => 'date',
                                    ],
                                    [
                                        'label' => esc_html__( 'Checkbox', 'gf-tools' ),
                                        'value' => 'checkbox',
                                    ],
                                ],
                            ]
                        ],
                    ],
                ]
            ],
        ];
	} // End plugin_settings_fields()


	/**
	 * Configures the settings which should be rendered on the Form Settings > Advanced Tools tab.
     * 
	 *
	 * @return array
	 */
	public function form_settings_fields( $form ) {
        // dpr( $this->get_form_settings( $form ), null, true );

        // Timezone
        $timezone = (new GF_Advanced_Tools_Helpers)->convert_date_to_wp_timezone( null, 'T' );
        
        // Auto-delete choices
        $auto_delete_choices = [
            [
                'label' => esc_html__( 'No', 'gf-tools' ),
                'value' => '',
            ],
            [
                'label' => esc_html__( 'Delete all previous entries for logged-in users', 'gf-tools' ),
                'value' => 'all',
            ]
        ];
        if ( is_plugin_active( 'gravityformsquiz/quiz.php' ) ) {
            $auto_delete_choices[] = [
                'label' => esc_html__( 'Delete previous entries only if logged-in user passed quiz', 'gf-tools' ),
                'value' => 'quiz-only',
            ];

            // Include auto-fill question
            $incl_auto_fill_quiz_answers = [
                'type'    => 'checkbox',
                'name'    => 'auto_fill_quiz_answers_group',
                'label'   => esc_html__( 'Auto-Fill Quiz Answers for Admins Only', 'gf-tools' ),
                'tooltip' => esc_html__( 'Makes it easier for admins to test quiz forms without having to know the correct answers.', 'gf-tools' ),
                'choices' => [
                    [
                        'label' => esc_html__( 'Yes', 'gf-tools' ),
                        'name'  => 'auto_fill_quiz_answers',
                    ],
                ],
            ];
        } else {
            $incl_auto_fill_quiz_answers = [];
        }

        // The fields array
		$fields = [
            [
                'title'  => esc_html__( 'Advanced Form Settings', 'gf-tools' ),
                'fields' => [
                    [
                        'type' => 'html',
                        'name' => 'form_display_section',
                        'args' => [
                            'html' => '<h2 id="form-display">Form Display</h2>'
                        ],
                    ],
                    [
                        'type'    => 'datetimes',
                        'name'    => 'dates_group',
                        'label'   => esc_html__( 'Display the Form During Specified Dates and Times — Timezone: ', 'gf-tools' ).$timezone,
                        'tooltip' => esc_html__( 'You can leave dates blank if you only want to show the form after the start date or until the end date, or if you want to display the form only during those times every day. Leave the times blank if you want to start at 12:01am and end at 11:59pm.', 'gf-tools' ),
                        'args'    => [
                            'start_date' => [
                                'input_type' => 'date',
                                'name'       => 'start_date',
                            ],
                            'start_time' => [
                                'input_type' => 'time',
                                'name'       => 'start_time',
                            ],
                            'end_date' => [
                                'input_type' => 'date',
                                'name'       => 'end_date',
                            ],
                            'end_time' => [
                                'input_type' => 'time',
                                'name'       => 'end_time',
                            ],
                        ],
                    ],
                    [
                        'type'  => 'text',
                        'name'  => 'before_message',
                        'label' => esc_html__( 'Display a Message Instead of the Form BEFORE Start Date', 'gf-tools' ),
                        'class' => 'large',
                    ],
                    [
                        'type'  => 'text',
                        'name'  => 'after_message',
                        'label' => esc_html__( 'Display a Message Instead of the Form AFTER End Date', 'gf-tools' ),
                        'class' => 'large',
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'remove_submit_btn_group',
                        'label'   => esc_html__( 'Remove the Submit Button', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'remove_submit_btn',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'convert_submit_btn_group',
                        'label'   => esc_html__( 'Convert the Submit Input to a Button Element', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'convert_submit_btn',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'enable_review_page_group',
                        'label'   => esc_html__( 'Enable Review Page Prior to Form Submission', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'enable_review_page',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'textarea',
                        'use_editor' => true,
                        'name'    => 'review_page_content',
                        'label'   => esc_html__( 'Review Page Content - Must Enable Above', 'gf-tools' ),
                        'default_value' => '<strong>'.__( 'Please review your results before submitting.', 'gf-tools' ).'</strong><br>{all_fields}',
                    ],
                    [
                        'type' => 'html',
                        'name' => 'field_population_section',
                        'args' => [
                            'html' => '<br><br><h2 id="field-population">Field Population</h2>'
                        ],
                    ],
                    [
                        'type'    => 'text',
                        'name'    => 'remove_qs',
                        'label'   => esc_html__( 'Remove Query String Parameters from URL Without Refreshing - Enter Parameters Separated by Commas', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Useful if you are passing parameters to a form to populate fields, but then want to remove them from the URL so the user can\'t easily copy the URL or refresh the page to complete the form again if a parameter is required. Query string parameters look like this: http://yoursite.com/?page_id=1234&user_id=1 (where page_id and user_id are the parameters).', 'gf-tools' ),
                        'class'   => 'small',
                    ],
                    // [ // TODO: In a future version
                    //     'type'    => 'text',
                    //     'name'    => 'populate_entry_group',
                    //     'label'   => esc_html__( 'Populate the Form with the Entry Specified in URL Query String - Enter Query String Parameter', 'gf-tools' ),
                    //     'tooltip' => esc_html__( 'Example: http://yoursite.com/?entry_id=# - query string parameter would be "entry_id". Leave blank to disable.', 'gf-tools' ),
                    // ],
                    // [
                    //     'type'    => 'checkbox',
                    //     'name'    => 'update_entry_group',
                    //     'label'   => esc_html__( 'Update the Entry Specified in URL Query String - Must Specify Parameter Above', 'gf-tools' ),
                    //     'tooltip' => esc_html__( 'Alternatively, you would just be populating an entry in the form so you could quickly duplicate entries that are similar.', 'gf-tools' ),
                    //     'choices' => [
                    //         [
                    //             'label' => esc_html__( 'Yes', 'gf-tools' ),
                    //             'name'  => 'update_entry',
                    //         ],
                    //     ],
                    // ],
                    [
                        'type' => 'html',
                        'name' => 'field_population_section',
                        'args' => [
                            'html' => '<br><p>You can connect this form to a another post, page, or custom post type to populate meta data into your form. You can use merge tags <code>{connection:[meta_key]}</code> to display the data. If the meta data is on the page that the form is on, you can use <code>{embed_post:[meta_key]}</code> for post objects like ID, post_title, post_author, etc. or <code>{custom_field:[meta_key]}</code> for custom meta instead.</p>'
                        ],
                    ],
                    [
                        'type'    => 'text',
                        'name'    => 'associated_page_qs',
                        'label'   => esc_html__( 'Enter the Post ID or Query String Parameter Used to Connect To', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Example: http://yoursite.com/?page_id=# - query string parameter would be "page_id".', 'gf-tools' ),
                        'class'   => 'small',
                    ],
                    
                    [
                        'type' => 'html',
                        'name' => 'entries_section',
                        'args' => [
                            'html' => '<br><br><h2 id="entries">Entries</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'disable_spam_protection_group',
                        'label'   => esc_html__( 'Disable All Spam Filtering for This Form - Please Read Tooltip', 'gf-tools' ),
                        'tooltip' => esc_html__( 'There is an option in the plugin settings under Forms > Settings > Advanced Tools > Entries that allows you to disable all spam filtering for logged-in users site-wide.There is no need to enable this if you have that selected and this form is for logged-in users only. If this form is open to the public, then disabling spam filtering is NOT recommended except for testing purposes.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'disable_spam_protection',
                            ],
                        ],
                    ],
                    [
                        'type'    => 'select',
                        'name'    => 'auto_delete_previous_entries',
                        'label'   => esc_html__( 'Auto-Delete Previous Entries for Logged-In Users', 'gf-tools' ),
                        'tooltip' => esc_html__( 'When a logged-in user submits a new entry, you can have it automatically delete their previous entries, ensuring a single entry per user. Other options available with other add-ons.', 'gf-tools' ),
                        'choices' => $auto_delete_choices,
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'mark_resolved_group',
                        'label'   => esc_html__( 'Enable "Mark Resolved" Feature', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Adds ability to mark an entry as "resolved" or "in progress" so your team can keep track of if, when, and who resolved a support request on a contact form (or whatever else you want to use it for).', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'mark_resolved',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'exporting_section',
                        'args' => [
                            'html' => '<br><br><h2 id="exporting">Exporting</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'entries_export_group',
                        'label'   => esc_html__( 'Add to Export Entries Shortcode', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Adds this form to the [gfat_export_entries] shortcode, which can be used to export entries on the front-end.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'entries_export',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'confirmations_section',
                        'args' => [
                            'html' => '<br><br><h2 id="confirmations">Confirmations</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'disable_confirmations_anchor_group',
                        'label'   => esc_html__( 'Disable Confirmations Anchor', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Disable the confirmation anchor functionality that automatically scrolls the page to the confirmation text or validation message upon submission.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'disable_confirmations_anchor',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'notifications_section',
                        'args' => [
                            'html' => '<br><br><h2 id="notifications">Notifications</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'allow_text_fields_email_group',
                        'label'   => esc_html__( 'Allow Text and Select Fields in "Send To" > "Select a Field" Dropdown', 'gf-tools' ),
                        'tooltip' => esc_html__( 'A lot of times we setup email inputs as text fields or select fields, but by default Gravity Forms only allows you to choose email fields. This adds support for the others as well.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'allow_text_fields_email',
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'name' => 'admins_only_section',
                        'args' => [
                            'html' => '<br><br><h2 id="admins-only">Admins Only</h2>'
                        ],
                    ],
                    [
                        'type'    => 'checkbox',
                        'name'    => 'disable_required_fields_group',
                        'label'   => esc_html__( 'Disable Required Fields for Admins Only', 'gf-tools' ),
                        'tooltip' => esc_html__( 'Very useful for testing forms.', 'gf-tools' ),
                        'choices' => [
                            [
                                'label' => esc_html__( 'Yes', 'gf-tools' ),
                                'name'  => 'disable_required_fields',
                            ],
                        ],
                    ],
                    $incl_auto_fill_quiz_answers,
                    [
                        'type' => 'html',
                        'name' => 'for_developers_section',
                        'args' => [
                            'html' => '<br><br><h2 id="developers">For Developers</h2>'
                        ],
                    ],
                    [
                        'type'    => 'textarea',
                        'name'    => 'add_user_meta',
                        'label'   => esc_html__( 'Automatically Add User Meta to Entry Meta - Enter Meta Keys Separated by Spaces', 'gf-tools' ),
                        'tooltip' => esc_html__( 'You can add user meta to the entry meta directly if the user completing the form is logged in. You can then use {entry:[meta_key]} to display it on confirmations and notifications.', 'gf-tools' ),
                    ],
                ],
            ],
        ];

        // Return it
        return $fields;
	} // End form_settings_fields()


	/**
	 * Add HTML
	 *
	 * @param array $field
	 * @param boolean $echo
	 * @return void
	 */
	public function settings_html( $field, $echo = true ) {
        $html = $field[ 'args' ][ 'html' ];
		echo '</pre>'.wp_kses_post( $html ).'<pre>';
    } // End settings_html()


    /**
	 * Define the markup for the datetimes type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 */
    public function settings_datetimes( $field, $echo = true ) {
		$start_date_field = $field[ 'args' ][ 'start_date' ];
		$this->settings_text( $start_date_field );

        $start_time_field = $field[ 'args' ][ 'start_time' ];
		$this->settings_text( $start_time_field );

		$end_date_field = $field[ 'args' ][ 'end_date' ];
		$this->settings_text( $end_date_field );

        $end_time_field = $field[ 'args' ][ 'end_time' ];
		$this->settings_text( $end_time_field );
	} // End settings_datetimes()


    /**
	 * Define the markup for the text+ type field.
	 *
	 * @param array $fields The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 */
    public function settings_text_plus( $field, $echo = true ) {
        $fields = $field[ 'args' ];
        $values = $this->get_setting( $field[ 'name' ] );

        // Allowed HTML
        $allowed_html = [
            'a'   => [
                'href'        => [],
                'id'          => []
            ],
            'br' => [],
            'div' => [
                'class'       => [],
                'id'          => [],
                'data-row'    => []
            ],
            'input' => [
                'type'        => [],
                'name'        => [],
                'value'       => [],
                'id'          => [],
                'placeholder' => [],
                'class'       => [],
                'required'    => []
            ],
            'select' => [
                'name'        => [],
                'id'          => [],
                'class'       => []
            ],
            'option' => [
                'value'       => [],
                'selected'    => []
            ],
            'button' => [
                'type'        => [],
                'id'          => [],
                'class'       => [],
                'data-name'   => []
            ]
        ];

        // Count
        $incl_class = empty( $values ) ? ' empty' : '';

        // Start with the add new field link
        $results = '<button type="button" class="button add-new-field" data-name="'.$field[ 'name' ].'">Add New Field +</button><br><br>
        <div id="fields_container_'.$field[ 'name' ].'" class="fields_container'.$incl_class.'">';

            // Add the rows
            if ( !empty( $values ) ) {
                foreach ( $values as $index => $value ) {
                    $results .= $this->create_text_plus_row( $fields, $field[ 'name' ], $value, $index );
                }
            } else {
                $results .= $this->create_text_plus_row( $fields, $field[ 'name' ], [], 0 );
            }
            
        // End container
        $results .= '</div>';

        // Echo
        echo wp_kses( $results, $allowed_html );
	} // End settings_text_plus()


    /**
     * Create a row for text+ fields
     *
     * @param array $fields
     * @param string $field_name
     * @param array $values
     * @param int $index
     * @return string
     */
    public function create_text_plus_row( $fields, $field_name, $values, $index ) {
        // Start row container
        $results = '<div class="text-plus-row" data-row="'.$index.'">';
            
            // Iter the fields
            foreach ( $fields as $field ) {
                $type = $field[ 'type' ];
                $input_type = isset( $field[ 'input_type' ] ) ? $field[ 'input_type' ] : false;
                $name = $field[ 'name' ];
                $label = $field[ 'label' ];
                $class = isset( $field[ 'class' ] ) ? ' class="'.$field[ 'class' ].'"' : '';
                
                // The value
                $field_value = '';
                if ( isset( $values[ $name ] ) ) {
                    if ( $type == 'number' ) {
                        $field_value = absint( $values[ $name ] );
                    } elseif ( $input_type == 'metakey' ) {
                        $field_value = sanitize_key( $values[ $name ] );
                    } else {
                        $field_value = sanitize_text_field( $values[ $name ] );
                    }
                }
                    
                // The input
                switch ( $type ) {
                    case 'select':
                        $results .= '<select name="_gform_setting_'.$field_name.'['.$index.']['.$name.']"'.$class.'>';

                        foreach ( $field[ 'choices' ] as $choice ) {
                            $is_selected = ( $field_value == $choice[ 'value' ] ) ? ' selected' : '';
                            $results .= '<option value="'.$choice[ 'value' ].'"'.$is_selected.'>'.$choice[ 'label' ].'</option>';
                        }

                        $results .= '</select>';
                        break;

                    default:
                        $results .= '<input type="'.$type.'" name="_gform_setting_'.$field_name.'['.$index.']['.$name.']" value="'.$field_value.'"'.$class.' placeholder="'.$label.'" required="required"/>';
                        break;
                }
            }

            // Remove button
            $results .= '<div><button   tton type="button" class="button remove-row">(-) Delete</button></div>';

        // End container
        $results .= '</div>';

        // Return
        return $results;
    } // End create_text_plus_row()


	// # FIELD VALIDATION -----------------------------------------------------------------------------------------------


	/**
	 * The feedback callback for the 'mytextbox' setting on the plugin settings page and the 'mytext' setting on the form settings page.
	 *
	 * @param string $value The setting value.
	 *
	 * @return bool
	 */
	public function is_valid_menu_position( $value ) {
		return $value >= 1;
	} // End is_valid_menu_position()

}