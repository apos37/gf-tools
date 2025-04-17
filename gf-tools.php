<?php
/**
 * Plugin Name:         Advanced Tools for Gravity Forms
 * Plugin URI:          https://github.com/apos37/gf-tools
 * Description:         Unlock advanced tools to supercharge your Gravity Forms experience with enhanced features and streamlined management.
 * Version:             1.0.6
 * Requires at least:   5.9
 * Tested up to:        6.8
 * Requires PHP:        7.4
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Support URI:         https://discord.gg/3HnzNEJVnR
 * Text Domain:         gf-tools
 * License:             GPLv2 or later
 * License URI:         http://www.gnu.org/licenses/gpl-2.0.txt
 * Created on:          August 8, 2024
 */


/**
 * Exit if accessed directly.
 */
if ( !defined( 'ABSPATH' ) ) exit;


/**
 * Defines
 */
$plugin_data = get_file_data( __FILE__, [
    'name'         => 'Plugin Name',
    'version'      => 'Version',
    'textdomain'   => 'Text Domain',
    'support_uri'  => 'Support URI',
] );


/**
 * Defines
 */
define( 'GFADVTOOLS_VERSION', $plugin_data[ 'version' ] );
define( 'GFADVTOOLS_NAME', $plugin_data[ 'name' ] );
define( 'GFADVTOOLS_TEXTDOMAIN', $plugin_data[ 'textdomain' ] );
define( 'GFADVTOOLS_ADMIN_INCLUDES_URL', trailingslashit( ABSPATH.str_replace( site_url(), '', admin_url( 'includes/' ) ) ) );  // /abspath/.../public_html/wp-admin/includes/
define( 'GFADVTOOLS_PLUGIN_ROOT', plugin_dir_path( __FILE__ ) );                                                                // /home/.../public_html/wp-content/plugins/gf-tools/
define( 'GFADVTOOLS_PLUGIN_DIR', plugins_url( '/'.GFADVTOOLS_TEXTDOMAIN.'/' ) );                                                // https://domain.com/wp-content/plugins/gf-tools/
define( 'GFADVTOOLS_SETTINGS_URL', admin_url( 'admin.php?page=gf_settings&subview='.GFADVTOOLS_TEXTDOMAIN ) );                  // https://domain.com/wp-admin/admin.php?page=gf_settings&subview=gf-tools/
define( 'GFADVTOOLS_DASHBOARD_URL', admin_url( 'admin.php?page='.GFADVTOOLS_TEXTDOMAIN ) );                                     // https://domain.com/wp-admin/admin.php?page=gf-tools
define( 'GFADVTOOLS_DISCORD_SUPPORT_URL', $plugin_data[ 'support_uri' ] );


/**
 * Dashboard Tab URL
 *
 * @param string $tab
 * @return string
 */
function gfadvtools_get_plugin_page_tab( $tab ) {
    return add_query_arg( 'tab', $tab, GFADVTOOLS_DASHBOARD_URL );
} // End gfadvtools_get_plugin_page_tab()


/**
 * Form Settings URL
 *
 * @param int $form_id
 * @return string
 */
function gfadvtools_get_form_settings_url( $form_id ) {
    return add_query_arg( [
        'page'    => 'gf_edit_forms',
        'view'    => 'settings',
        'subview' => 'gf-tools',
        'id'      => $form_id
    ], admin_url( 'admin.php' ) );
} // End gfadvtools_get_form_settings_url()


/**
 * Pre-load files that need earlier execution
 */
// Helpers
require_once GFADVTOOLS_PLUGIN_ROOT.'includes/class-helpers.php';

// Import/Export
require_once GFADVTOOLS_PLUGIN_ROOT.'includes/class-import-export.php';
new GF_Advanced_Tools_Import_Export();


/**
 * Load the Bootstrap
 */
add_action( 'gform_loaded', [ 'GF_Advanced_Tools_Bootstrap', 'load' ], 5 );


/**
 * GF_Advanced_Tools_Bootstrap Class
 */
class GF_Advanced_Tools_Bootstrap {

    // Load
    public static function load() {
        // print_r( 'load bootstrap bak' );

        // Make sure the framework exists
        if ( !method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        // Load main plugin class.
        require_once GFADVTOOLS_PLUGIN_ROOT.'includes/class-gf-tools.php';

        // Register the addon
        GFAddOn::register( 'GF_Advanced_Tools' );
    }
}

/**
 * Filter plugin action links
 */
add_filter( 'plugin_row_meta', 'gfadvtools_plugin_row_meta' , 10, 2 );


/**
 * Add links to our website and Discord support
 *
 * @param array $links
 * @return array
 */
function gfadvtools_plugin_row_meta( $links, $file ) {
    // Only apply to this plugin
    if ( GFADVTOOLS_TEXTDOMAIN.'/'.GFADVTOOLS_TEXTDOMAIN.'.php' == $file ) {

        // Add the link
        $row_meta = [
            // 'docs'    => '<a href="'.esc_url( 'https://apos37.com/wordpress-advanced-tools-for-gravity-forms/' ).'" target="_blank" aria-label="'.esc_attr__( 'Plugin Website Link', 'gf-tools' ).'">'.esc_html__( 'Website', 'gf-tools' ).'</a>',
            'discord' => '<a href="'.esc_url( 'https://discord.gg/3HnzNEJVnR' ).'" target="_blank" aria-label="'.esc_attr__( 'Plugin Support on Discord', 'gf-tools' ).'">'.esc_html__( 'Discord Support', 'gf-tools' ).'</a>'
        ];

        // Require Gravity Forms Notice
        if ( ! is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
            echo '<div class="gravity-forms-required-notice" style="margin: 5px 0 15px; border-left-color: #d63638 !important; background: #FCF9E8; border: 1px solid #c3c4c7; border-left-width: 4px; box-shadow: 0 1px 1px rgba(0, 0, 0, .04); padding: 10px 12px;">';
            /* translators: 1: Plugin name, 2: Gravity Forms link */
            printf( __( 'This plugin requires the %s plugin to be activated!', 'gf-tools' ),
                '<a href="https://www.gravityforms.com/" target="_blank">Gravity Forms</a>'
            );
            echo '</div>';
        }

        // Merge the links
        return array_merge( $links, $row_meta );
    }

    // Return the links
    return (array) $links;
} // End plugin_row_meta()


/**
 * Handle clean-up on uninstall
 */
add_action( 'gform_uninstalling', 'gfadvtools_cleanup' );
register_uninstall_hook( __FILE__, 'gfadvtools_cleanup' );


/**
 * Function for handling cleanup
 *
 * @return void
 */
function gfadvtools_cleanup() {
    delete_option( 'gfat_export_form_filename' );
    delete_option( 'gfat_export_exclude_bom' );
    delete_option( 'gfat_spam_filtering' );
    delete_option( 'gfat_last_entry_id' );
    delete_option( 'gfat_recent_entry_count' );
    delete_option( 'gfadvtools_per_page' );
} // End gfadvtools_cleanup()