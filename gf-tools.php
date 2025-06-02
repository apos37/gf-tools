<?php
/**
 * Plugin Name:         Advanced Tools for Gravity Forms
 * Plugin URI:          https://github.com/apos37/gf-tools
 * Description:         Unlock advanced tools to supercharge your Gravity Forms experience with enhanced features and streamlined management.
 * Version:             1.1.0
 * Requires at least:   5.9
 * Tested up to:        6.8
 * Requires PHP:        7.4
 * Author:              PluginRx
 * Author URI:          https://pluginrx.com/
 * Discord URI:         https://discord.gg/3HnzNEJVnR
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
    'author_uri'   => 'Author URI',
    'discord_uri'  => 'Discord URI',
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
define( 'GFADVTOOLS_AUTHOR_URL', $plugin_data[ 'author_uri' ] );
define( 'GFADVTOOLS_GUIDE_URL', GFADVTOOLS_AUTHOR_URL . 'guide/plugin/' . GFADVTOOLS_TEXTDOMAIN . '/' );
define( 'GFADVTOOLS_DOCS_URL', GFADVTOOLS_AUTHOR_URL . 'docs/plugin/' . GFADVTOOLS_TEXTDOMAIN . '/' );
define( 'GFADVTOOLS_SUPPORT_URL', GFADVTOOLS_AUTHOR_URL . 'support/plugin/' . GFADVTOOLS_TEXTDOMAIN . '/' );
define( 'GFADVTOOLS_DISCORD_URL', $plugin_data[ 'discord_uri' ] );


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
    $text_domain = GFADVTOOLS_TEXTDOMAIN;
    if ( $text_domain . '/' . $text_domain . '.php' == $file ) {

        $guide_url = GFADVTOOLS_GUIDE_URL;
        $docs_url = GFADVTOOLS_DOCS_URL;
        $support_url = GFADVTOOLS_SUPPORT_URL;
        $plugin_name = GFADVTOOLS_NAME;

        $our_links = [
            'guide' => [
                // translators: Link label for the plugin's user-facing guide.
                'label' => __( 'How-To Guide', 'gf-tools' ),
                'url'   => $guide_url
            ],
            'docs' => [
                // translators: Link label for the plugin's developer documentation.
                'label' => __( 'Developer Docs', 'gf-tools' ),
                'url'   => $docs_url
            ],
            'support' => [
                // translators: Link label for the plugin's support page.
                'label' => __( 'Support', 'gf-tools' ),
                'url'   => $support_url
            ],
        ];

        $row_meta = [];
        foreach ( $our_links as $key => $link ) {
            // translators: %1$s is the link label, %2$s is the plugin name.
            $aria_label = sprintf( __( '%1$s for %2$s', 'gf-tools' ), $link[ 'label' ], $plugin_name );
            $row_meta[ $key ] = '<a href="' . esc_url( $link[ 'url' ] ) . '" target="_blank" aria-label="' . esc_attr( $aria_label ) . '">' . esc_html( $link[ 'label' ] ) . '</a>';
        }

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