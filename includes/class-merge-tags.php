<?php
/**
 * Merge tags class
 */

// Exit if accessed directly.
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class GF_Advanced_Tools_Merge_Tags {

    /**
     * Store the settings here for the rest of the stuff
     *
     * @var array
     */
    public $plugin_settings;
    public $form_settings;


    /**
     * Merge tags
     *
     * @var array
     */
    public $merge_tags;


    /**
	 * Constructor
	 */
	public function __construct( $plugin_settings, $form_settings ) {

        // Update the settings
        $this->plugin_settings = isset( $plugin_settings ) ? $plugin_settings : [];
        $this->form_settings = isset( $form_settings ) ? $form_settings : [];

        // Prepare the merge tags once
        $this->prepare();

        // Register the custom merge tags
        add_filter( 'gform_custom_merge_tags', [ $this, 'register' ], 10, 4 );

        // Replace the values of the merge tags. Request all args to match GF's filter signature.
        add_filter( 'gform_replace_merge_tags', [ $this, 'replace' ], 10, 7 );

	} // End __construct()


    /**
     * Prepare the merge tags
     *
     * @return void
     */
    public function prepare() {
        // Defaults
        $merge_tags = [
            [ 
                'label'       => __( 'Admin Email', 'gf-tools' ), 
                'name'        => 'admin_email',
                'value'       => 'admin_email',
                'field_type'  => null,
                'return_type' => 'text'
            ],
            [ 
                'label'       => __( 'Site Name', 'gf-tools' ), 
                'name'        => 'site_name', 
                'value'       => get_bloginfo( 'name' ),
                'field_type'  => null,
                'return_type' => 'text'
            ],
            [ 
                'label'       => __( 'Domain Name', 'gf-tools' ), 
                'name'        => 'domain_name', 
                'value'       => get_bloginfo( 'url' ),
                'field_type'  => null,
                'return_type' => 'text'
            ],
            [ 
                'label'       => __( 'Reset Password URL', 'gf-tools' ), 
                'name'        => 'reset_password_url', 
                'value'       => wp_lostpassword_url(),
                'field_type'  => null,
                'return_type' => 'text'
            ],
            [ 
                'label'       => __( 'Confirmation Signature', 'gf-tools' ), 
                'name'        => 'confirmations_signature',
                'value'       => 'confirmations_signature',
                'field_type'  => 'textarea',
                'return_type' => 'plugin_setting'
            ],
            [ 
                'label'       => __( 'Notification Signature', 'gf-tools' ), 
                'name'        => 'notifications_signature', 
                'value'       => 'notifications_signature',
                'field_type'  => 'textarea',
                'return_type' => 'plugin_setting'
            ]
        ];

        // Get custom tags
        $custom_merge_tags = isset( $this->plugin_settings[ 'custom_merge_tags' ] ) ? $this->plugin_settings[ 'custom_merge_tags' ] : false;
        if ( !empty( $custom_merge_tags ) ) {
            foreach ( $custom_merge_tags as $merge_tag ) {
                $label = sanitize_text_field( $merge_tag[ 'label' ] );
                $modifier = sanitize_key( $merge_tag[ 'modifier' ] );
                $value = sanitize_text_field( $merge_tag[ 'value' ] );
                if ( $label && $modifier && $value ) {
                    $merge_tags[] = [ 
                        'label' => $label, 
                        'name'  => 'gfat:'.$modifier,
                        'value' => $value,
                        'return_type'  => sanitize_key( $merge_tag[ 'return_type' ] )
                    ];
                }
            }
        }

        // Connection tags
        $merge_tags[] = [ 
            'label' => 'Connected Page Meta', 
            'name'  => 'connection:[meta_key]',
        ];

        // Prepare
        $this->merge_tags = $merge_tags;
    } // End prepare()


    /**
     * Register the merge tags
     *
     * @param array $merge_tags
     * @param int $form_id
     * @param [type] $fields
     * @param [type] $element_id
     * @return array
     */
    public function register( $merge_tags, $form = null, $fields = null, $element_id = null ) {

        foreach ( $this->merge_tags as $merge_tag ) {
            $merge_tags[] = [
                'label' => $merge_tag[ 'label' ],
                'tag' => '{'.$merge_tag[ 'name' ].'}'
            ];
        }
        return $merge_tags;
    } // End register()


    /**
     * Replace the merge tags
     *
     * @param string $text
     * @param array|false $form
     * @param array|false $entry
     * @param boolean $url_encode
     * @param boolean $esc_html
     * @param boolean $nl2br
     * @param string $format
     * @return string
     */
    public function replace( $text, $form = null, $entry = null, $url_encode = false, $esc_html = true, $nl2br = true, $format = '' ) {
        // Check form settings for an associated/connected post
        $post_id = false;
        $query_string = false;
        $form_settings = (new GF_Advanced_Tools)->get_form_settings( $form );
        if ( isset( $form_settings[ 'associated_page_qs' ] ) && $form_settings[ 'associated_page_qs' ] != '' ) {
            $associated_page_qs = $form_settings[ 'associated_page_qs' ];
            if ( is_numeric( $associated_page_qs ) ) {
                $post_id = absint( $associated_page_qs );
            } else {
                $query_string = sanitize_text_field( $associated_page_qs );
            }
        }

        // Store the post here if we pull it
        $post = false;
        $post_object_keys = false;

        // Ensure merge tags have a sensible default return type
        foreach ( $this->merge_tags as $k => $mt ) {
            if ( !isset( $this->merge_tags[ $k ][ 'return_type' ] ) || $this->merge_tags[ $k ][ 'return_type' ] === '' ) {
                $this->merge_tags[ $k ][ 'return_type' ] = 'text';
            }
        }

        // Build replacements in bulk for normal tags (non-connection)
        $search = [];
        $replace = [];
        foreach ( $this->merge_tags as $merge_tag ) {
            // Skip connection-style tags here; they are handled separately below
            if ( isset( $merge_tag['name'] ) && strpos( $merge_tag['name'], 'connection:' ) === 0 ) {
                continue;
            }

            $merge_tag_code = '{' . $merge_tag['name'] . '}';
            $value = '';

            switch ( $merge_tag['return_type'] ) {
                case 'plugin_setting':
                    $setting = $merge_tag['value'];
                    if ( isset( $this->plugin_settings[ $setting ] ) ) {
                        $field_type = isset( $merge_tag['field_type'] ) ? $merge_tag['field_type'] : '';
                        $value = ( $field_type == 'textarea' ) ? sanitize_textarea_field( $this->plugin_settings[ $setting ] ) : sanitize_text_field( $this->plugin_settings[ $setting ] );
                    }
                    break;
                case 'form_setting':
                    $setting = $merge_tag['value'];
                    if ( isset( $this->form_settings[ $setting ] ) ) {
                        $field_type = isset( $merge_tag['field_type'] ) ? $merge_tag['field_type'] : '';
                        $value = ( $field_type == 'textarea' ) ? sanitize_textarea_field( $this->form_settings[ $setting ] ) : sanitize_text_field( $this->form_settings[ $setting ] );
                    }
                    break;
                case 'callback':
                    $callback = $merge_tag['value'];
                    if ( is_callable( $callback ) ) {
                        try {
                            $raw = call_user_func( $callback, $form, $entry );
                            $value = wp_kses_post( $raw );
                        } catch ( Exception $e ) {
                            // swallow exception to avoid breaking replacements
                        }
                    }
                    break;
                case 'text':
                default:
                    $value = isset( $merge_tag['value'] ) ? $merge_tag['value'] : '';
                    break;
            }

            $search[] = $merge_tag_code;
            $replace[] = $value;
        }

        if ( !empty( $search ) ) {
            $text = str_replace( $search, $replace, $text );
        }

        // Connections: replace {connection:meta_key} tags if we have a connected post
        if ( preg_match_all( '/\{connection:([a-zA-Z0-9_]+)\}/', $text, $matches ) ) {
            // Only continue if the form has a connection set
            if ( $post_id || $query_string ) {
                // If query string, let's attempt to get the post id from the query string
                if ( !$post_id && $query_string ) {
                    if ( isset( $_GET[ $query_string ] ) && absint( $_GET[ $query_string ] ) > 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $post_id = absint( $_GET[ $query_string ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    }
                }

                // Validate we have a post id
                if ( $post_id ) {
                    if ( !$post ) {
                        $post = get_post( $post_id );
                    }

                    if ( $post ) {
                        if ( !$post_object_keys ) {
                            $post_object_keys = array_keys( get_object_vars( $post ) );
                        }

                        foreach ( $matches[1] as $meta_key ) {
                            $meta_value = '';
                            if ( in_array( $meta_key, $post_object_keys ) ) {
                                $meta_value = isset( $post->$meta_key ) ? sanitize_text_field( $post->$meta_key ) : '';
                            } else {
                                $get_value = get_post_meta( $post_id, $meta_key, true );
                                if ( $get_value !== '' && $get_value !== false ) {
                                    $meta_value = sanitize_text_field( $get_value );
                                } else {
                                    $meta_value = '[Meta Key "' . $meta_key . '" Does Not Exist]';
                                }
                            }

                            $text = str_replace( '{connection:' . $meta_key . '}', $meta_value, $text );
                        }
                    }
                }
            }
        }
        return $text;
    } // End replace()
}