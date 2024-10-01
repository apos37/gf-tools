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
        add_filter( 'gform_custom_merge_tags', [ $this, 'register' ] );

        // Replace the values of the merge tags
        add_filter( 'gform_replace_merge_tags', [ $this, 'replace' ], 10, 3 );

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
    public function register( $merge_tags ) {
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
    public function replace( $text, $form, $entry ) {
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

        // Iter the tags
        foreach ( $this->merge_tags as $merge_tag ) {
            
            // Normal merge tags
            $merge_tag_code = '{'.$merge_tag[ 'name' ].'}';
            if ( strpos( $text, $merge_tag_code ) !== false ) {
                
                // Straight text value
                if ( $merge_tag[ 'return_type' ] == 'text' ) {
                    $value = $merge_tag[ 'value' ];
                    $text = str_replace( $merge_tag_code, $value, $text );

                // Plugin setting
                } elseif ( $merge_tag[ 'return_type' ] == 'plugin_setting' ) {
                    $setting = $merge_tag[ 'value' ];
                    if ( isset( $this->plugin_settings[ $setting ] ) ) {
                        $field_type = $merge_tag[ 'field_type' ];
                        if ( $field_type == 'textarea' ) {
                            $value = sanitize_textarea_field( $this->plugin_settings[ $setting ] );
                        } else {
                            $value = sanitize_text_field( $this->plugin_settings[ $setting ] );
                        }
                        $text = str_replace( $merge_tag_code, $value, $text );
                    }

                // Form setting
                } elseif ( $merge_tag[ 'return_type' ] == 'form_setting' ) {
                    $setting = $merge_tag[ 'value' ];
                    if ( isset( $this->form_settings[ $setting ] ) ) {
                        $field_type = $merge_tag[ 'field_type' ];
                        if ( $field_type == 'textarea' ) {
                            $value = sanitize_textarea_field( $this->form_settings[ $setting ] );
                        } else {
                            $value = sanitize_text_field( $this->form_settings[ $setting ] );
                        }
                        $text = str_replace( $merge_tag_code, $value, $text );
                    }

                // Callback
                } elseif ( $merge_tag[ 'return_type' ] == 'callback' ) {
                    $callback = $merge_tag[ 'value' ];
                    if ( function_exists( $callback ) ) {
                        $value = wp_kses_post( $callback( $form, $entry ) );
                        $text = str_replace( $merge_tag_code, $value, $text );
                    }
                }

            // Connections
            } elseif ( preg_match_all( '/\{connection:([a-zA-Z0-9_]+)\}/', $text, $matches ) ) {
                
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

                        // If no post is set, attempt to set it
                        if ( !$post ) {
                            $post = get_post( $post_id );
                        }
    
                        // Validate we have a post
                        if ( $post ) {

                            // Save the post object vars
                            if ( !$post_object_keys ) {
                                $post_object_keys = array_keys( get_object_vars( $post ) );
                            }

                            // Now check each meta key found
                            foreach ( $matches[1] as $meta_key ) {
                                
                                // Get the value
                                $meta_value = '';
                                if ( in_array( $meta_key, $post_object_keys ) ) {
                                    $meta_value = isset( $post->$meta_key ) ? sanitize_text_field( $post->$meta_key ) : '';

                                } else {
                                    $get_value = get_post_meta( $post_id, $meta_key, true );
                                    if ( $get_value !== '' && $get_value !== false ) {
                                        $meta_value = sanitize_text_field( $get_value );
                                    } else {
                                        $meta_value = '[Meta Key "'.$meta_key.'" Does Not Exist]';
                                    }
                                }

                                // Replace
                                $text = str_replace( '{connection:'.$meta_key.'}', $meta_value, $text );
                            }
                        }
                    }
                }
            }
        }
        return $text;
    } // End replace()
}