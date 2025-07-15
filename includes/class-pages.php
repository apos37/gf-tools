<?php
/**
 * Pages
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Pages {

    /**
     * Store the page IDs and labels for the form pages.
     *
     * @var array
     */
    public $page_ids = [];


    /**
     * Store page mappings (slug => translated label).
     *
     * @var array
     */
    protected $page_mappings;


    /**
     * Constructor
     */
    public function __construct( $plugin_settings ) {

        // Only run in admin area
        if ( !is_admin() ) {
            return;
        }

        // Define page slugs and their translated labels here (use __() now)
        $this->page_mappings = [
            'contact'         => __( 'Contact Form', 'gf-tools' ),
            'registration'    => __( 'Registration Form', 'gf-tools' ),
            'account'         => __( 'Account Form', 'gf-tools' ),
            'password_change' => __( 'Password Change Form', 'gf-tools' ),
            'login'           => __( 'Login Form', 'gf-tools' ),
            'password_reset'  => __( 'Password Reset Form', 'gf-tools' ),
        ];

        // Load page IDs from plugin settings
        foreach ( $this->page_mappings as $slug => $label ) {
            $page_id = isset( $plugin_settings[ $slug . '_page' ] ) ? absint( $plugin_settings[ $slug . '_page' ] ) : 0;
            if ( $page_id > 0 ) {
                $this->page_ids[ $slug ] = $page_id;
            }
        }

        // Add the post states
        add_filter( 'display_post_states', [ $this, 'add_post_states' ], 10, 2 );

        // Prevent deletion of mapped pages
        add_filter( 'map_meta_cap', [ $this, 'lock_page' ], 10, 4 );

    } // End __construct()


    /**
     * Add post states for mapped pages, inserted after draft/pending/private.
     *
     * @param array   $post_states
     * @param WP_Post $post
     * @return array
     */
    public function add_post_states( $post_states, $post ) {
        $custom_states = [];

        foreach ( $this->page_ids as $slug => $page_id ) {
            if ( $post->ID === $page_id && isset( $this->page_mappings[ $slug ] ) ) {
                $label = $this->page_mappings[ $slug ];
                // translators: 1: Page label, 2: Lock icon title.
                $custom_states[ $slug ] = sprintf(
                    '%1$s <span class="dashicons dashicons-lock" title="%2$s"></span>',
                    esc_html( $label ),
                    esc_attr__( 'Locked by ', 'gf-tools' ) . esc_html( GFADVTOOLS_NAME )
                );
            }
        }

        if ( empty( $custom_states ) ) {
            return $post_states;
        }

        $known_states = [ 'Draft', 'Pending', 'Private' ];
        $known_states_lower = array_map( 'strtolower', $known_states );

        $new_states = [];
        $inserted = false;

        foreach ( $post_states as $key => $label ) {
            if ( in_array( strtolower( $label ), $known_states_lower, true ) ) {
                $new_states[ $key ] = $label;
                continue;
            }

            if ( ! $inserted ) {
                // Insert custom states here preserving keys
                $new_states = array_merge( $new_states, $custom_states );
                $inserted = true;
            }

            $new_states[ $key ] = $label;
        }

        if ( ! $inserted ) {
            $new_states = array_merge( $custom_states, $post_states );
        }

        return $new_states;
    } // End add_post_states()


    /**
     * Prevent deletion or changing status of mapped published pages by modifying meta capabilities.
     *
     * @param array $caps
     * @param string $cap
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function lock_page( $caps, $cap, $user_id, $args ) {
         if ( in_array( $cap, [ 'delete_post', 'delete_page' ], true ) ) {
            $post_id = isset( $args[0] ) ? absint( $args[0] ) : 0;

            if ( in_array( $post_id, $this->page_ids, true ) ) {
                $caps[] = 'do_not_allow';
            }
        }
        return $caps;
    } // End lock_page()

}