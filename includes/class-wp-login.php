<?php
/**
 * Our wp-login.php handler.
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_WpLogin {

    /**
     * Form IDs
     *
     * @var array
     */
    public $form_ids = [];


    /**
     * Page IDs
     *
     * @var array
     */
    public $page_ids = [];


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings ) {

        // Load form and page IDs from options
        $options = [ 'registration', 'login', 'password_reset' ];
        foreach ( $options as $option ) {
            $this->form_ids[ $option ] = isset( $plugin_settings[ $option . '_form' ] ) ? absint( $plugin_settings[ $option . '_form' ] ) : 0;
            $this->page_ids[ $option ] = isset( $plugin_settings[ $option . '_page' ] ) ? absint( $plugin_settings[ $option . '_page' ] ) : 0;
        }

        // Registration
        add_action( 'update_option_users_can_register', [ $this, 'sync_registration_access' ], 10, 2 );
        if ( $this->is_enabled( 'registration' ) && get_option( 'users_can_register' ) ) {
            add_filter( 'register_url', [ $this, 'filter_register_url' ] );
        }

        // Login
        if ( $this->is_enabled( 'login' ) ) {
            add_filter( 'login_url', [ $this, 'filter_login_url' ], 10, 3 );
            add_filter( 'logout_redirect', [ $this, 'logout_redirect' ], 10, 3 );
        }

        // The actual wp-login.php
        add_action( 'login_init', [ $this, 'maybe_redirect_wp_login' ] );

	} // End __construct()


    /**
     * The custom page URL
     *
     * @param string $type
     * @return string|false
     */
    public function page_url( $type ) {
        $id = isset( $this->page_ids[ $type ] ) ? $this->page_ids[ $type ] : 0;

        if ( $id && get_post_status( $id ) === 'publish' ) {
            return get_permalink( $id );
        }

        return false;
    } // End page_url()


    /**
     * Is a form enabled? Do we have a form and page identified?
     *
     * @param string $type
     * @return boolean
     */
    public function is_enabled( $type ) {
        return isset( $this->form_ids[ $type ] ) && isset( $this->page_ids[ $type ] );
    } // End is_enabled()


    /**
     * Sync registration page and form visibility with the users_can_register option.
     *
     * @param int $old_value
     * @param int $new_value
     * @return void
     */
    public function sync_registration_access( $old_value, $new_value ) {
        if ( !$this->is_enabled( 'registration' ) ) {
            return;
        }

        $enabled = (int) $new_value === 1;

        // Update page status
        if ( $enabled ) {
            wp_update_post( [
                'ID'          => $this->page_ids[ 'registration' ],
                'post_status' => 'publish',
            ] );
        } else {
            wp_update_post( [
                'ID'          => $this->page_ids[ 'registration' ],
                'post_status' => 'draft',
            ] );
        }

        // Update form status
        GFAPI::update_form_property( $this->form_ids[ 'registration' ], 'is_active', $enabled );
    } // End sync_registration_access()

    
    /**
     * Filter register URL
     *
     * @param string $register_url
     * @return string
     */
    public function filter_register_url( $register_url ) {
        return $this->page_url( 'registration' ) ?: $register_url;
    } // End filter_register_url()


    /**
     * Filter login URL
     *
     * @param string $login_url
     * @param string $redirect
     * @param bool $force_reauth
     * @return string
     */
    public function filter_login_url( $login_url, $redirect, $force_reauth ) {
        $url = $this->page_url( 'login' );
        if ( $url && $redirect ) {
            $url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
        }
        return $url ?: $login_url;
    } // End filter_login_url()


    /**
     * Custom logout redirect
     *
     * @param string $redirect_to
     * @param string $requested_redirect_to
     * @param WP_User $user
     * @return string
     */
    public function logout_redirect( $redirect_to, $requested_redirect_to, $user ) {
        return $this->page_url( 'login' ) ?: $redirect_to;
    } // End logout_redirect()


    /**
     * Redirect wp-login.php to mapped login page unless it's an allowed action.
     *
     * @return void
     */
    public function maybe_redirect_wp_login() {
        if ( is_user_logged_in() ) {
            return;
        }

        // Get the action
        $action = isset( $_GET[ 'action' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'action' ] ) ) : '';

        // Redirect register action to custom registration page
        if ( $this->is_enabled( 'registration' ) && $action === 'register' ) {
            $url = $this->page_url( 'registration' );
            if ( $url ) {
                wp_safe_redirect( $url );
                exit;
            }
            return;
        }

        // Redirect lostpassword action to custom reset page
        if ( $this->is_enabled( 'password_reset' ) && $action === 'lostpassword' ) {
            $url = $this->page_url( 'password_reset' );
            if ( $url ) {
                wp_safe_redirect( $url );
                exit;
            }
            return;
        }

        // Redirect reset password actions (rp, resetpass) to custom reset page with key and login query args
        if ( $this->is_enabled( 'password_reset' ) && in_array( $action, [ 'rp', 'resetpass' ], true ) ) {
            $url = $this->page_url( 'password_reset' );
            if ( $url ) {
                $key   = isset( $_GET[ 'key' ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'key' ] ) ) : '';
                $login = isset( $_GET[ 'login' ] ) ? sanitize_user( wp_unslash( $_GET[ 'login' ] ) ) : '';
                $redirect_url = add_query_arg( [
                    'action' => $action,
                    'key'    => $key,
                    'login'  => $login,
                ], $url );
                wp_safe_redirect( $redirect_url );
                exit;
            }
            return;
        }

        // Allow logout action without redirect
        if ( $action === 'logout' ) {
            return;
        }

        // Only redirect GET requests to custom login page for all other cases
        if ( $this->is_enabled( 'login' ) && $_SERVER[ 'REQUEST_METHOD' ] === 'GET' ) {
            $url = $this->page_url( 'login' );
            if ( $url ) {
                wp_safe_redirect( $url );
                exit;
            }
        }
    } // End maybe_redirect_wp_login()

}